#!/bin/bash
cd /home/z/my-project
export DATABASE_URL="file:/home/z/my-project/db/custom.db"
export NODE_OPTIONS="--max-old-space-size=512"
while true; do
  echo "[$(date)] Starting server..."
  bun .next/standalone/server.js -H 0.0.0.0 2>&1 | tee -a dev.log
  echo "[$(date)] Server exited, restarting in 2s..."
  sleep 2
done
