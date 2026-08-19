#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"
OUT="$ROOT/dist/rezajordaan-installable.zip"

mkdir -p "$ROOT/dist"
rsync -a \
	--exclude '.git' \
	--exclude 'dist' \
	--exclude '.cursor' \
	--exclude 'node_modules' \
	--exclude 'scripts' \
	--exclude 'plugin' \
	"$ROOT/" "$BUILD_DIR/rezajordaan/"

rm -f "$OUT"
(cd "$BUILD_DIR" && zip -rq "$OUT" rezajordaan)

echo "Built $OUT ($(du -h "$OUT" | awk '{print $1}'))"
