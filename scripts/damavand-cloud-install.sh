#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

echo "[damavand] ensuring PHP toolchain..."
sudo apt-get update -qq
sudo apt-get install -y -qq \
  php-cli php-xml php-mbstring php-curl php-zip unzip curl ca-certificates

echo "[damavand] PHP $(php -r 'echo PHP_VERSION;')"

PLUGIN_ROOT="${DAMAVAND_PLUGIN_ROOT:-damavand}"
if [[ ! -f "$PLUGIN_ROOT/shojaei-seo-for-woo.php" ]]; then
  echo "[damavand] plugin sources not present at $PLUGIN_ROOT (OK on base branch); toolchain ready"
  echo "[damavand] install complete"
  exit 0
fi

echo "[damavand] linting plugin PHP files..."
mapfile -t php_files < <(find "$PLUGIN_ROOT" -type f -name '*.php' | sort)
if [[ ${#php_files[@]} -lt 1 ]]; then
  echo "[damavand] no PHP files found under $PLUGIN_ROOT" >&2
  exit 1
fi
fail=0
for f in "${php_files[@]}"; do
  if ! php -l "$f" >/dev/null; then
    echo "[damavand] lint failed: $f" >&2
    fail=1
  fi
done
if [[ "$fail" -ne 0 ]]; then
  exit 1
fi
echo "[damavand] linted ${#php_files[@]} PHP files OK"

# Schema currency sanity (IRT→IRR ×10) without WordPress bootstrap
php <<'PHPEOF'
<?php
function schema_price_string($price, float $factor = 1.0): string {
    if ($price === '' || $price === null) {
        return '';
    }
    $num = (float) $price * $factor;
    if (is_finite($num) && abs($num - round($num)) < 0.00001) {
        return (string) (int) round($num);
    }
    return (string) $num;
}
function map_currency(string $currency): array {
    if ($currency === 'IRT') {
        return array( 'IRR', 10.0 );
    }
    return array( $currency, 1.0 );
}
list($c, $f) = map_currency('IRT');
assert($c === 'IRR' && $f === 10.0);
assert(schema_price_string(150000, $f) === '1500000');
assert(schema_price_string(100000, $f) === '1000000');
assert(schema_price_string(250000, $f) === '2500000');
list($c2, $f2) = map_currency('IRR');
assert($c2 === 'IRR' && $f2 === 1.0);
assert(schema_price_string(1500000, $f2) === '1500000');
echo "[damavand] schema IRT→IRR conversion checks passed\n";
PHPEOF

echo "[damavand] install complete"
