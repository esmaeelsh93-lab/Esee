=== Esee Automatic WebP ===
Contributors: esee
Tags: webp, image optimization, media, uploads, heic
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically converts images uploaded through the WordPress media pipeline to high-quality WebP.

== Description ==

Esee Automatic WebP converts JPEG, PNG, BMP, AVIF, and supported HEIC/HEIF uploads to WebP before WordPress creates the attachment.

Because conversion runs on the standard `wp_handle_upload` filter, it works with Gutenberg, the Classic Editor, WooCommerce, and page builders that upload through the WordPress Media Library.

Important behavior:

* Quality defaults to 88 and can be changed from Settings > Media.
* Animated GIF files are not converted, so their animation is preserved.
* Existing WebP images are left untouched.
* HEIC and HEIF input requires an image editor installed on the server that can decode those formats. The plugin safely keeps the original upload if decoding is unavailable.
* Ghostscript is not required. Ghostscript is used by WordPress for PDF previews, not for these image conversions.
* By default, the source file is removed after successful conversion. It can optionally be retained from Settings > Media.

== Installation ==

1. Upload the `esee-webp-converter` folder to `/wp-content/plugins/`, or install the supplied ZIP from Plugins > Add New > Upload Plugin.
2. Activate Esee Automatic WebP.
3. Optionally adjust quality and original-file retention under Settings > Media.
4. Upload a new image through the Media Library.

== Frequently Asked Questions ==

= Does it convert existing Media Library images? =

No. Version 1.0 converts new uploads only, avoiding unexpected URL changes for existing content.

= Does it work with every page builder? =

It works with any editor or page builder that uses WordPress' standard upload API. A tool that writes files directly into the uploads directory bypasses all WordPress upload filters and cannot be intercepted by a normal plugin hook.

= Why was my HEIC file not converted? =

GD does not normally decode HEIC/HEIF. The server needs an HEIC-capable Imagick/ImageMagick installation (or another WordPress image editor) for those inputs. The upload is kept unchanged if support is unavailable.

= Is Ghostscript required? =

No. It is unrelated to JPEG, PNG, BMP, AVIF, HEIC/HEIF, or WebP conversion in this plugin.

== Changelog ==

= 1.0.0 =

* Initial release.
* Automatic high-quality WebP conversion for new uploads.
* HEIC/HEIF JPEG fallback mappings.
* Media settings for quality and original retention.
