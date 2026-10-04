# 5. Cache blob descriptors with a decorator over the index

## Status

Accepted

## Context

Serving a blob requires its descriptor — the row recording owner, size, type and metadata — which lives in the SQLite index. The same blobs tend to be requested repeatedly, so each `GET`/`HEAD` would otherwise hit SQLite for a row that has not changed. The index is a single-writer store: writes happen only on upload and delete, both of which pass through the same process.

Adding caching directly inside the SQLite implementation would mix two concerns — durable storage and an in-memory read cache — in one class, and would make the cache impossible to test or swap independently.

## Decision

Descriptor caching is a separate `CachingBlobIndex` that decorates the `SqliteBlobIndex` behind the shared index interface. It keeps a bounded in-memory map of descriptors keyed by hash, with least-recently-used eviction once a fixed entry limit is reached. Reads consult the cache first and populate it on a miss. Writes that pass through the same decorator keep the cache coherent: `save` stores the new descriptor, and `deleteForTenant` evicts the entry.

The cache is safe specifically because the store has a single writer in the same process. Every mutation flows through this decorator, so there is no out-of-band writer that could leave the cache stale relative to the store.

## Consequences

Repeated reads of a hot blob are served from memory without touching SQLite, and the storage class stays a plain index with no caching concern folded in. The cache can be tested and reasoned about on its own.

The coherence guarantee depends on all writes flowing through the one decorator instance. A second writer to the same database — another process, or a bypass of the decorator — would not invalidate cached entries and could serve a stale descriptor. This is an accepted constraint of the single-writer deployment model, not a general-purpose cache.
