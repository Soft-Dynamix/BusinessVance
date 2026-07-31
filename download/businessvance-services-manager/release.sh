#!/bin/bash
# release.sh — Bump version, update changelog, build ZIP, git tag
#
# Usage:
#   ./release.sh 2.0.1 "Added settings page"
#   ./release.sh 2.1.0 "Major feature"
#   ./release.sh 2.1.0 ""          # skip changelog message

set -e

VERSION="${1:?Usage: release.sh VERSION [DESCRIPTION]}"
DESC="${2:-}"

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_FILE="$PLUGIN_DIR/businessvance-services-manager.php"
CHANGELOG="$PLUGIN_DIR/CHANGELOG.md"
ZIP_OUTPUT="/home/z/my-project/public/businessvance-services-manager-v${VERSION}.zip"
DATE=$(date +%Y-%m-%d)

# ── Validate version format (x.y.z)
if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "❌ Version must be in x.y.z format (e.g. 2.0.1)"
    exit 1
fi

# ── Check for uncommitted changes
if ! git -C "$PLUGIN_DIR" diff --quiet HEAD; then
    echo "❌ You have uncommitted changes. Commit first:"
    git -C "$PLUGIN_DIR" status --short
    exit 1
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  BusinessVance Release Builder"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  Version : $VERSION"
echo "  Date    : $DATE"
echo "  Dir     : $PLUGIN_DIR"
echo ""

# ── Step 1: Update version in plugin file
echo "📝 Updating version in plugin header..."
sed -i "s/^ \* Version: .*/ * Version: $VERSION/" "$PLUGIN_FILE"
sed -i "s/^define( 'BV_VERSION', '.*' );/define( 'BV_VERSION', '$VERSION' );/" "$PLUGIN_FILE"

# ── Step 2: Prepend to CHANGELOG
echo "📝 Updating CHANGELOG.md..."
if [ -n "$DESC" ]; then
    # Collect staged changes since last tag
    LAST_TAG=$(git -C "$PLUGIN_DIR" describe --tags --abbrev=0 2>/dev/null || echo "")

    TEMP_HEADER=$(mktemp)
    cat > "$TEMP_HEADER" << HEADER

## [$VERSION] — $DATE

### Changed
- $DESC
HEADER
    # Insert after the first --- line in CHANGELOG
    awk -v insert="$(cat "$TEMP_HEADER")" '
        NR==4{print insert; print ""}
        {print}
    ' "$CHANGELOG" > "$CHANGELOG.tmp" && mv "$CHANGELOG.tmp" "$CHANGELOG"
    rm "$TEMP_HEADER"
else
    echo "  (no changelog description provided — update CHANGELOG.md manually)"
fi

# ── Step 3: Git add, commit, tag
echo "📦 Committing version bump..."
git -C "$PLUGIN_DIR" add -A
git -C "$PLUGIN_DIR" commit -m "v$VERSION — Bump version"

echo "🏷️  Tagging v$VERSION..."
git -C "$PLUGIN_DIR" tag -a "v$VERSION" -m "v$VERSION"

# ── Step 4: Build ZIP (excluding .git)
echo "📦 Building ZIP..."
cd "$PLUGIN_DIR/.."
rm -f "$ZIP_OUTPUT"
zip -r "$ZIP_OUTPUT" businessvance-services-manager/ \
    -x "businessvance-services-manager/.git/*" \
    -x "businessvance-services-manager/.gitignore" \
    -x "businessvance-services-manager/release.sh" \
    -x "businessvance-services-manager/.DS_Store"

SIZE=$(du -h "$ZIP_OUTPUT" | cut -f1)
FILES=$(zipinfo -1 "$ZIP_OUTPUT" | wc -l)

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ Release v$VERSION complete!"
echo ""
echo "  ZIP:  $ZIP_OUTPUT"
echo "  Size: $SIZE"
echo "  Files: $FILES"
echo ""
echo "  Git tag: v$VERSION"
echo ""
echo "  To rollback:  git tag -d v$VERSION && git reset --soft HEAD~1"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
