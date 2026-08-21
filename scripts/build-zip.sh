#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"
OUT="$ROOT/dist/rezajordaan-installable.zip"

cleanup() {
	rm -rf "$BUILD_DIR"
}
trap cleanup EXIT

mkdir -p "$ROOT/dist" "$BUILD_DIR/rezajordaan"

if command -v rsync >/dev/null 2>&1; then
	rsync -a \
		--exclude '.git' \
		--exclude 'dist' \
		--exclude '.cursor' \
		--exclude 'node_modules' \
		--exclude 'scripts' \
		"$ROOT/" "$BUILD_DIR/rezajordaan/"
else
	# Fallback when rsync is unavailable (Cloud Agent images).
	find "$ROOT" -mindepth 1 -maxdepth 1 \
		! -name '.git' \
		! -name 'dist' \
		! -name '.cursor' \
		! -name 'node_modules' \
		! -name 'scripts' \
		-exec cp -a {} "$BUILD_DIR/rezajordaan/" \;
fi

rm -f "$OUT"
(cd "$BUILD_DIR" && zip -rq "$OUT" rezajordaan)

echo "Built $OUT ($(du -h "$OUT" | awk '{print $1}'))"
