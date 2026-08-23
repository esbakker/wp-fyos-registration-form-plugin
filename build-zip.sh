#!/usr/bin/env bash
# =============================================================================
# build-zip.sh — Linux/macOS equivalent of build-zip.ps1
#
# Builds a WordPress-installable zip of the FOYS Registration Form plugin.
# Used by the GitHub Actions release workflow and can be run locally on Unix.
#
# WordPress extracts an uploaded zip into wp-content/plugins/<zip-filename>/,
# so the zip must contain the plugin files at its ROOT — no subdirectory inside.
# The zip is named after the plugin slug so WordPress installs to the right folder.
#
# Usage:
#   bash build-zip.sh [output-dir]
#
#   output-dir  Where to write the .zip. Defaults to the script directory.
# =============================================================================

set -euo pipefail

SLUG="fyos-registration-form"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Resolve output dir to an absolute path (create it if needed).
RAW_OUTPUT="${1:-$SCRIPT_DIR}"
mkdir -p "$RAW_OUTPUT"
OUTPUT_DIR="$(cd "$RAW_OUTPUT" && pwd)"

PLUGIN_FILE="$SCRIPT_DIR/$SLUG.php"
if [ ! -f "$PLUGIN_FILE" ]; then
  echo "Error: Main plugin file not found: $PLUGIN_FILE" >&2
  exit 1
fi

# Pull version from plugin header (portable sed, no -P flag needed).
VERSION=$(sed -n 's/.*\* Version:[[:space:]]*\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' \
              "$PLUGIN_FILE" | head -1)
[ -z "$VERSION" ] && VERSION="dev"

# Only these items are part of the distributable plugin.
INCLUDE=(
  "$SLUG.php"
  "includes"
  "assets"
  "README.md"
)

# -------------------------------------------------------------------
# Stage files in a temp directory so zip entries land at archive root
# (no subdirectory). WordPress uses the zip filename as the plugin
# folder, so the files must NOT be nested inside a subdirectory.
# -------------------------------------------------------------------
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

for item in "${INCLUDE[@]}"; do
  SRC="$SCRIPT_DIR/$item"
  if [ -e "$SRC" ]; then
    cp -r "$SRC" "$STAGE/"
  else
    echo "Warning: skipping missing item: $item" >&2
  fi
done

# Strip files that must never ship.
find "$STAGE" \( \
  -name '.DS_Store'     -o \
  -name 'Thumbs.db'     -o \
  -name '*.log'          \
\) -delete

# -------------------------------------------------------------------
# Build zip — cd into stage root so entries have no leading directory.
# The Unix zip tool always uses forward slashes, so no backslash issue.
# -------------------------------------------------------------------
ZIP_PATH="$OUTPUT_DIR/$SLUG.zip"
[ -f "$ZIP_PATH" ] && rm -f "$ZIP_PATH"

(cd "$STAGE" && zip -r9 "$ZIP_PATH" .)

echo ""
echo "Plugin version : $VERSION"
echo "Created        : $ZIP_PATH"
echo "WP installs to : wp-content/plugins/$SLUG/"
echo ""
echo "Zip contents (files at root, no subdirectory):"
unzip -l "$ZIP_PATH"
echo ""
echo "Upload via WordPress: Plugins → Add New → Upload Plugin → choose this zip."
