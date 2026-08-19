#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"
OUT="$ROOT/dist/rezajordaan-commerce-installable.zip"

mkdir -p "$ROOT/dist"
rsync -a \
	--exclude '.DS_Store' \
	"$ROOT/plugin/rezajordaan-commerce/" "$BUILD_DIR/rezajordaan-commerce/"

rm -f "$OUT"
(cd "$BUILD_DIR" && zip -rq "$OUT" rezajordaan-commerce)

echo "Built $OUT ($(du -h "$OUT" | awk '{print $1}'))"
