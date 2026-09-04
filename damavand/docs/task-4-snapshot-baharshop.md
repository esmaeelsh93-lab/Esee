# Snapshot — baharshop.ir (2026-09-04)

Source: live site `https://baharshop.ir` (no separate staging host responded; this is the shop used for Damavand work).

Artifact: `artifacts/baharshop-seo-head-snapshot.json` + `artifacts/baharshop-robots.txt`  
Tool: `scripts/snapshot-seo-head.php`

## robots.txt

```
User-agent: *
Disallow: /wp-content/uploads/wc-logs/
Disallow: /wp-content/uploads/woocommerce_transient_files/
Disallow: /wp-content/uploads/woocommerce_uploads/
Disallow: /*?add-to-cart=
Disallow: /*?*add-to-cart=
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: https://baharshop.ir/wp-sitemap.xml
```

(Core WP sitemap — Damavand SEO Core robots/sitemap layer not visible as owner here.)

## Head snapshot (13 URLs)

| HTTP | URL type | canonical | robots | JSON-LD |
|------|----------|-----------|--------|---------|
| 200 | home | — | max-image-preview:large | 0 |
| 200 | products archive | — | max-image-preview:large | BreadcrumbList |
| 200 | products/page/2 | — | max-image-preview:large | BreadcrumbList |
| 200 | product_cat | — | max-image-preview:large | BreadcrumbList |
| 200 | product ×2 | self URL | max-image-preview:large | BreadcrumbList + Product |
| 200 | cat?orderby=price | — | max-image-preview:large | BreadcrumbList |
| 500 | cart / checkout | (empty body) | — | — |
| 200 | search | — | **noindex, follow** | BreadcrumbList |
| 404 | missing URL / author | — | max-image-preview:large | 0 |
| 200 | product?attribute_… | parent product URL | max-image-preview:large | BreadcrumbList + Product |

## Notes for Damavand deploy

- No `damavand` / Rank Math / Yoast markers in HTML sampled — current emission looks theme/Woo + WP defaults.
- Search already `noindex,follow` (good). Cart/checkout returned **HTTP 500** to the snapshot UA (site issue, not Damavand).
- After Damavand primary mode: re-run the same URL list and diff JSON (expect crawl-budget noindex on cart/checkout/404/author when those URLs respond, shop pagination self-canonical, richer Product `@graph`).
