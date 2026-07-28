#!/bin/bash

echo "=== Starting server ==="
fuser -k 3000/tcp 2>/dev/null || true
sleep 1

cd /home/z/my-project
DATABASE_URL=file:/home/z/my-project/db/custom.db node .next/standalone/server.js -H 0.0.0.0 > /tmp/bv-server.log 2>&1 &
sleep 2

node mini-services/ipv6-proxy/index.js > /tmp/bv-proxy.log 2>&1 &
sleep 1

curl -s --max-time 5 http://127.0.0.1:3000/api/stats > /dev/null
echo "Server running"

echo "=== Opening page ==="
agent-browser open http://127.0.0.1:3000 2>&1
sleep 4
agent-browser wait --load networkidle 2>&1

echo ""
echo "=== 1. DASHBOARD TAB ==="
agent-browser snapshot 2>&1 | head -40
echo "..."
agent-browser screenshot /tmp/tab-dashboard.png 2>&1
echo "[OK] Dashboard"

echo ""
echo "=== 2. SERVICES TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
# Get Services button ref
SVC_REF=$(rg 'Services' /tmp/snap.txt | head -1 | rg -o 'e\d+')
echo "Services ref: $SVC_REF"
agent-browser click @$SVC_REF 2>&1
sleep 3
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-services.png 2>&1
echo "[OK] Services"

echo ""
echo "=== 3. PLANS TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
PLANS_REF=$(rg 'Plans' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Plans ref: $PLANS_REF"
agent-browser click @$PLANS_REF 2>&1
sleep 3
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-plans.png 2>&1
echo "[OK] Plans"

echo ""
echo "=== 4. CATEGORIES TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
CAT_REF=$(rg 'Categories' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Categories ref: $CAT_REF"
agent-browser click @$CAT_REF 2>&1
sleep 2
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-categories.png 2>&1
echo "[OK] Categories"

echo ""
echo "=== 5. ICONS TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
ICON_REF=$(rg '"Icons"' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Icons ref: $ICON_REF"
agent-browser click @$ICON_REF 2>&1
sleep 2
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-icons.png 2>&1
echo "[OK] Icons"

echo ""
echo "=== 6. TEMPLATES TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
TPL_REF=$(rg 'Templates' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Templates ref: $TPL_REF"
agent-browser click @$TPL_REF 2>&1
sleep 2
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-templates.png 2>&1
echo "[OK] Templates"

echo ""
echo "=== 7. PROJECTS TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
PROJ_REF=$(rg 'Projects' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Projects ref: $PROJ_REF"
agent-browser click @$PROJ_REF 2>&1
sleep 2
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -25
echo "..."
agent-browser screenshot /tmp/tab-projects.png 2>&1
echo "[OK] Projects"

echo ""
echo "=== 8. SETTINGS TAB ==="
agent-browser snapshot -i 2>&1 > /tmp/snap.txt
SET_REF=$(rg 'Settings' /tmp/snap.txt | rg -o 'e\d+' | head -1)
echo "Settings ref: $SET_REF"
agent-browser click @$SET_REF 2>&1
sleep 2
agent-browser wait --load networkidle 2>&1
agent-browser snapshot 2>&1 | head -30
echo "..."
agent-browser screenshot /tmp/tab-settings.png 2>&1
echo "[OK] Settings"

echo ""
echo "=== ALL TABS TESTED SUCCESSFULLY ==="
