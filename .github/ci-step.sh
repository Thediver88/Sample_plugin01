#!/usr/bin/env bash
# Run a CI step; on failure, publish the last lines of its output as a GitHub annotation so the
# failure can be read through the API (agents can't open Actions logs).
#   .github/ci-step.sh "<title>" <command...>
set -uo pipefail
title="$1"; shift
log=$(mktemp)
"$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ]; then
  tail_text=$(tail -n 60 "$log" | sed 's/\x1b\[[0-9;]*[A-Za-z]//g')
  tail_text=${tail_text//'%'/'%25'}
  tail_text=${tail_text//$'\r'/}
  tail_text=${tail_text//$'\n'/'%0A'}
  echo "::error title=${title} failed (exit ${status})::${tail_text}"
fi
exit "$status"
