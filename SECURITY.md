# Security

This document describes the security properties Hubstr Blossom provides, the properties it deliberately leaves to the operator, and the reasoning behind the non-obvious decisions. It is the reference an operator should read before exposing this server to the internet, and the reference a contributor should read before changing any authorisation, ingest or mirror path.

Hubstr Blossom is a deployable service. Unlike a library, it has an attack surface the moment it is running: a public HTTP endpoint that strangers fetch from by design, an upload path that writes to disk, and a mirror endpoint that makes outbound requests to caller-supplied URLs.

## Audit status

**This server has not undergone an independent third-party security audit.** It is built and reviewed with care, with the design decisions recorded in [`docs/adr/`](docs/adr/) and an acceptance suite that drives a live daemon through every supported BUD on every CI run, but internal review is not a substitute for an external audit. Treat these guarantees as best-effort and not externally verified. If you commission or perform an audit, please share the results through the vulnerability-reporting channel below.

## Reporting a vulnerability

If you have found a security vulnerability in Hubstr Blossom, report it privately through GitHub's built-in vulnerability reporting: **Security → Advisories → Report a vulnerability** on the repository page. Do not open a public issue for security-sensitive bugs.

Include:

- A description of the vulnerability and its impact.
- Reproduction steps or a proof-of-concept.
- The affected version (tag or commit SHA).

Acknowledgement is best-effort within 72 hours. Fixes land first, then the advisory is published.

For non-security bugs, open a regular issue.

This project does not run a bug bounty.

## Supported versions

Only the latest tagged release is supported. Older releases do not receive backported fixes, ever. Use the latest version.

## Security properties

### What this server provides

- **Every tenant action is authenticated by signature.** Upload, mirror, media, list and delete each require a kind-24242 event signed by a configured tenant key, naming the verb, carrying an expiry, and binding the blob by hash in an `x` tag. A valid signature from a key that is not a tenant is refused before the signature is even verified, so a stranger cannot make the server spend secp256k1 work on their request.
- **A blob is what the server received, not what the caller declared.** The stored hash is computed by the server over the bytes on disk; a declared `X-SHA-256` that disagrees is refused. The stored MIME type is sniffed from the bytes, so a `Content-Type` header cannot smuggle a disallowed type past the allow-list, and a declared type cannot mislabel the served content.
- **The mirror endpoint cannot reach your internal network.** Mirror fetches resolve the source host through a vetting resolver that refuses private, loopback, link-local and reserved addresses, connect to the vetted address rather than re-resolving the name, and re-vet every redirect hop, closing the DNS-rebinding and redirect bypasses. The download is capped at `max_upload_bytes`. See [ADR-0002](docs/adr/0002-pin-resolved-addresses-when-mirroring.md).
- **A decompression bomb is never decoded.** Image dimensions are read from the header before any pixel decode, and an image whose width times height exceeds `max_image_pixels` is neither optimised nor blurhashed. The decode that does run happens on a worker process, so it cannot stall the event loop for other clients. See [ADR-0006](docs/adr/0006-offload-cpu-work-to-a-worker-pool.md).
- **Request bodies are bounded.** An upload larger than `max_upload_bytes` is refused with `413` and a reason, whether its size is declared up front or discovered while streaming: the server stops reading at the cap rather than buffering the body to measure it. The mirror fetch is cut at the same cap. The mirror and report endpoints buffer at most a few kilobytes of JSON and answer `413` beyond that, and the HTTP driver's own limit sits just above the cap as a backstop.
- **Every query is parameterised.** SQL is confined to the persistence store classes and values reach SQLite as bound parameters, never as interpolated text.
- **The database file is closed to other users.** The SQLite index and its WAL and shared-memory files are created owner-only, so another local account cannot read the tenant-to-blob mapping.
- **Deleted bytes go when the last holder lets go.** The same blob uploaded by two tenants is stored once; a delete removes the caller's row and removes the bytes only when no row remains, so one tenant cannot delete another tenant's blob out from under them.

### What this server does not provide

- **TLS.** The server speaks plain HTTP and expects a reverse proxy in front of it. Without TLS the signed authorisation header, and every blob a client fetches, are on the wire in clear. Serve it over TLS.
- **Private reads.** Any blob is served to anyone who asks for its hash, and there is no configuration key to change that. A blob's URL travels inside a public event, so gating reads would only stop published content from displaying. Content that must stay private is encrypted before it is uploaded. See [ADR-0007](docs/adr/0007-blobs-are-served-to-anyone-who-asks.md).
- **Content scanning.** The server checks that bytes are of an allowed type and that they hash to what was claimed. It does not scan for malware, illegal content or anything else. `PUT /report` records a kind-1984 report against a blob, and `bin/reports.php` lists what has been received; acting on it is the operator's job.
- **Protection against active content.** The example `allowed_types` excludes HTML and SVG, and the shipped Caddyfile sandboxes blob responses with a `Content-Security-Policy` at the edge. If you allow `text/html` or `image/svg+xml`, you are serving user-uploaded scripts from your origin. Keep them off the allow-list.
- **Protection of the tenant key.** Anyone holding a tenant key can upload to, and delete from, this server as you. Prefer a remote signer over a key in browser storage.
- **A correct client address without configuration.** The proxy's address is what the server sees unless `trusted_proxies` names your proxy.
- **Protection against a hostile tenant.** A tenant is the operator. Do not configure a key as a tenant unless you would hand that key the server's disk.
- **Rate limiting.** Nothing in the server throttles a reader who is merely expensive rather than unauthorised. Put that at the edge.
- **Multi-process operation.** The descriptor cache is coherent only because every write flows through one process. Running two copies against one index is not a supported configuration. See [ADR-0005](docs/adr/0005-cache-blob-descriptors-with-a-decorator.md).
- **Encryption at rest.** Blob bytes and the index are stored in plain form. Protect the `data/` directory and its backups.

## Design decisions

### Reads are public on purpose

A server offering three read policies in its library and wiring only the public one reads like a forgotten configuration key. It is not. A Blossom blob exists to be displayed by every client that renders the event referencing it, and those clients hold no credential from this server. Gating reads protects nothing the URL has not already disclosed and breaks the one thing the upload was for. Do not add a configuration key for it. See [ADR-0007](docs/adr/0007-blobs-are-served-to-anyone-who-asks.md).

### The mirror fetch pins the address it vetted

Checking a hostname and then letting the HTTP client resolve it again is a rebinding hole, and letting the client follow redirects reopens it a second time. The fetcher therefore owns its own resolver and its own redirect loop, and the connection goes to the address that passed the check. This is more code than the default client, and that cost is accepted. An `allow_private_mirror_hosts` flag exists for operators mirroring inside a trusted network; it lifts the range check only, never the pinning. See [ADR-0002](docs/adr/0002-pin-resolved-addresses-when-mirroring.md).

### Blob bytes are streamed by the process, not offloaded to the proxy

Blobs are content-addressed and extensionless, and BUD-01 requires the stored MIME type on every response, so a proxy's static-file handler cannot serve them correctly. The application serves the bytes itself and owns the range, ETag and `Content-Type` semantics in one place. See [ADR-0001](docs/adr/0001-stream-blob-bytes-from-the-process.md).
