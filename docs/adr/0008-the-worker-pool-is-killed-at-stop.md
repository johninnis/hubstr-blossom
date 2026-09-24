# 8. The worker pool is killed at stop

## Status

Accepted

## Context

Blob hashing, media inspection and media optimisation run on a pool of worker processes (ADR-0006). Each task is one-shot: it hashes or re-encodes one file and returns, so unlike a worker that loops on a channel, a worker here does finish on its own.

amphp offers two ways to end a pool. `shutdown()` waits for every worker's current task to complete before exiting the worker; `kill()` ends the worker processes at once. Because the tasks are one-shot, `shutdown()` would normally return, and it reads as the gentler choice. It is not the safer one. The kernel stops the HTTP server before it stops the lifecycle, and a stopped server has finished every in-flight request, so by the time the pool ends no client is waiting on a task. What can still be running is a task that will not end on its own: a decode of a pathological image that survived the pixel budget, or a hash of a file on a stalled disk. `shutdown()` would wait on it, and the process would hang on stop until the supervisor's timeout killed it.

Nothing is lost by a kill. A task's only durable effect is a temporary file, and the ingest coordinator discards the temp file of any request that does not complete; the blob store and the index are written only after a task has returned.

A pool that nothing references is ended the same way: amphp kills the workers of a pool when it is garbage-collected. Relying on that leaves the moment the workers end to the collector rather than to the shutdown sequence.

## Decision

`BlossomLifecycle` owns the worker pool. Its `stop()` kills the pool. It is the lifecycle the kernel runs, so the pool ends when the kernel stops, deterministically and before the process exits. Its `start()` and `drain()` do nothing: the pool spawns its workers on the first task and has nothing to drain once the server has stopped.

## Consequences

- Shutdown returns promptly whatever a worker is doing.
- The pool has one owner with a defined end. Do not hold the pool alive by capturing it elsewhere; ownership belongs in the lifecycle.
- Do not replace `kill()` with `shutdown()` to make the stop "graceful". There is nothing in flight to be graceful about once the server has stopped, and a stuck task would hold the stop open.
