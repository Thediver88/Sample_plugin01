#!/usr/bin/env bash
# Build the WordPress.org release ZIP from a committed tree and check it before hand-off.
#   bin/build-zip.sh            → dist/sample-shelf-for-woocommerce-<version>.zip
#   bin/build-zip.sh <git-ref>  → build from a tag/commit
set -euo pipefail

SLUG="sample-shelf-for-woocommerce"
REF="${1:-HEAD}"
cd "$(git rev-parse --show-toplevel)"

if [ "$REF" = "HEAD" ] && [ -n "$(git status --porcelain -- . ':!dist')" ]; then
  echo "✗ Uncommitted changes. Commit first so the ZIP matches a known commit." >&2
  exit 1
fi

file_at() { git show "$REF:$1"; }

header_version=$(file_at "$SLUG.php" | sed -n 's/^ \* Version:[[:space:]]*//p' | tr -d '\r')
const_version=$(file_at "$SLUG.php" | sed -n "s/^define( 'SMPSHELF_VERSION', '\(.*\)' );/\1/p")
stable_tag=$(file_at readme.txt | sed -n 's/^Stable tag:[[:space:]]*//p' | tr -d '\r')
text_domain=$(file_at "$SLUG.php" | sed -n 's/^ \* Text Domain:[[:space:]]*//p' | tr -d '\r')

fail=0
check() { if [ "$2" != "$3" ]; then echo "✗ $1: '$2' ≠ '$3'"; fail=1; else echo "✓ $1: $2"; fi; }
check "Version header = SMPSHELF_VERSION" "$header_version" "$const_version"
check "Version header = readme Stable tag" "$header_version" "$stable_tag"
check "Text Domain = slug" "$text_domain" "$SLUG"
file_at readme.txt | grep -q "^= $header_version" || { echo "✗ readme.txt changelog has no '= $header_version' entry"; fail=1; }
[ "$fail" = 0 ] || exit 1

mkdir -p dist
out="dist/$SLUG-$header_version.zip"
rm -f "$out"
git archive --format=zip --prefix="$SLUG/" -o "$out" "$REF"

# Nothing from the dev workflow may ship.
forbidden='/(tests|bin|\.github|\.claude|node_modules|vendor|dist|\.wordpress-org)/|/(CLAUDE\.md|composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml\.dist|phpcs\.xml\.dist|playwright\.config\.js|\.wp-env\.json|README\.md|\.git[a-z]*)$'
if unzip -Z1 "$out" | grep -E "$forbidden"; then
  echo "✗ Dev files found in the ZIP (above). Add them to .gitattributes export-ignore." >&2
  exit 1
fi

echo "✓ $(unzip -Z1 "$out" | grep -vc '/$') files"
unzip -Z1 "$out" | sed 's/^/   /'
echo "✓ Built $out ($(du -h "$out" | cut -f1)) from $(git rev-parse --short "$REF")"
