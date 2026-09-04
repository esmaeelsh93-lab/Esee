#!/usr/bin/env bash
# بررسی همگام بودن نسخه هدر پلاگین، ثابت DAMAVAND_SEO_VERSION و Stable tag در readme.txt
# قبل از build/zip اجرا شود؛ در صورت ناهماهنگی با کد غیرصفر خارج می‌شود.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/shojaei-seo-for-woo.php"
README="$ROOT/readme.txt"

if [[ ! -f "$PLUGIN" ]]; then
	echo "error: plugin file missing: $PLUGIN" >&2
	exit 1
fi
if [[ ! -f "$README" ]]; then
	echo "error: readme missing: $README" >&2
	exit 1
fi

header_ver="$(
	grep -E '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*' "$PLUGIN" \
		| head -n1 \
		| sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' \
		| tr -d '\r' \
		| awk '{print $1}'
)"
const_ver="$(
	grep -E "define\(\s*'DAMAVAND_SEO_VERSION'" "$PLUGIN" \
		| head -n1 \
		| sed -E "s/.*DAMAVAND_SEO_VERSION'\s*,\s*'([^']+)'.*/\1/" \
		| tr -d '\r'
)"
stable_ver="$(
	grep -E '^Stable tag:[[:space:]]*' "$README" \
		| head -n1 \
		| sed -E 's/^Stable tag:[[:space:]]*//' \
		| tr -d '\r' \
		| awk '{print $1}'
)"

fail=0
if [[ -z "$header_ver" || -z "$const_ver" || -z "$stable_ver" ]]; then
	echo "error: could not parse one or more version fields" >&2
	echo "  header Version:        '${header_ver:-}'" >&2
	echo "  DAMAVAND_SEO_VERSION:  '${const_ver:-}'" >&2
	echo "  readme Stable tag:     '${stable_ver:-}'" >&2
	exit 1
fi

echo "Plugin header Version:       $header_ver"
echo "DAMAVAND_SEO_VERSION const:  $const_ver"
echo "readme.txt Stable tag:       $stable_ver"

if [[ "$header_ver" != "$const_ver" ]]; then
	echo "error: header Version ($header_ver) != DAMAVAND_SEO_VERSION ($const_ver)" >&2
	fail=1
fi
if [[ "$header_ver" != "$stable_ver" ]]; then
	echo "error: header Version ($header_ver) != readme Stable tag ($stable_ver)" >&2
	fail=1
fi

if [[ "$fail" -ne 0 ]]; then
	exit 1
fi

echo "OK: versions are in sync ($header_ver)"
exit 0
