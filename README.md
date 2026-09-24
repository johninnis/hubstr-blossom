# Hubstr Blossom

[![CI](https://github.com/johninnis/hubstr-blossom/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/hubstr-blossom/actions/workflows/ci.yml)

A personal [Blossom](https://github.com/hzrd149/blossom) media server for Nostr: your images, video and files stored under their content hash, served from your own origin, and managed with your Nostr key.

## What it does

Your Nostr client uploads media here instead of to a third-party host, and puts the resulting URL into the events it publishes. The server stores each blob on the local filesystem under its SHA-256 hash, records which of your keys uploaded it in SQLite, and serves it back to anyone who asks.

It has two access tiers:

- **Anyone** can fetch a blob by its hash. A blob's address travels inside a public event, so every client rendering that event can display it without holding a credential from this server.
- **You** (a configured tenant, authenticated with a signed kind-24242 event) can upload, mirror, optimise, list and delete.

Reads are public by design, and there is no setting to close them; content that must stay private is encrypted before it is uploaded. The reasoning is recorded in [ADR-0007](docs/adr/0007-blobs-are-served-to-anyone-who-asks.md).

## How it works

![Diagram of how Hubstr Blossom mirrors blobs from other servers on request, serves any blob to anyone without auth, accepts uploads and deletes from tenants, and stores blobs on the filesystem](docs/diagram.svg)

Every upload is hashed and type-sniffed by the server itself: the stored hash is what was received, not what was declared, and the stored type is what the bytes are, not what the `Content-Type` header said. Blob bytes are streamed straight from the process, with range requests, ETags and the stored `Content-Type` handled in application code, so the server runs behind any reverse proxy (or none) without proxy-specific offload features such as `X-Accel-Redirect`. See [ADR-0001](docs/adr/0001-stream-blob-bytes-from-the-process.md).

The hashing, the NIP-94 metadata extraction (dimensions and blurhash) and the media optimisation are CPU-bound, so they run on a parallel worker pool rather than on the event loop. See [ADR-0006](docs/adr/0006-offload-cpu-work-to-a-worker-pool.md).

Optimisation re-encodes the image and keeps none of its metadata, so a JPEG's EXIF orientation is applied to the pixels first: a portrait phone photo sent to `PUT /media` is stored upright, and its EXIF (including any location data) does not travel with it. Animated GIFs and WebPs, and images over `max_image_pixels`, are stored as received. See [ADR-0010](docs/adr/0010-optimised-media-carries-no-metadata.md).

## Supported BUDs

| BUD    | Capability                                              |
|--------|---------------------------------------------------------|
| BUD-01 | `GET`/`HEAD /<sha256>` with Content-Type, ranges, ETags |
| BUD-02 | `PUT /upload`                                           |
| BUD-04 | `PUT /mirror`                                           |
| BUD-05 | `PUT /media` (server-side optimisation)                 |
| BUD-06 | `HEAD /upload` and `HEAD /media` upload requirements    |
| BUD-08 | NIP-94 file metadata (dimensions, blurhash)             |
| BUD-09 | `PUT /report` (kind 1984 blob reports)                  |
| BUD-11 | Kind-24242 endpoint authorisation on every write        |
| BUD-12 | `DELETE /<sha256>`, `GET /list/<pubkey>`                |

Every write requires a kind-24242 authorisation event whose `x` tag names the blob it authorises; the server-side rules for that event (verb, expiry, server scope, blob binding) live in the `innis/nostr-blossom` library and are recorded in its ADRs.

## Mirroring and SSRF protection

`PUT /mirror` fetches bytes from a caller-supplied URL, so it is guarded against being used to reach internal services. With `allow_private_mirror_hosts` set to `false` (the default), the source host is resolved and every resolved address is rejected if it falls in a private, loopback, link-local or otherwise reserved range (for example `10.0.0.0/8`, `127.0.0.1`, `169.254.169.254` or `fd00::/8`). The connection is pinned to that validated address, and every redirect hop is re-resolved and re-checked, so the address cannot rebind to a private one between the check and the request. The download is capped at `max_upload_bytes`.

Set `allow_private_mirror_hosts` to `true` only when mirroring within a trusted private network; it disables the address checks (resolution and connection pinning still happen, but private and reserved addresses are no longer rejected).

See [ADR-0002](docs/adr/0002-pin-resolved-addresses-when-mirroring.md) for the threat model and why addresses are pinned per hop.

## Browser access

The server is browser-facing, so it advertises permissive CORS. Every response carries `access-control-allow-origin: *` together with the allowed and exposed header lists (including the Blossom `X-*` headers such as `X-Reason`, `X-Max-Upload-Size` and `X-Content-Types`), including the errors the HTTP driver answers before a request reaches a route (see [ADR-0009](docs/adr/0009-cors-headers-are-applied-by-the-error-handler-as-well.md)). `OPTIONS` preflight requests are synthesised for every route and answered with `204` and the methods allowed on that path, so cross-origin clients can preflight uploads, mirrors, deletes and reports without any per-route configuration.

The root path serves a small landing page (`templates/index.latte`, styled by the assets under `public/`); unknown paths fall back to serving a matching static asset or an HTML error page (`templates/error.latte`).

## Storage

Blob bytes live on the local filesystem under `storage_path`, sharded by the first two hex characters of their hash, with the upload-staging area in a `tmp/` subdirectory of the same root so a completed upload moves into place with an atomic same-filesystem rename. The SQLite index (WAL mode) records one row per tenant per blob, so the same bytes uploaded by two tenants are stored once and removed only when the last tenant deletes them. A small in-process descriptor cache speeds repeated reads of the same blob (see [ADR-0005](docs/adr/0005-cache-blob-descriptors-with-a-decorator.md)).

The index suits a metadata workload of small, infrequent writes (one row per upload or delete) against many reads; it is not intended as a high-write-contention store.

The blobs and the index live in `data/` and the caches in `var/`. Back up `data/`; you can delete `var/` at any time without losing anything. The server writes no log file of its own, logging to standard output for its supervisor to keep. The sibling `hubstr-relay` service uses the same layout. See [ADR-0003](docs/adr/0003-separate-durable-and-disposable-state.md) for the reasoning behind the split.

## Stack

- PHP 8.4+ with Amphp (async HTTP server); blob bytes are streamed from the process
- SQLite (WAL mode) for the per-tenant index; hashing and media work run on dedicated worker processes, off the event loop
- GD for media optimisation and NIP-94 metadata
- Caddy for TLS termination (automatic HTTPS via Let's Encrypt)
- Built on the `innis/nostr-blossom`, `innis/nostr-core`, and `innis/hubstr-core` packages ([github.com/johninnis/nostr-blossom](https://github.com/johninnis/nostr-blossom), [github.com/johninnis/nostr-core](https://github.com/johninnis/nostr-core), [github.com/johninnis/hubstr-core](https://github.com/johninnis/hubstr-core)). `nostr-blossom` supplies the use cases and the authorisation rules; `hubstr-core` supplies the `Kernel` application entrypoint, configuration, logging, SQLite and templating.

## Requirements

- PHP 8.4+ with `ext-ctype`, `ext-exif`, `ext-fileinfo`, `ext-gd`, `ext-pdo_sqlite`, `ext-pcntl`, `ext-zlib`, `ext-gmp`, `ext-intl`, `ext-mbstring`, `ext-openssl` and `ext-sodium`
- `ext-ffi` with `libsecp256k1` for native signature verification. A deployment can run without it, on a pure-PHP fallback; a development install cannot, because the test suite exercises the native path and `ext-ffi` is a dev requirement
- [Caddy](https://caddyserver.com/) (or another reverse proxy) for TLS in a public deployment

A development install additionally needs `ext-iconv`, which `php-cs-fixer` pulls in through `symfony/polyfill-mbstring`. The server itself never calls it, so a production `composer install --no-dev` does not require it.

## Install

```bash
git clone https://github.com/johninnis/hubstr-blossom.git
cd hubstr-blossom
composer install
cp config/blossom.example.php config/blossom.php
```

Edit `config/blossom.php` and set at least `tenant_pubkeys` and `base_url`.

**Deploying a release:** check out the release tag *before* installing, so the server reports the release version on its landing and error pages rather than a branch ref:

```bash
git checkout "$(git tag --sort=-v:refname | head -1)"
composer install --no-dev
```

The version is read from the git state at install time: a tag checkout reports that tag, while staying on `master` (or pulling past the tag) reports `dev-master@<sha>`.

## Configuration

Startup fails with a message naming the offending key if a **required** key is missing or malformed; optional keys fall back to the default shown.

| Key                | Required | Description                                            |
|--------------------|----------|--------------------------------------------------------|
| `tenant_pubkeys`   | Yes      | Public keys permitted to upload, delete, mirror and list, each as 64 hex characters or an `npub`; at least one |
| `port`             | Yes      | Port the HTTP server listens on                        |
| `base_url`         | Yes      | Public origin used to build blob URLs in descriptors   |
| `storage_path`     | Yes      | Durable blob root (`data/blobs` in the example config): blob bytes plus the `tmp/` upload-staging area, so completed uploads move into place with an atomic same-filesystem rename |
| `database_path`    | Yes      | SQLite index file (`data/hubstr-blossom.sqlite` in the example config) |
| `max_upload_bytes` | Yes      | Maximum accepted upload size, also the mirror fetch ceiling; must be positive |
| `allowed_types`    | Yes      | MIME types accepted for storage, as a list of `type/subtype` strings; at least one, and a malformed entry is refused at start-up |
| `host`             | No       | Listen address (default `127.0.0.1`)                   |
| `log_level`        | No       | Level `debug`, `info`, `error`, ... (default `info`). The log goes to standard output only; there is no log file, and a leftover `log_path` key is refused at start-up |
| `trusted_proxies`  | No       | Proxies trusted for `X-Forwarded-For` (default `['127.0.0.1']`) |
| `allow_private_mirror_hosts` | No | Permit `PUT /mirror` to fetch from private/loopback hosts (default `false`; leave off unless mirroring within a trusted private network) |
| `worker_pool_limit` | No      | Maximum parallel workers for blob hashing and media optimisation (default `0`, which uses the amphp default) |
| `max_image_pixels` | No       | Decompression-bomb guard: reject decoding any image whose width x height exceeds this (default `50000000`, 50MP). Peak decode memory is roughly pixels x 4 bytes per concurrent worker, so size it together with `worker_pool_limit` and the worker `memory_limit` |

`config/blossom.example.php` already sets every required key, so a copy of it needs only `tenant_pubkeys` and `base_url` changed.

The config path can be overridden with the `HUBSTR_BLOSSOM_CONFIG` environment variable.

## Run

```bash
php bin/hubstr-blossom.php
```

## Deployment

The server listens on localhost. Use Caddy (or similar) as a reverse proxy for TLS termination; a plain `reverse_proxy` is all that is required, because the daemon streams blob bytes, handles ranges, ETags and CORS, and returns the stored `Content-Type` itself. See `resources/Caddyfile` for an example configuration with automatic TLS and an edge upload-size limit.

The SQLite schema is applied by the daemon on start from the numbered migrations in `resources/migrations/` (`0001-initial-schema.sql` onwards). The reached version is kept in SQLite's `user_version`; a schema change is a new numbered file, never an edit to one that has shipped.

### Running as a service

For a VPS deployment, run the server under systemd so it survives logout and restarts on failure. An example unit is in `resources/hubstr-blossom.service`; it runs the daemon under a dedicated user with `ProtectSystem=strict`, granting write access only to `data/` and `var/`.

```bash
sudo cp resources/hubstr-blossom.service /etc/systemd/system/
# Edit User, WorkingDirectory, and ReadWritePaths to match your host
sudo systemctl daemon-reload
sudo systemctl enable --now hubstr-blossom
sudo journalctl -u hubstr-blossom -f
```

## CLI tools

```bash
# List stored blobs per tenant, newest first
php bin/list.php
php bin/list.php --limit=50

# List the kind-1984 reports received through PUT /report, newest first
php bin/reports.php
php bin/reports.php --limit=50
```

Each report line carries when it was received, the reported blob's hash, the NIP-56 report type from the report's `x` tag, the reporter's npub and the reason. Both tools refuse a `--limit` they cannot use — one that is not a number, zero, or above the 1000-row page ceiling — naming the option on stderr and exiting with status 2.

Blobs are removed only by an explicit, authenticated `DELETE /<sha256>` from the tenant that holds them (BUD-12); there is deliberately no bulk or age-based deletion tool.

## Development

```bash
composer test              # Full test suite (unit, integration, BUD acceptance) + static analysis
composer test-unit         # Unit tests only
composer test-integration  # Integration tests only
composer test-acceptance   # BUD acceptance suite only (spawns a live daemon)
composer test-coverage     # Coverage report
composer analyse           # PHPStan (level 9)
composer fix-style         # PHP-CS-Fixer
composer check-style       # Dry-run style check
composer rector            # Apply the PHP 8.4 modernisation rules
composer check-rector      # Dry-run Rector check
```

The acceptance suite boots a real daemon on a free port against a throwaway config and storage directory, and drives every supported BUD over HTTP.

Two shell harnesses drive a real server process over HTTP with `curl`, minting the tenant's kind-24242 authorisation events with [nak](https://github.com/fiatjaf/nak), against a throwaway config, storage directory and database on a free port; the fixture they share lives in `tools/lib/blossom-under-test.sh`:

```bash
tools/stress-test.sh         # Hundreds of tenant uploads, thousands of whole and ranged reads; reports throughput
tools/leak-test.sh           # Sustained upload and read churn; samples memory, file descriptors and staged temp files for leaks
```

Both take their sizes from environment variables named in the script headers.

## Architecture decisions

Design rationale — the deliberate choices that read like smells until you know why — lives in version-controlled records under [`docs/adr/`](docs/adr/).

## License

MIT
