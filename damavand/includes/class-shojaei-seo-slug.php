<?php
/**
 * Product slug automation: Finglish, readability, 301 on rename.
 *
 * Facade / BC surface — implementation lives in Damavand_Slug_*.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Slug
 */
class Shojaei_SEO_Slug {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Always serve stored 301s — must not depend on the metabox/UI toggle.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_old_slug' ), 0 );
		// These handlers no-op via their own options when disabled.
		add_filter( 'wp_insert_post_data', array( $this, 'maybe_transliterate_new_slug' ), 20, 2 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 20, 3 );

		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_tools_enabled', 'yes' ) ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_shojaei_seo_slug_live', array( $this, 'ajax_live_preview' ) );
	}

	/**
	 * Post types that get Finglish slug tools + 301 on rename.
	 *
	 * @return string[]
	 */
	public static function supported_post_types(): array {
		return Damavand_Slug_Finglish::supported_post_types();
	}

	/**
	 * Whether a post type is supported.
	 *
	 * @param string $post_type Type.
	 */
	public static function is_supported_type( string $post_type ): bool {
		return Damavand_Slug_Finglish::is_supported_type( $post_type );
	}

	/**
	 * Slug redirects table.
	 */
	public static function table(): string {
		return Damavand_Slug_Redirects::table();
	}

	/**
	 * Persian/Arabic → Finglish map (stable, offline).
	 *
	 * @return array<string,string>
	 */
	public static function char_map(): array {
		return Damavand_Slug_Finglish::char_map();
	}

	/**
	 * Built-in Persian → Latin word dictionary (fashion / shop).
	 *
	 * @return array<string,string>
	 */
	public static function builtin_word_map(): array {
		return Damavand_Slug_Finglish::builtin_word_map();
	}

	/**
	 * Option key for custom Finglish dictionary.
	 */
	public static function dictionary_option_key(): string {
		return Damavand_Slug_Finglish::dictionary_option_key();
	}

	/**
	 * Custom dictionary from settings (overrides built-in on same key).
	 *
	 * @return array<string,string>
	 */
	public static function custom_word_map(): array {
		return Damavand_Slug_Finglish::custom_word_map();
	}

	/**
	 * Merged map: built-in + custom (custom wins).
	 *
	 * @return array<string,string>
	 */
	public static function word_map(): array {
		return Damavand_Slug_Finglish::word_map();
	}

	/**
	 * Overlay pairs for a single preview request.
	 *
	 * @param array<string,string> $map Overlay.
	 */
	public static function set_preview_overlay( array $map ): void {
		Damavand_Slug_Finglish::set_preview_overlay( $map );
	}

	/**
	 * Drop dictionary cache (after custom dict save).
	 */
	public static function clear_word_map_cache(): void {
		Damavand_Slug_Finglish::clear_word_map_cache();
	}

	/**
	 * Normalize Persian text for matching (ی/ک، حذف نیم‌فاصله).
	 *
	 * @param string $text Raw.
	 */
	public static function normalize_fa( string $text ): string {
		return Damavand_Slug_Finglish::normalize_fa( $text );
	}

	/**
	 * Dictionary keys normalized, longest-match-first (O(n·k) with k small).
	 *
	 * @return array<string,string>
	 */
	public static function sorted_word_map(): array {
		return Damavand_Slug_Finglish::sorted_word_map();
	}

	/**
	 * Persian + Latin stopwords removed from final slug tokens.
	 *
	 * @return array<string,true>
	 */
	public static function slug_stopwords(): array {
		return Damavand_Slug_Finglish::slug_stopwords();
	}

	/**
	 * Remove stopword tokens; never empty the slug completely.
	 *
	 * @param string $slug Latin slug.
	 */
	public static function strip_slug_stopwords( string $slug ): string {
		return Damavand_Slug_Finglish::strip_slug_stopwords( $slug );
	}

	/**
	 * Ensure slug is unique. Prefer distinctive product tokens (color/brand/sku/model)
	 * before falling back to WordPress numeric suffix (-2, -3…).
	 *
	 * @param string $slug      Desired slug.
	 * @param int    $post_id   Current post.
	 * @param string $post_type Type.
	 * @param string $status    Status.
	 * @param int    $parent    Parent.
	 */
	public static function uniquify_slug( string $slug, int $post_id = 0, string $post_type = 'product', string $status = 'publish', int $parent = 0 ): string {
		return Damavand_Slug_Finglish::uniquify_slug( $slug, $post_id, $post_type, $status, $parent );
	}

	/**
	 * Append short distinctive tokens from product data when base slug already exists.
	 *
	 * @param string $base      Base Latin slug.
	 * @param int    $product_id Product ID.
	 */
	public static function enrich_slug_with_discriminators( string $base, int $product_id ): string {
		return Damavand_Slug_Finglish::enrich_slug_with_discriminators( $base, $product_id );
	}

	/**
	 * Normalize Persian/key side of a dictionary entry.
	 *
	 * @param string $key Raw key.
	 */
	public static function normalize_dict_key( string $key ): string {
		return Damavand_Slug_Finglish::normalize_dict_key( $key );
	}

	/**
	 * Normalize Latin/value side → slug-safe token.
	 *
	 * @param string $value Raw value.
	 */
	public static function normalize_dict_value( string $value ): string {
		return Damavand_Slug_Finglish::normalize_dict_value( $value );
	}

	/**
	 * Parse textarea lines: «فارسی = latin» or «فارسی => latin» or «فارسی: latin».
	 * Lines starting with # are comments. Max 500 entries.
	 *
	 * @param string $text Raw textarea.
	 * @return array<string,string>
	 */
	public static function parse_dictionary_text( string $text ): array {
		return Damavand_Slug_Finglish::parse_dictionary_text( $text );
	}

	/**
	 * Format stored map for textarea editor.
	 *
	 * @param array<string,string>|null $map Map or null to load custom.
	 */
	public static function format_dictionary_text( ?array $map = null ): string {
		return Damavand_Slug_Finglish::format_dictionary_text( $map );
	}

	/**
	 * Sanitize and persist custom dictionary from textarea POST.
	 *
	 * @param string $text Raw textarea.
	 * @return array<string,string> Saved map.
	 */
	public static function save_custom_dictionary_from_text( string $text ): array {
		return Damavand_Slug_Finglish::save_custom_dictionary_from_text( $text );
	}

	/**
	 * Add / update one custom dictionary pair.
	 *
	 * @param string $fa Persian word/phrase.
	 * @param string $en Latin slug token.
	 * @return array{ok:bool,message:string,map?:array<string,string>,preview?:string}
	 */
	public static function upsert_dictionary_entry( string $fa, string $en ): array {
		return Damavand_Slug_Finglish::upsert_dictionary_entry( $fa, $en );
	}

	/**
	 * Remove one custom dictionary key.
	 *
	 * @param string $fa Key.
	 */
	public static function delete_dictionary_entry( string $fa ): bool {
		return Damavand_Slug_Finglish::delete_dictionary_entry( $fa );
	}

	/**
	 * Transliterate title/text to Latin slug (Finglish) — offline, longest-match-first.
	 *
	 * @param string $text Raw title (FA/AR/Latin/digits mix OK).
	 */
	public static function transliterate( string $text ): string {
		return Damavand_Slug_Finglish::transliterate( $text );
	}

	/**
	 * Whether string contains Persian/Arabic letters (raw or percent-encoded).
	 *
	 * @param string $text Text.
	 */
	public static function has_persian( string $text ): bool {
		return Damavand_Slug_Finglish::has_persian( $text );
	}

	/**
	 * True when slug is already clean Latin (a-z0-9- only, no Persian).
	 *
	 * @param string $slug Slug.
	 */
	public static function is_clean_latin_slug( string $slug ): bool {
		return Damavand_Slug_Finglish::is_clean_latin_slug( $slug );
	}

	/**
	 * Readability score 0–100 for a slug.
	 *
	 * @param string $slug Slug.
	 * @return array{score:int,tips:string[]}
	 */
	public static function score_slug( string $slug ): array {
		return Damavand_Slug_Finglish::score_slug( $slug );
	}

	/**
	 * Auto-Finglish for new products (or Persian slug).
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw.
	 * @return array
	 */
	public function maybe_transliterate_new_slug( array $data, array $postarr ): array {
		return ( new Damavand_Slug_Editor() )->maybe_transliterate_new_slug( $data, $postarr );
	}

	/**
	 * When published post/product slug changes → store 301 + activity log.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_post_updated( int $post_id, $post_after, $post_before ): void {
		( new Damavand_Slug_Redirects() )->on_post_updated( $post_id, $post_after, $post_before );
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_product_updated( int $post_id, $post_after, $post_before ): void {
		( new Damavand_Slug_Redirects() )->on_product_updated( $post_id, $post_after, $post_before );
	}

	/**
	 * Replace trailing slug in product URL.
	 *
	 * @param string $url      New URL.
	 * @param string $new_slug New slug.
	 * @param string $old_slug Old slug.
	 */
	public static function swap_slug_in_url( string $url, string $new_slug, string $old_slug ): string {
		return Damavand_Slug_Redirects::swap_slug_in_url( $url, $new_slug, $old_slug );
	}

	/**
	 * Encoded + decoded forms of a post_name (WP stores Persian as %d8%aa…).
	 *
	 * @param string $slug Slug.
	 * @return string[]
	 */
	public static function slug_variants( string $slug ): array {
		return Damavand_Slug_Redirects::slug_variants( $slug );
	}

	/**
	 * Persist slug redirect row.
	 *
	 * @param int    $product_id Product.
	 * @param string $old_slug   Old slug.
	 * @param string $old_url    Old URL.
	 * @param string $new_url    New URL.
	 * @param string $type       301/302.
	 */
	public static function save_redirect( int $product_id, string $old_slug, string $old_url, string $new_url, string $type = '301' ): int {
		return Damavand_Slug_Redirects::save_redirect( $product_id, $old_slug, $old_url, $new_url, $type );
	}

	/**
	 * Normalize path key for lookup (lowercase, no trailing slash).
	 *
	 * @param string $url URL.
	 */
	public static function path_key( string $url ): string {
		return Damavand_Slug_Redirects::path_key( $url );
	}

	/**
	 * Build path keys to match stored redirects (encoded + decoded Persian).
	 *
	 * @param string $req_path Request path.
	 * @return string[]
	 */
	public static function path_lookup_candidates( string $req_path ): array {
		return Damavand_Slug_Redirects::path_lookup_candidates( $req_path );
	}

	/**
	 * On 404, apply stored slug redirect.
	 */
	public function maybe_redirect_old_slug(): void {
		( new Damavand_Slug_Redirects() )->maybe_redirect_old_slug();
	}

	/**
	 * Product editor metabox.
	 */
	public function register_metabox(): void {
		( new Damavand_Slug_Editor() )->register_metabox();
	}

	/**
	 * Live preview payload for product editor (title + current slug).
	 *
	 * @param string $title Title.
	 * @param string $slug  Current slug (may be empty).
	 * @return array{score:int,tone:string,tips:string[],suggest:string,slug:string,based_on:string}
	 */
	public static function live_preview( string $title, string $slug = '', int $post_id = 0 ): array {
		return Damavand_Slug_Editor::live_preview( $title, $slug, $post_id );
	}

	/**
	 * AJAX: live slug score + Finglish suggestion while editing product.
	 */
	public function ajax_live_preview(): void {
		( new Damavand_Slug_Editor() )->ajax_live_preview();
	}

	/**
	 * Metabox UI.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_metabox( $post ): void {
		( new Damavand_Slug_Editor() )->render_metabox( $post );
	}

	/**
	 * List slug redirects for admin UI.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function list_redirects( int $limit = 100 ): array {
		return Damavand_Slug_Redirects::list_redirects( $limit );
	}

	/**
	 * Count active slug redirects.
	 */
	public static function count_active_redirects(): int {
		return Damavand_Slug_Redirects::count_active_redirects();
	}

	/**
	 * Toggle redirect active flag.
	 *
	 * @param int $id     Row ID.
	 * @param int $active 1|0.
	 */
	public static function set_redirect_active( int $id, int $active ): bool {
		return Damavand_Slug_Redirects::set_redirect_active( $id, $active );
	}

	/**
	 * Update destination URL of a slug redirect (chain flatten).
	 *
	 * @param int    $id      Row ID.
	 * @param string $new_url Absolute target URL.
	 */
	public static function update_redirect_target( int $id, string $new_url ): bool {
		return Damavand_Slug_Redirects::update_redirect_target( $id, $new_url );
	}

	/**
	 * Delete a slug redirect row.
	 *
	 * @param int $id Row ID.
	 */
	public static function delete_redirect( int $id ): bool {
		return Damavand_Slug_Redirects::delete_redirect( $id );
	}

	/**
	 * Product IDs currently marked 410 Gone in OOS tracker.
	 *
	 * @return array<int,true> Map of product_id => true.
	 */
	public static function get_410_product_map(): array {
		return Damavand_Slug_Health::get_410_product_map();
	}

	/**
	 * Whether product has an active 410 Gone decision.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function is_410_product( int $product_id ): bool {
		return Damavand_Slug_Health::is_410_product( $product_id );
	}

	/**
	 * Option key for full-catalog slug health report.
	 */
	public static function full_report_option(): string {
		return Damavand_Slug_Health::full_report_option();
	}

	/**
	 * Stored full health report (may be in-progress).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_stored_full_report(): array {
		return Damavand_Slug_Health::get_stored_full_report();
	}

	/**
	 * Drop products from the cached health list (after apply / already finglish).
	 *
	 * @param int[] $product_ids IDs.
	 */
	public static function prune_health_report_ids( array $product_ids ): void {
		Damavand_Slug_Health::prune_health_report_ids( $product_ids );
	}

	/**
	 * Current slug already is the Finglish suggestion (or uniquified -2).
	 */
	public static function slug_is_applied_suggestion( string $slug, string $suggest ): bool {
		return Damavand_Slug_Health::slug_is_applied_suggestion( $slug, $suggest );
	}

	/**
	 * All published product IDs (newest first).
	 *
	 * @return int[]
	 */
	public static function get_all_published_product_ids(): array {
		return Damavand_Slug_Health::get_all_published_product_ids();
	}

	/**
	 * Analyze one product for health row (or null if OK / skipped).
	 *
	 * @param int             $product_id Product ID.
	 * @param array<int,true> $gone_410   410 map.
	 * @return array{row:?array,skipped_410:bool}
	 */
	public static function analyze_product_health( int $product_id, array $gone_410 = array() ): array {
		return Damavand_Slug_Health::analyze_product_health( $product_id, $gone_410 );
	}

	/**
	 * Start background full-catalog slug health scan.
	 *
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_full_health_scan(): array {
		return Damavand_Slug_Health::start_full_health_scan();
	}

	/**
	 * Process a chunk of product IDs for full health scan.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array{processed:int,issues_added:int}
	 */
	public static function process_health_scan_ids( array $ids ): array {
		return Damavand_Slug_Health::process_health_scan_ids( $ids );
	}

	/**
	 * Finalize full report: dup flags, sort, trim heavy fields, mark complete.
	 * Keeps at most 2000 worst-scoring issues to avoid bloating wp_options.
	 */
	public static function finalize_full_health_report(): void {
		Damavand_Slug_Health::finalize_full_health_report();
	}

	/**
	 * Health scan for published product slugs.
	 * Prefers completed full-catalog report when available.
	 *
	 * @param int $scan_limit   How many recent products to inspect (quick mode).
	 * @param int $return_limit Per-page size.
	 * @param int $page         1-based page.
	 * @return array{rows:array<int,array>,scanned:int,issues:int,skipped_410:int,source:string,page:int,per_page:int,pages:int,finished_at?:string,total?:int,complete?:bool,stored_rows?:int}
	 */
	public static function get_health_report( int $scan_limit = 400, int $return_limit = 100, int $page = 1 ): array {
		return Damavand_Slug_Health::get_health_report( $scan_limit, $return_limit, $page );
	}

	/**
	 * Reason labels for health UI.
	 *
	 * @param string $code Reason code.
	 */
	public static function reason_label( string $code ): string {
		return Damavand_Slug_Health::reason_label( $code );
	}

	/**
	 * Preview or apply Finglish slug for one published product (creates 301 via post_updated).
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $dry_run    If true, only preview.
	 * @return array{ok:bool,message:string,old_slug?:string,new_slug?:string,old_url?:string,new_url?:string,redirect_id?:int,indexnow?:bool,loop_blocked?:bool}
	 */
	public static function apply_suggested_slug( int $product_id, bool $dry_run = true ): array {
		return Damavand_Slug_Health::apply_suggested_slug( $product_id, $dry_run );
	}

	/**
	 * Latest active slug redirect id for product + old slug.
	 */
	public static function latest_redirect_id_for_product( int $product_id, string $old_slug = '' ): int {
		return Damavand_Slug_Redirects::latest_redirect_id_for_product( $product_id, $old_slug );
	}

	/**
	 * WordPress core redirect_canonical / wp_old_slug_redirect will 301 this slug.
	 */
	public static function wp_old_slug_covers( int $product_id, string $old_slug ): bool {
		return Damavand_Slug_Redirects::wp_old_slug_covers( $product_id, $old_slug );
	}

	/**
	 * Batch dry-run / apply (hard cap 20).
	 *
	 * @param int[] $product_ids IDs.
	 * @param bool  $dry_run     Dry-run.
	 * @return array{ok:bool,dry_run:bool,applied:int,failed:int,items:array}
	 */
	public static function batch_apply( array $product_ids, bool $dry_run = true ): array {
		return Damavand_Slug_Health::batch_apply( $product_ids, $dry_run );
	}

	/**
	 * Undo a health/auto slug apply: restore old slug + deactivate 301.
	 *
	 * @param int $redirect_id Slug redirect row ID.
	 * @return array{ok:bool,message:string}
	 */
	public static function undo_slug_redirect( int $redirect_id ): array {
		return Damavand_Slug_Health::undo_slug_redirect( $redirect_id );
	}

	/**
	 * Search published products for slug tools UI.
	 *
	 * @param string $query Search term (title / ID / slug).
	 * @param int    $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_products_for_slug( string $query, int $limit = 20 ): array {
		return Damavand_Slug_Health::search_products_for_slug( $query, $limit );
	}

	/**
	 * Admin JS for live slug score/suggest + publish warning.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		( new Damavand_Slug_Editor() )->enqueue_admin_assets( $hook );
	}
}
