# 3. Separate durable and disposable state on disk

## Status

Accepted

## Context

The server writes several kinds of state to disk: stored blob bytes, the SQLite index that records who owns each blob, an upload-staging area, the compiled template cache and tool caches. These differ fundamentally in one respect — whether losing them is data loss or merely a cache miss.

Blob bytes and the index cannot be regenerated. Losing the bytes loses user data; losing the index orphans the bytes, because a content-addressed blob is meaningless without the rows recording which tenant owns it, its declared type and its metadata. Caches, by contrast, are rebuilt on demand and can be deleted at any time with no loss.

A single runtime directory holding all of this makes routine operations dangerous: "clear the runtime directory to reclaim space" or "wipe the scratch area" would silently destroy irreplaceable data.

## Decision

State is split across two top-level directories by replaceability:

- `data/` holds the durable state: blob bytes, the upload-staging area (`storage_path`, with its `tmp/` subdirectory), and the SQLite index (`database_path`). The staging area lives under the blob root so completed uploads move into place with an atomic same-filesystem rename.
- `var/` holds the disposable state: the compiled template cache and tool caches. The log goes to standard output for the supervisor to keep, so no log file is written anywhere.

## Consequences

The backup boundary is unambiguous: `data/` is the only directory that must be backed up, and `var/` can be deleted at any time without losing anything. The systemd unit grants write access to both paths explicitly, and operational guidance can safely treat `var/` as scratch.

The staging area must share a filesystem with the blob root for the atomic rename to hold, which the layout guarantees by nesting `tmp/` under `storage_path`. The split is a convention the configuration and deployment resources must keep consistent; the defaults in the example configuration encode it.
