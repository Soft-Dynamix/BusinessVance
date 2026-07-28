#!/bin/bash
cd /home/z/my-project
export DATABASE_URL="file:/home/z/my-project/db/custom.db"
while true; do
  if ! ss -tlnp 2>/dev/null | grep -q ":3000 "; then
    echo "[$(date +%H:%M:%S)] Server not running, starting..." >> /tmp/watchdog.log
    node .next/standalone/server.js -H 0.0.0.0 >> /tmp/server.log 2>&1 &
    sleep 3
  fi
  sleep 2
done
