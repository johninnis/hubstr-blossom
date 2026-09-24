#!/usr/bin/env bash
#
# Leak test for Hubstr Blossom.
#
# Spawns a real server process (see tools/lib/blossom-under-test.sh) and drives
# sustained churn for a fixed duration: rounds of short-lived range reads
# interleaved with signed uploads, each upload a fresh noise PNG hashed, sniffed
# and blurhashed on the worker pool. Meanwhile it samples the server's resident
# memory (RSS), the RSS of its whole process tree (the workers are children)
# and its open file descriptor count at a fixed interval, so a memory, socket
# or temp-file leak shows up as monotonic growth rather than a plateau.
#
# The upload churn is the point: every upload opens a staged temp file, a
# worker task and a stored blob; one that is not closed or moved surfaces here
# as a rising FD count or tree RSS even while throughput looks fine.
#
# Tunables (env), on top of the fixture's PORT, STORAGE and PHP_BIN:
#   DURATION   soak duration in seconds                    (default 120)
#   INTERVAL   sampling interval in seconds                (default 5)
#   BATCH      short-lived reads per round                  (default 60)
#   READ_PAR   parallel read connections                    (default 60)
#   WRITES     uploads per round                            (default 5)
#   BLOB_PX    side of each uploaded noise PNG in pixels     (default 300, about 270KiB)
#
# Usage:  tools/leak-test.sh

set -euo pipefail

DURATION=${DURATION:-120}
INTERVAL=${INTERVAL:-5}
BATCH=${BATCH:-60}
READ_PAR=${READ_PAR:-60}
WRITES=${WRITES:-5}
BLOB_PX=${BLOB_PX:-300}

# shellcheck source=tools/lib/blossom-under-test.sh
source "$(dirname "${BASH_SOURCE[0]}")/lib/blossom-under-test.sh"

start_blossom_under_test

BLOBS="${WORKDIR}/uploads"
mkdir -p "${BLOBS}"
HASHES="${WORKDIR}/hashes"

export -f auth_header random_blob upload_blob
export HTTP TENANT_SEC BLOBS BLOB_PX HASHES PHP_BIN

echo "==> seeding 20 blobs"
seq 1 20 | xargs -P 10 -I{} \
    bash -c 'f="${BLOBS}/seed-{}"; random_blob "$f" "${BLOB_PX}"; [ "$(upload_blob "$f")" = 200 ] && sha256sum "$f" | cut -d" " -f1' \
    > "${HASHES}" || true
[ -s "${HASHES}" ] || show_blossom_log_and_fail "seeding stored nothing"

load_loop() {
    local round=0
    while :; do
        round=$(( round + 1 ))
        seq 1 "${BATCH}" | xargs -P "${READ_PAR}" -I{} \
            bash -c 'h="$(shuf -n1 "${HASHES}")"; curl -s -o /dev/null -H "Range: bytes=0-4095" "${HTTP}/${h}"' || true
        seq 1 "${WRITES}" | xargs -P "${WRITES}" -I{} \
            bash -c "f=\"\${BLOBS}/soak-${round}-{}\"; random_blob \"\$f\" \"\${BLOB_PX}\"; upload_blob \"\$f\" >/dev/null; rm -f \"\$f\"" || true
    done
}
load_loop &
LOAD_PID=$!
BACKGROUND_PIDS+=("${LOAD_PID}")

rss_kb() { awk '/^VmRSS:/{print $2}' "/proc/$1/status" 2>/dev/null || echo 0; }
fd_count() { ls "/proc/$1/fd" 2>/dev/null | wc -l; }
tree_rss_kb() {
    local total; total=0
    for pid in $1 $(pgrep -P "$1" 2>/dev/null || true); do
        total=$((total + $(rss_kb "$pid")))
    done
    echo "${total}"
}
staged_files() { find "${STORAGE}/tmp" -type f 2>/dev/null | wc -l; }

echo "==> soak: ${DURATION}s, sampling every ${INTERVAL}s (${BATCH} reads + ${WRITES} uploads per round, par=${READ_PAR})"
printf "    %6s  %10s  %12s  %6s  %7s\n" "t(s)" "rss(MB)" "tree(MB)" "fds" "staged"

samples_rss=(); samples_fd=()
start="$(date +%s)"
while :; do
    t=$(( $(date +%s) - start ))
    [ "${t}" -ge "${DURATION}" ] && break
    blossom_is_alive || show_blossom_log_and_fail "    blossom died during soak"

    rss=$(rss_kb "${BLOSSOM_PID}"); tree=$(tree_rss_kb "${BLOSSOM_PID}"); fds=$(fd_count "${BLOSSOM_PID}")
    samples_rss+=("${rss}"); samples_fd+=("${fds}")
    printf "    %6s  %10.1f  %12.1f  %6s  %7s\n" "${t}" "$(awk "BEGIN{print ${rss}/1024}")" "$(awk "BEGIN{print ${tree}/1024}")" "${fds}" "$(staged_files)"
    sleep "${INTERVAL}"
done

kill "${LOAD_PID}" 2>/dev/null || true
sleep 3
final_rss=$(rss_kb "${BLOSSOM_PID}"); final_fds=$(fd_count "${BLOSSOM_PID}"); final_staged=$(staged_files)

first_rss=${samples_rss[0]}; peak_rss=0
for v in "${samples_rss[@]}"; do [ "${v}" -gt "${peak_rss}" ] && peak_rss=${v}; done
first_fd=${samples_fd[0]}; peak_fd=0
for v in "${samples_fd[@]}"; do [ "${v}" -gt "${peak_fd}" ] && peak_fd=${v}; done

echo "==> summary"
printf "    RSS     first=%.1fMB  peak=%.1fMB  final(after drain)=%.1fMB\n" \
    "$(awk "BEGIN{print ${first_rss}/1024}")" "$(awk "BEGIN{print ${peak_rss}/1024}")" "$(awk "BEGIN{print ${final_rss}/1024}")"
printf "    FDs     first=%s  peak=%s  final(after drain)=%s\n" "${first_fd}" "${peak_fd}" "${final_fds}"
printf "    staged  final(after drain)=%s\n" "${final_staged}"

fail=0
if [ "${final_fds}" -gt $(( first_fd + 20 )) ]; then
    echo "    FAIL  file descriptors did not return to baseline after drain (leak suspected)"; fail=1
else
    echo "    PASS  file descriptors returned to baseline after drain"
fi
if [ "${final_staged}" -gt 0 ]; then
    echo "    FAIL  ${final_staged} staged temp files left behind after drain"; fail=1
else
    echo "    PASS  no staged temp files left behind"
fi
grow=$(awk "BEGIN{printf \"%d\", (${final_rss}-${first_rss})*100/(${first_rss}>0?${first_rss}:1)}")
if [ "${grow}" -gt 50 ] && [ $(( final_rss - first_rss )) -gt 51200 ]; then
    echo "    WARN  RSS grew ${grow}% over the soak (>50MB); inspect for a leak"
else
    echo "    PASS  RSS stayed within ${grow}% of the first sample"
fi

exit "${fail}"
