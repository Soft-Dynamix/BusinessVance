#!/bin/bash
cd /home/z/my-project
export DATABASE_URL="file:/home/z/my-project/db/custom.db"
export NODE_OPTIONS="--max-old-space-size=256"
while true; do
  if ! ss -tlnp 2>/dev/null | grep -q ":3000 "; then
    echo "[$(date +%H:%M:%S)] Server not running, starting..."
    node --max-old-space-size=256 .next/standalone/server.js -H 0.0.0.0 &
    sleep 3
  fi
  sleep 2
done
