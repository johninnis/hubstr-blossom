#!/usr/bin/env bash
#
# Shared fixture for the network harnesses: spawns a real Blossom server on a
# free port against a throwaway config, storage directory and database, waits
# until it serves its landing page, and mints kind-24242 authorisation headers
# for one configured tenant so the harness can drive authenticated writes.
#
# Every direct nak call has its stdin closed: nak reads an event from an open
# non-terminal stdin and would block when the script is run non-interactively.
#
# Requires: nak, php (with GD), curl, sha256sum, base64, python3 (free-port pick and JSON counting).
#
# Tunables (env):
#   PORT       server port                                  (default: a free one)
#   STORAGE    an existing storage directory to run against  (default: a fresh temp one)
#   PHP_BIN    php binary to launch the server with          (default: php)
#
# Provides: ROOT, HTTP, WORKDIR, TENANT_SEC, TENANT_PUB, BLOSSOM_PID, MAX_UPLOAD_BYTES,
# and the functions start_blossom_under_test, blossom_is_alive, auth_header,
# upload_blob, list_blob_count and random_blob. A harness that starts background
# work registers its pids in BACKGROUND_PIDS so cleanup ends them before the server.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN=${PHP_BIN:-php}

for bin in nak "${PHP_BIN}" curl sha256sum base64 python3; do
    command -v "$bin" >/dev/null 2>&1 || { echo "missing required tool: $bin" >&2; exit 1; }
done

pick_port() {
    python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()'
}
PORT=${PORT:-$(pick_port)}

HTTP="http://127.0.0.1:${PORT}"
MAX_UPLOAD_BYTES=104857600

WORKDIR="$(mktemp -d)"
CONFIG="${WORKDIR}/blossom.php"
STORAGE="${STORAGE:-${WORKDIR}/blobs}"
BLOSSOM_PID=""
BACKGROUND_PIDS=()

cleanup() {
    for pid in "${BACKGROUND_PIDS[@]}"; do kill "${pid}" 2>/dev/null || true; done
    [ -n "${BLOSSOM_PID}" ] && kill "${BLOSSOM_PID}" 2>/dev/null || true
    [ -n "${BLOSSOM_PID}" ] && wait "${BLOSSOM_PID}" 2>/dev/null || true
    rm -rf "${WORKDIR}"
}
trap cleanup EXIT

TENANT_SEC="$(nak key generate </dev/null)"
TENANT_PUB="$(nak key public "${TENANT_SEC}")"

cat > "${CONFIG}" <<PHP
<?php

declare(strict_types=1);

return [
    'tenant_pubkeys' => ['${TENANT_PUB}'],
    'host' => '127.0.0.1',
    'port' => ${PORT},
    'base_url' => '${HTTP}',
    'storage_path' => '${STORAGE}',
    'database_path' => '${WORKDIR}/hubstr-blossom.sqlite',
    'max_upload_bytes' => ${MAX_UPLOAD_BYTES},
    'allowed_types' => ['image/png'],
    'trusted_proxies' => ['127.0.0.1'],
    'log_level' => 'warning',
];
PHP

blossom_is_alive() {
    kill -0 "${BLOSSOM_PID}" 2>/dev/null
}

show_blossom_log_and_fail() {
    echo "$1; see ${WORKDIR}/blossom.log" >&2
    cat "${WORKDIR}/blossom.log" >&2 || true
    exit 1
}

start_blossom_under_test() {
    echo "==> blossom on ${HTTP}  (tenant ${TENANT_PUB:0:12}...)"
    HUBSTR_BLOSSOM_CONFIG="${CONFIG}" "${PHP_BIN}" -d xdebug.mode=off "${ROOT}/bin/hubstr-blossom.php" > "${WORKDIR}/blossom.log" 2>&1 &
    BLOSSOM_PID=$!

    echo -n "==> waiting for blossom (landing page)"
    local ready=0
    for _ in $(seq 1 100); do
        if [ "$(curl -s -o /dev/null -w '%{http_code}' "${HTTP}/")" = 200 ]; then ready=1; break; fi
        blossom_is_alive || { echo; show_blossom_log_and_fail "blossom died on startup"; }
        echo -n "."; sleep 0.2
    done
    echo
    [ "${ready}" = 1 ] || { echo "blossom did not become ready" >&2; exit 1; }
}

# auth_header <verb> [<sha256>]: a "Nostr <base64>" value for the Authorization header.
auth_header() {
    local verb="$1" hash="${2:-}" tags
    tags=(-t "t=${verb}" -t "expiration=$(( $(date +%s) + 3600 ))")
    [ -n "${hash}" ] && tags+=(-t "x=${hash}")
    nak event -k 24242 -c 'Blossom auth' "${tags[@]}" --sec "${TENANT_SEC}" -q 2>/dev/null </dev/null | base64 -w0
}

# random_blob <path> <pixels>: writes a square PNG of random pixels with that side length.
# Noise compresses poorly, so 300px is roughly 270KiB, and every blob is unique. A real
# image is what a Blossom server receives, and it drives the worker pool through the type
# sniff, the dimension read and the blurhash decode that every upload pays for.
random_blob() {
    "${PHP_BIN}" -d xdebug.mode=off -r '$s = (int) $argv[1]; $image = imagecreatetruecolor($s, $s); for ($y = 0; $y < $s; ++$y) { for ($x = 0; $x < $s; ++$x) { imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF)); } } imagepng($image, $argv[2], 6);' "$2" "$1"
}

# upload_blob <path>: PUTs the file as image/png; prints the HTTP status.
upload_blob() {
    local hash
    hash="$(sha256sum "$1" | cut -d' ' -f1)"
    curl -s -o /dev/null -w '%{http_code}' -X PUT "${HTTP}/upload" \
        -H "Authorization: Nostr $(auth_header upload "${hash}")" \
        -H 'Content-Type: image/png' \
        --data-binary "@$1"
}

# list_blob_count: how many blobs the tenant's list reports (the list page is capped at 1000).
list_blob_count() {
    curl -s "${HTTP}/list/${TENANT_PUB}?limit=1000" \
        -H "Authorization: Nostr $(auth_header list)" \
        | python3 -c 'import json,sys; body=json.load(sys.stdin); print(len(body) if isinstance(body, list) else 0)'
}
