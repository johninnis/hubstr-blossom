# 4. Root faults by the code that raises them

## Status

Accepted

## Context

This application depends on several libraries that signal failure in different ways. The Nostr libraries root their own faults at `NostrException`. The `hubstr-core` kernel roots its faults at `HubstrException`. The `nostr-blossom` package throws no faults of its own, returning failures as `*Failure` values alongside `InvalidArgumentException`. The application itself raises faults from its own infrastructure.

A tempting simplification is to make every fault share one base — for instance to have `HubstrException` extend `NostrException` because the kernel depends on the Nostr libraries, or to re-wrap library faults in an application base so a single `catch` covers everything. Either approach ties the exception hierarchy to the dependency graph rather than to the origin of the failure, which obscures where a fault actually came from and couples unrelated packages through their exception types.

## Decision

Faults are rooted by whose code raises them, not by the dependency graph.

- The application defines its own leaf faults, one per cause so a caught fault names what broke — `BlobInspectionException` (a staged file could not be sized or hashed), `BlobStorageException` (a temp file or the move into the store failed), `CorruptRowException` (a stored row failed to hydrate), `MalformedRouteValueException` (a matched route yielded a value its pattern should have excluded), `MediaProcessingException` and `RemoteBlobFetchException` — each extending `HubstrException`, the kernel root. `HubstrException` is independent of `NostrException` and deliberately does not extend it, even though the application depends on the Nostr libraries.
- Nostr library faults surface as `NostrException`, and `nostr-blossom` failures surface as the returned `*Failure` values it defines.
- The application's own trusted-internal validation throws the native `InvalidArgumentException`, and the mirror address-vetting guard throws `DnsException`. Failures originating in external libraries (PDO, amphp) bubble as their own native throwables.

A consumer does not re-root library faults: each fault keeps the root of the code that raised it. The Presentation layer maps returned `*Failure` outcomes to HTTP responses and lets faults bubble to the process boundary, where they are rendered as a 500 and logged.

## Consequences

The root of a caught exception identifies which body of code failed, which makes failures easier to attribute and handle at the right level. Packages stay decoupled at the type level: depending on a library does not drag its exception base into the dependant's hierarchy. The cost is that there is no single base type that catches every possible fault; code that needs to handle several origins catches each root explicitly, which is the intended trade for clearer attribution.
