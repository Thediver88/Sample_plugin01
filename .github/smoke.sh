#!/usr/bin/env bash
# Smoke-check the seeded test store before the browser tests and print what is needed to debug a failure.
set -uo pipefail
cli() { npx wp-env run tests-cli "$@" 2>/dev/null; }
echo "== theme: $(cli wp theme list --status=active --field=name)"
echo "== plugins:"; cli wp plugin list --status=active --fields=name,version
echo "== products:"; cli wp post list --post_type=product --fields=ID,post_name,post_status
url="http://localhost:8889/product/sample-tile/"
code=$(curl -s -o /tmp/p.html -w '%{http_code}' -L "$url")
echo "== GET $url -> $code ($(wc -c </tmp/p.html) bytes)"
if [ "$code" != "200" ]; then echo "== PHP debug log:"; cli bash -c 'tail -n 30 wp-content/debug.log 2>/dev/null'; exit 1; fi
echo "== product page loads"
