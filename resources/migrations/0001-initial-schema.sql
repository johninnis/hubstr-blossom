CREATE TABLE IF NOT EXISTS blobs (
    tenant_pubkey TEXT NOT NULL,
    sha256        TEXT NOT NULL,
    url           TEXT NOT NULL,
    size          INTEGER NOT NULL,
    type          TEXT NOT NULL,
    uploaded      INTEGER NOT NULL,
    dimensions    TEXT,
    blurhash      TEXT,
    original_hash TEXT,
    PRIMARY KEY (tenant_pubkey, sha256)
);

CREATE INDEX IF NOT EXISTS idx_blobs_tenant_uploaded ON blobs (tenant_pubkey, uploaded DESC, sha256 DESC);

CREATE INDEX IF NOT EXISTS idx_blobs_sha256 ON blobs (sha256);

CREATE TABLE IF NOT EXISTS reports (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    blob_sha256 TEXT NOT NULL,
    event_json  TEXT NOT NULL,
    created_at  INTEGER NOT NULL
);
