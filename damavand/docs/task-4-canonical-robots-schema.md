# Task 4 — Canonical / Robots / Schema ownership

## Verdict (do not blindly delete)

| Area | Decision |
|------|----------|
| **Canonical** | **PORT THEN DELETE** — single owner `SEO_Core_Canonical_Resolver` (booted from `SEO_Core_Canonical_Module`). Former `Damavand_Canonical` + `Shojaei_SEO_Canonical` removed. |
| **Robots** | **KEEP PARALLEL** — `SEO_Core_Robots_Module` = `robots.txt` only; HTML/`wp_robots` = `Damavand_Robots` via `Shojaei_SEO_General_Meta`; OOS noindex = `Shojaei_SEO_OOS_Manager`. |
| **Schema** | **KEEP PARALLEL** — emission = `Shojaei_SEO_Schema_Generator`; conflict scan = `Shojaei_SEO_Schema_Detector`; seo-core schema module = settings/gate adapter only. |

OWNER comments are on the file headers of the classes above.

## Baseline / diff snapshot

This Cloud Agent VM has **no live WordPress + WooCommerce front**. A CLI helper ships at:

`scripts/snapshot-seo-head.php`

On staging, before and after deploying this build:

```bash
php wp-content/plugins/damavand/scripts/snapshot-seo-head.php \
  --urls='https://…/product/a/,https://…/shop/page/2/,https://…/cart/,…' \
  --out=/tmp/damavand-seo-before.json
# deploy
php … --out=/tmp/damavand-seo-after.json
diff -u /tmp/damavand-seo-before.json /tmp/damavand-seo-after.json
```

Recommended URL set (10–15): product, variable+attribute query, variation permalink if used, shop, shop/page/2, product_cat, faceted `?orderby=`, cart, checkout, search, 404, author (if public), home.
