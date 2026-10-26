#!/bin/bash
# GasMonitor launcher. systemd ExecStart points here (Restart=always).
# Crash-loop guard: after an auto-update/env change the agent drops .update_pending.
# If the app then starts >3 times without being declared healthy, roll back.
cd "$(dirname "$(readlink -f "$0")")" || exit 1
BIN=./gasmonitor
if [ -f .update_pending ]; then
  n=$(cat .update_pending.attempts 2>/dev/null || echo 0)
  n=$((n+1)); echo "$n" > .update_pending.attempts
  if [ "$n" -gt 3 ]; then
    echo "[run.sh] crash loop after update (attempt $n) — rolling back" >&2
    get() { grep "^$1=" .update_pending | head -1 | cut -d= -f2-; }
    b=$(get binary); e=$(get env)
    [ "$b" = "1" ] && [ -f gasmonitor.bak ] && cp -f gasmonitor.bak gasmonitor && chmod +x gasmonitor
    [ "$e" = "1" ] && [ -f .env.bak ] && cp -f .env.bak .env
    cp -f .update_pending .rolled_back
    rm -f .update_pending .update_pending.attempts
  fi
fi
export GM_LAUNCHER=1
exec "$BIN" "$@"
