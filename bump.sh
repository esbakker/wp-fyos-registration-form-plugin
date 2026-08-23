#!/usr/bin/env bash
# =============================================================================
# bump.sh — raise the plugin version.
#
# The version in the plugin header is the source of truth for releases: pushing
# a commit that carries a new version publishes it. This script bumps the two
# places that version lives, so they can never drift apart.
#
# Usage:
#   bash bump.sh [patch|minor|major]     # default: patch
#   bash bump.sh 2.1.0                   # or set it explicitly
# =============================================================================

set -euo pipefail

SLUG="fyos-registration-form"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FILE="$SCRIPT_DIR/$SLUG.php"

[ -f "$FILE" ] || { echo "Error: $FILE not found" >&2; exit 1; }

CURRENT=$(sed -n 's/.*\* Version:[[:space:]]*\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' \
              "$FILE" | head -1)
[ -n "$CURRENT" ] || { echo "Error: could not read the current version" >&2; exit 1; }

ARG="${1:-patch}"

if printf '%s' "$ARG" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
  NEW="$ARG"
else
  IFS='.' read -ra V <<< "$CURRENT"
  MAJOR="${V[0]}"; MINOR="${V[1]}"; PATCH="${V[2]}"

  case "$ARG" in
    major) NEW="$((MAJOR + 1)).0.0" ;;
    minor) NEW="$MAJOR.$((MINOR + 1)).0" ;;
    patch) NEW="$MAJOR.$MINOR.$((PATCH + 1))" ;;
    *)     echo "Usage: bash bump.sh [patch|minor|major|X.Y.Z]" >&2; exit 1 ;;
  esac
fi

# "* Version:           x.y.z" — preserve the whitespace alignment.
sed -i.bak -E "s/(\* Version:[[:space:]]+)[0-9]+\.[0-9]+\.[0-9]+/\1$NEW/" "$FILE"
sed -i.bak "s/define( 'FRF_VERSION', '[^']*' );/define( 'FRF_VERSION', '$NEW' );/" "$FILE"
rm -f "$FILE.bak"

echo "$CURRENT → $NEW"
grep -E "\* Version:|FRF_VERSION" "$FILE"
echo ""
echo "Commit this with your change; the push publishes v$NEW."
