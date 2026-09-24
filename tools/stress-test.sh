#!/usr/bin/env bash
#
# Stress test for Hubstr Blossom.
#
# Spawns a real server process (see tools/lib/blossom-under-test.sh), then drives load with curl:
#   - hundreds of signed uploads from one authenticated tenant, each a fresh noise PNG
#   - thousands of unauthenticated reads of those blobs, half of them byte-range requests
# Stored blobs are verified through the tenant's list, and read throughput reported.
#
# Tunables (env), on top of the fixture's PORT, STORAGE and PHP_BIN:
#   UPLOADS    number of blobs the tenant uploads            (default 300, at most 1000 to be countable)
#   BLOB_PX    side of each uploaded noise PNG in pixels      (default 300, about 270KiB)
#   READS      number of unauthenticated GETs                (default 3000)
#   WRITE_PAR  parallel upload connections                   (default 20)
#   READ_PAR   parallel read connections                     (default 100)
#
# The server is launched with xdebug.mode=off; xdebug cuts throughput
# drastically and would skew the numbers. Override the binary with PHP_BIN.
#
# Usage:  tools/stress-test.sh

set -euo pipefail

UPLOADS=${UPLOADS:-300}
BLOB_PX=${BLOB_PX:-300}
READS=${READS:-3000}
WRITE_PAR=${WRITE_PAR:-20}
READ_PAR=${READ_PAR:-100}

# shellcheck source=tools/lib/blossom-under-test.sh
source "$(dirname "${BASH_SOURCE[0]}")/lib/blossom-under-test.sh"

start_blossom_under_test

now() { date +%s.%N; }
elapsed() { awk "BEGIN{printf \"%.2f\", $2 - $1}"; }
rate() { awk "BEGIN{d=$3-$2; printf \"%.0f\", (d>0)? $1/d : 0}"; }

BLOBS="${WORKDIR}/uploads"
mkdir -p "${BLOBS}"
HASHES="${WORKDIR}/hashes"

export -f auth_header random_blob upload_blob
export HTTP TENANT_SEC BLOBS BLOB_PX HASHES PHP_BIN

echo "==> phase 1: ${UPLOADS} uploads of ${BLOB_PX}px noise PNGs from the authenticated tenant (par=${WRITE_PAR})"
w_start="$(now)"
seq 1 "${UPLOADS}" | xargs -P "${WRITE_PAR}" -I{} \
    bash -c 'f="${BLOBS}/{}"; random_blob "$f" "${BLOB_PX}"; [ "$(upload_blob "$f")" = 200 ] && sha256sum "$f" | cut -d" " -f1' \
    > "${HASHES}" || true
w_end="$(now)"

UPLOADED="$(wc -l < "${HASHES}")"
STORED="$(list_blob_count)"
echo "    sent=${UPLOADS} accepted=${UPLOADED} listed=${STORED} in $(elapsed "${w_start}" "${w_end}")s ($(rate "${UPLOADED}" "${w_start}" "${w_end}") up/s, $(rate "$(( $(du -ck "${BLOBS}" | tail -1 | cut -f1) / 1024 ))" "${w_start}" "${w_end}") MiB/s)"

[ "${UPLOADED}" -gt 0 ] || { echo "no upload succeeded" >&2; show_blossom_log_and_fail "phase 1 stored nothing"; }

echo "==> phase 2: ${READS} unauthenticated reads, alternating whole blob and a 64KiB range (par=${READ_PAR})"
r_start="$(now)"
OK="$(seq 1 "${READS}" | xargs -P "${READ_PAR}" -I{} \
    bash -c 'h="$(shuf -n1 "${HASHES}")"; if [ $(( {} % 2 )) -eq 0 ]; then want=200; extra=(); else want=206; extra=(-H "Range: bytes=1024-66559"); fi; [ "$(curl -s -o /dev/null -w "%{http_code}" "${extra[@]}" "${HTTP}/${h}")" = "${want}" ] && echo ok' \
    | grep -c ok || true)"
r_end="$(now)"
echo "    succeeded=${OK}/${READS} in $(elapsed "${r_start}" "${r_end}")s ($(rate "${OK}" "${r_start}" "${r_end}") req/s)"

echo "==> summary"
fail=0
if [ "${UPLOADED}" -ge "${UPLOADS}" ] && [ "${STORED}" -ge "${UPLOADS}" ]; then
    echo "    PASS  all ${UPLOADS} tenant uploads accepted and listed"
else
    echo "    FAIL  accepted ${UPLOADED}/${UPLOADS}, listed ${STORED}/${UPLOADS}"; fail=1
fi
if [ "${OK}" -ge "${READS}" ]; then
    echo "    PASS  all ${READS} reads served"
else
    echo "    WARN  ${OK}/${READS} reads served"
fi

exit "${fail}"
