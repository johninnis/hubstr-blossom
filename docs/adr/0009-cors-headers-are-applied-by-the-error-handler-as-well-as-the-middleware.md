# 9. CORS headers are applied by the error handler as well as the middleware

## Status

Accepted

## Context

BUD-01 requires `Access-Control-Allow-Origin: *` on every response, errors included, because a browser client can read the status and the `X-Reason` header of a failed cross-origin request only when the response carries it. The natural place to add the header is a middleware, and `CorsMiddleware` does so for every response that passes through the request-handler stack.

Not every response passes through that stack. The amphp HTTP driver answers some requests itself, before any middleware or route runs: a malformed request line is a `400` and an unknown method is a `501`. For those it calls the server's error handler directly, and no middleware sees the response. `CorsErrorHandler` therefore wraps the templated error handler and applies the same header set, so a browser sees the CORS headers on those responses too.

Two classes applying one header set reads like duplication, and the tempting simplification is to delete the error-handler decorator on the grounds that the middleware already covers errors. It does cover the errors a handler raises, because the kernel answers those inside its own stack, but not the ones the driver raises outside it.

## Decision

`CorsHeaders` holds the header set once. `CorsMiddleware` applies it to every response the request-handler stack produces, and `CorsErrorHandler` applies it to every response the error handler produces, which is how the driver's own errors get it. Both are wired in the composition root, and the acceptance suite sends a malformed request line over a raw socket to prove the `400` the driver answers still carries the headers.

## Consequences

- A cross-origin client sees the CORS headers on every response the server emits, whichever component produced it.
- A response the request-handler stack produces through the error handler receives the headers at both seams; setting the same header twice is idempotent.
- Do not remove `CorsErrorHandler` as a duplicate of the middleware. The raw-socket acceptance test fails if it goes.
