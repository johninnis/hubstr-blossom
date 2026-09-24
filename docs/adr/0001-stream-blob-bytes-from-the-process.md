# 1. Stream blob bytes from the process

## Status

Accepted

## Context

The server stores blobs on the local filesystem and must serve them over HTTP, including range requests, ETags and the correct `Content-Type`. A common performance pattern for a PHP application behind a reverse proxy is to delegate the actual byte transfer to the proxy — for example with nginx `X-Accel-Redirect` or `X-Sendfile` — so the application only authorises the request and names a file for the proxy to send from its own static-file handler.

Two facts make that offload unsuitable here:

- Blobs are content-addressed. They are stored under their SHA-256 digest with no file extension. A proxy static-file handler derives `Content-Type` from the filename extension, so it cannot determine the correct type for an extensionless file.
- BUD-01 requires that the blob's stored MIME type be returned regardless of any extension present in the request URL. The authoritative type lives in the SQLite index, which only the application can consult. A static-file handler would either send no `Content-Type` or one guessed from the URL, both of which violate the specification.

## Decision

The application serves blob bytes itself. It reads the stored file and writes the response body directly, implementing range parsing, ETag handling and the stored `Content-Type` in application code rather than deferring any of these to the proxy. No `X-Accel-Redirect`, `X-Sendfile` or equivalent offload header is emitted.

## Consequences

The server runs correctly behind any reverse proxy, or none, without requiring proxy-specific offload features or configuration. The shipped Caddy configuration is therefore a plain reverse proxy with no static-file handling.

The serving process spends I/O and event-loop time moving blob bytes that a proxy could otherwise have moved. This is mitigated by the non-blocking server reading and writing in chunks, and serving scales horizontally by running more instances behind the proxy. Correctness of the `Content-Type` and range semantics is owned in one place — the application — rather than split between the application and proxy configuration.
