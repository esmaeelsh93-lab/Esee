#!/usr/bin/env bash
# ساخت زیپ نصب افزونه بعد از عبور از check-version-sync.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

bash "$ROOT/scripts/check-version-sync.sh"

ver="$(
	grep -E '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*' "$ROOT/shojaei-seo-for-woo.php" \
		| head -n1 \
		| sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' \
		| tr -d '\r' \
		| awk '{print $1}'
)"
out_dir="${DAMAVAND_ZIP_OUT:-$ROOT/../artifacts}"
mkdir -p "$out_dir"
out_zip="$out_dir/damavand-seo-${ver}.zip"

# پوشه والد workspace تا زیپ با ریشه damavand/ ساخته شود.
parent="$(dirname "$ROOT")"
base="$(basename "$ROOT")"
rm -f "$out_zip"
(
	cd "$parent"
	zip -r "$out_zip" "$base" \
		-x "${base}/.git/*" \
		-x "${base}/**/.DS_Store" \
		-x "${base}/vendor/*" \
		-x "${base}/node_modules/*" \
		>/dev/null
)

echo "Built: $out_zip"
ls -lh "$out_zip"
