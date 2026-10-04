# 6. Offload blob hashing and media work to a worker pool

## Status

Accepted

## Context

The server runs as a single long-lived process on one event loop, handling every request concurrently by never blocking it. Two operations on the upload and mirror paths are CPU-bound and synchronous: hashing a blob's bytes to derive its SHA-256 identity (and inspecting it for dimensions and a blurhash), and re-encoding media for the optimisation endpoint. Run inline on the event loop, either would block every other in-flight request for the duration of the work, defeating the concurrency the single-process model exists to provide.

## Decision

The CPU-bound operations are offloaded to a parallel worker pool. The blob inspector and media optimiser are wrapped by `WorkerBlobInspector` and `WorkerMediaOptimiser`, decorators that submit the real synchronous implementation as a task to the pool and await the result, rather than running it on the event loop. The pool size is configurable through `worker_pool_limit`, where `0` selects the runtime default. Each decorator awaits the task and returns the result through the port's declared value type (`IncomingBlob`, `PendingBlob`), so a worker that yields an unexpected type fails at that return boundary rather than propagating a malformed value onward.

## Consequences

Hashing and media re-encoding run on separate workers, so the event loop stays responsive and other requests are served while that work proceeds. The decorator shape keeps the offload concern out of the synchronous implementations, which remain plain and directly testable; the worker variant is wired in only at composition time.

The pool adds the cost of serialising task input and output across the process boundary and a configurable ceiling on concurrent CPU work. Sizing the pool is an operational tuning knob: too small throttles throughput under load, too large oversubscribes the host's cores.
