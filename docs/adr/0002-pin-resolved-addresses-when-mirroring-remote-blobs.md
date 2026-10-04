# 2. Pin resolved addresses when mirroring remote blobs

## Status

Accepted

## Context

`PUT /mirror` accepts a caller-supplied URL and fetches its bytes into local storage. A naive HTTP client given an attacker-controlled URL is a server-side request forgery (SSRF) vector: the caller can aim it at internal services the server can reach but the caller cannot — link-local metadata endpoints such as `169.254.169.254`, loopback admin interfaces, or private-range hosts like `10.0.0.0/8`.

Filtering by hostname or by a single up-front DNS lookup is insufficient. A hostname that resolves to a public address at check time can resolve to a private address moments later when the request actually connects (DNS rebinding). HTTP redirects open the same hole a second time: a public URL can 302 to an internal one.

Some operators legitimately mirror within a trusted private network, so a blanket ban on private addresses cannot be the only behaviour.

## Decision

Mirror fetches resolve the hostname through a vetting DNS resolver and reject any resolved A/AAAA record that falls in a private, loopback, link-local or otherwise reserved range. The client connects to that already-validated address directly and follows redirects manually: redirects are disabled in the HTTP client, and each hop is re-resolved, re-checked against the same rules and re-pinned before connecting. URLs that are already a literal IP are checked before connecting.

Because the connection targets the validated address rather than re-resolving the name, a hostname that passes the check cannot rebind to a private address between the check and the connection. The download is capped at the configured maximum upload size.

A `allow_private_mirror_hosts` configuration flag, defaulting to off, disables the range rejection for operators mirroring inside a trusted private network. With the flag on, resolution and connection pinning still happen; only the private/reserved rejection is lifted.

## Consequences

A caller cannot use the mirror endpoint to reach internal addresses, and the DNS-rebinding and redirect-based bypasses are closed because every address that will be connected to is validated and pinned immediately before the connection. Operators with a genuine private-network use case have an explicit, off-by-default escape hatch rather than being forced to weaken the code.

Manual redirect handling and a custom DNS resolver add code that the default HTTP client would otherwise provide. This cost is accepted because the default client's automatic redirect following would reopen the vulnerability.
