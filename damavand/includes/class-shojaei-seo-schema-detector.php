<?php
/**
 * Schema conflict detector — finds parallel application/ld+json blocks.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Schema_Detector
 */
class Shojaei_SEO_Schema_Detector {

	private const OPTION_LAST = 'shojaei_seo_schema_scan_last';
	private const OPTION_ALERT = 'shojaei_seo_schema_conflict_alert';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'schema' ) ) {
			return;
		}

		add_action( 'wp', array( $this, 'apply_disable_hooks' ) );

		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 0 );
			add_action( 'wp_footer', array( $this, 'maybe_render_conflict_banner' ), 99 );
		}
	}

	/**
	 * Disable competing schema sources based on settings.
	 */
	public function apply_disable_hooks(): void {
		$disable = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_disable_wc_schema', 'yes' );

		// When Damavand owns Product schema and no Rank Math/Yoast fights for it,
		// always suppress WooCommerce's parallel JSON-LD (duplicate Offer/Product).
		if ( ! $disable
			&& class_exists( 'Shojaei_SEO_Integration' )
			&& Shojaei_SEO_Integration::should_emit_product_schema()
			&& ! Shojaei_SEO_Integration::has_primary_seo_plugin()
		) {
			$disable = true;
		}

		if ( $disable ) {
			add_filter( 'woocommerce_structured_data_product', array( $this, 'empty_markup' ), 100 );
			add_filter( 'woocommerce_structured_data_product_offer', array( $this, 'empty_markup' ), 100 );
			add_filter( 'woocommerce_structured_data_breadcrumblist', array( $this, 'empty_markup' ), 100 );
			add_filter( 'woocommerce_structured_data_review', array( $this, 'empty_markup' ), 100 );

			add_action( 'wp_footer', array( $this, 'remove_wc_structured_data_output' ), 1 );
		}
	}

	/**
	 * Return empty structured data markup.
	 *
	 * @param mixed $markup Markup.
	 * @return array
	 */
	public function empty_markup( $markup ): array {
		return array();
	}

	/**
	 * Remove WooCommerce JSON-LD footer printer.
	 */
	public function remove_wc_structured_data_output(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->structured_data ) {
			return;
		}
		remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
	}

	/**
	 * Buffer frontend HTML for managers to detect conflicts (throttled).
	 */
	public function maybe_start_buffer(): void {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_detect_enabled', 'yes' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! is_singular( array( 'product', 'post', 'page' ) ) ) {
			return;
		}

		$url = $this->current_url();
		$last = get_option( self::OPTION_LAST, array() );
		if ( is_array( $last ) && ! empty( $last['url'] ) && $last['url'] === $url && ! empty( $last['at'] ) ) {
			if ( ( time() - (int) $last['at'] ) < HOUR_IN_SECONDS ) {
				return;
			}
		}

		ob_start( array( $this, 'scan_buffer' ) );
	}

	/**
	 * OB callback: scan then pass-through HTML.
	 *
	 * @param string $html Page HTML.
	 */
	public function scan_buffer( string $html ): string {
		$result = self::analyze_html( $html, $this->current_url() );
		self::store_scan( $result );

		if ( ! empty( $result['has_conflict'] ) ) {
			self::maybe_notify( $result );
		}

		return $html;
	}

	/**
	 * Analyze HTML for ld+json conflicts.
	 *
	 * @param string $html Page HTML.
	 * @param string $url  Page URL.
	 * @return array
	 */
	public static function analyze_html( string $html, string $url = '' ): array {
		$blocks = self::extract_ldjson_blocks( $html );
		$types  = array();
		$items  = array();

		foreach ( $blocks as $index => $data ) {
			$found_types = self::collect_types( $data );
			foreach ( $found_types as $type ) {
				$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
			}
			$items[] = array(
				'index' => $index + 1,
				'types' => $found_types,
			);
		}

		$conflicts = array();
		$watch     = array( 'Product', 'BreadcrumbList', 'Organization', 'WebSite', 'FAQPage', 'AggregateOffer', 'Offer' );

		foreach ( $watch as $type ) {
			$count = (int) ( $types[ $type ] ?? 0 );
			if ( $count > 1 ) {
				$conflicts[] = array(
					'type'    => $type,
					'count'   => $count,
					'message' => sprintf(
						/* translators: 1: schema type, 2: count */
						__( 'تگ موازی: %1$s تعداد %2$d بار در صفحه تکرار شده است.', 'shojaei-seo-for-woo' ),
						$type,
						$count
					),
				);
			}
		}

		// Product + Offer from different blocks often means WC + another SEO plugin.
		if ( ( $types['Product'] ?? 0 ) >= 1 && ( $types['Product'] ?? 0 ) + ( $types['Offer'] ?? 0 ) >= 3 ) {
			$conflicts[] = array(
				'type'    => 'Product/Offer',
				'count'   => (int) ( $types['Product'] ?? 0 ),
				'message' => __( 'احتمال تداخل اسکیمای محصول بین ووکامرس و افزونه سئو وجود دارد.', 'shojaei-seo-for-woo' ),
			);
		}

		// Deduplicate conflict messages by type.
		$unique = array();
		foreach ( $conflicts as $c ) {
			$unique[ $c['type'] ] = $c;
		}
		$conflicts = array_values( $unique );

		return array(
			'url'          => $url,
			'scanned_at'   => current_time( 'mysql' ),
			'block_count'  => count( $blocks ),
			'types'        => $types,
			'blocks'       => $items,
			'conflicts'    => $conflicts,
			'has_conflict' => ! empty( $conflicts ),
			'rank_math'    => Shojaei_SEO_Helpers::is_rank_math_active(),
			'yoast'        => Shojaei_SEO_Helpers::is_yoast_active(),
			'seo_plugins'  => class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '',
			'schema_mode'  => class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::schema_mode() : '',
			'suggestions'  => self::suggestions( $conflicts, $types ),
		);
	}

	/**
	 * Build admin suggestions for resolving conflicts.
	 *
	 * @param array $conflicts Conflicts.
	 * @param array $types     Type counts.
	 * @return string[]
	 */
	private static function suggestions( array $conflicts, array $types ): array {
		$out = array();
		if ( empty( $conflicts ) ) {
			$out[] = __( 'تداخل موازی جدی یافت نشد.', 'shojaei-seo-for-woo' );
			return $out;
		}

		if ( ! empty( $types['Product'] ) && (int) $types['Product'] > 1 ) {
			$out[] = __( 'اسکیمای پیش‌فرض ووکامرس را غیرفعال کنید یا Product اسکیمای این افزونه را خاموش کنید.', 'shojaei-seo-for-woo' );
		}
		if ( ! empty( $types['BreadcrumbList'] ) && (int) $types['BreadcrumbList'] > 1 ) {
			$out[] = __( 'Breadcrumb موازی است — Breadcrumb این افزونه یا منبع دیگر را غیرفعال کنید.', 'shojaei-seo-for-woo' );
		}
		if ( class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::has_primary_seo_plugin() ) {
			$out[] = sprintf(
				/* translators: %s: plugin names */
				__( '%s فعال است؛ با «احترام به افزونه SEO» این افزونه Product/Breadcrumb را واگذار می‌کند — تداخل معمولاً از ووکامرس است.', 'shojaei-seo-for-woo' ),
				Shojaei_SEO_Integration::detected_labels()
			);
		} elseif ( Shojaei_SEO_Helpers::is_rank_math_active() ) {
			$out[] = __( 'Rank Math فعال است؛ این افزونه فقط FAQ تکمیلی خروجی می‌دهد — Product تکراری معمولاً از ووکامرس است.', 'shojaei-seo-for-woo' );
		} elseif ( Shojaei_SEO_Helpers::is_yoast_active() ) {
			$out[] = __( 'Yoast فعال است؛ Product/Breadcrumb را به Yoast بسپارید و خروجی این افزونه را خاموش کنید.', 'shojaei-seo-for-woo' );
		}

		$out[] = __( 'از تنظیمات → یکپارچگی / اسکیما گزینه‌های تفکیک نقش را اعمال کنید.', 'shojaei-seo-for-woo' );
		return $out;
	}

	/**
	 * Extract JSON-LD payloads from HTML.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	public static function extract_ldjson_blocks( string $html ): array {
		$blocks = array();
		if ( ! preg_match_all( '/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			return $blocks;
		}

		foreach ( $matches[1] as $raw ) {
			$raw  = trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$data = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && null !== $data ) {
				$blocks[] = $data;
			}
		}

		return $blocks;
	}

	/**
	 * Recursively collect @type values.
	 *
	 * @param mixed $data JSON data.
	 * @return string[]
	 */
	public static function collect_types( $data ): array {
		$types = array();

		if ( ! is_array( $data ) ) {
			return $types;
		}

		if ( isset( $data['@type'] ) ) {
			$t = $data['@type'];
			if ( is_array( $t ) ) {
				foreach ( $t as $one ) {
					if ( is_string( $one ) ) {
						$types[] = self::short_type( $one );
					}
				}
			} elseif ( is_string( $t ) ) {
				$types[] = self::short_type( $t );
			}
		}

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				$types = array_merge( $types, self::collect_types( $node ) );
			}
		}

		foreach ( $data as $key => $value ) {
			if ( '@graph' === $key || '@type' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$types = array_merge( $types, self::collect_types( $value ) );
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Strip schema.org prefix from type.
	 *
	 * @param string $type Type string.
	 */
	private static function short_type( string $type ): string {
		$type = trim( $type );
		$type = preg_replace( '#^https?://schema\.org/#i', '', $type );
		return (string) $type;
	}

	/**
	 * Persist last scan.
	 *
	 * @param array $result Scan result.
	 */
	public static function store_scan( array $result ): void {
		update_option(
			self::OPTION_LAST,
			array(
				'url' => $result['url'] ?? '',
				'at'  => time(),
				'data'=> $result,
			),
			false
		);
	}

	/**
	 * Get last scan payload.
	 *
	 * @return array|null
	 */
	public static function get_last_scan(): ?array {
		$last = get_option( self::OPTION_LAST, null );
		if ( ! is_array( $last ) || empty( $last['data'] ) || ! is_array( $last['data'] ) ) {
			return null;
		}
		return $last['data'];
	}

	/**
	 * Notify admin once per conflict signature.
	 *
	 * @param array $result Scan result.
	 */
	private static function maybe_notify( array $result ): void {
		$sig = md5( wp_json_encode( array( $result['url'] ?? '', $result['conflicts'] ?? array() ) ) );
		$prev = get_option( self::OPTION_ALERT, '' );
		if ( $prev === $sig ) {
			return;
		}
		update_option( self::OPTION_ALERT, $sig, false );

		$count = count( $result['conflicts'] ?? array() );
		Shojaei_SEO_Notifications::add(
			'schema_conflict',
			sprintf(
				/* translators: 1: count, 2: url */
				__( 'تداخل اسکیما: %1$d مورد موازی در %2$s شناسایی شد.', 'shojaei-seo-for-woo' ),
				$count,
				$result['url'] ?? ''
			),
			0,
			admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-schema-conflict' )
		);
	}

	/**
	 * Admin-only front banner when Product schema is duplicated.
	 */
	public function maybe_render_conflict_banner(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! is_singular( array( 'product', 'post', 'page' ) ) ) {
			return;
		}
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_disable_wc_schema', 'no' ) ) {
			return;
		}

		$last = self::get_last_scan();
		if ( empty( $last['has_conflict'] ) ) {
			return;
		}

		$product_n = (int) ( $last['types']['Product'] ?? 0 );
		$current   = $this->current_url();
		$scanned   = (string) ( $last['url'] ?? '' );
		$same_url  = $scanned && untrailingslashit( $scanned ) === untrailingslashit( $current );

		if ( ! $same_url && $product_n < 2 ) {
			return;
		}

		$settings = admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-schema-conflict' );
		$nonce    = wp_create_nonce( 'shojaei_seo_admin_nonce' );
		?>
		<div id="shojaei-schema-conflict-banner" class="shojaei-schema-conflict-banner" role="status">
			<div class="shojaei-schema-conflict-inner">
				<strong><?php esc_html_e( 'تداخل اسکیما', 'shojaei-seo-for-woo' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: %d: product schema count */
						esc_html__( '%d× Product schema — پیشنهاد: اسکیمای ووکامرس را خاموش کنید.', 'shojaei-seo-for-woo' ),
						max( 2, $product_n )
					);
					?>
				</span>
				<button type="button" class="shojaei-schema-fix-btn" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'اسکیمای ووکامرس را خاموش کن', 'shojaei-seo-for-woo' ); ?>
				</button>
				<a href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'تنظیمات یکپارچگی', 'shojaei-seo-for-woo' ); ?></a>
				<button type="button" class="shojaei-schema-banner-close" aria-label="<?php esc_attr_e( 'بستن', 'shojaei-seo-for-woo' ); ?>">×</button>
			</div>
		</div>
		<script>
		(function(){
			var b=document.getElementById('shojaei-schema-conflict-banner');
			if(!b)return;
			var close=b.querySelector('.shojaei-schema-banner-close');
			if(close)close.addEventListener('click',function(){b.remove();});
			var btn=b.querySelector('.shojaei-schema-fix-btn');
			if(!btn)return;
			btn.addEventListener('click',function(){
				btn.disabled=true;
				var fd=new FormData();
				fd.append('action','shojaei_seo_disable_wc_schema');
				fd.append('nonce',btn.getAttribute('data-nonce'));
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',body:fd,credentials:'same-origin'})
					.then(function(r){return r.json();})
					.then(function(j){
						if(j&&j.success){btn.textContent=(j.data&&j.data.message)?j.data.message:'OK'; setTimeout(function(){location.reload();},800);}
						else{btn.disabled=false;}
					}).catch(function(){btn.disabled=false;});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Scan a public URL via HTTP (admin-triggered).
	 *
	 * @param string $url URL.
	 * @return array|WP_Error
	 */
	public static function scan_url( string $url ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'bad_url', __( 'آدرس نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		if ( class_exists( 'Shojaei_SEO_Helpers' ) && ! Shojaei_SEO_Helpers::is_safe_remote_url( $url, false ) ) {
			return new WP_Error( 'unsafe_url', __( 'فقط آدرس‌های همین سایت برای اسکن مجاز هستند.', 'shojaei-seo-for-woo' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'user-agent'  => 'ShojaeiSEO-SchemaDetector/' . DAMAVAND_SEO_VERSION,
				'cookies'     => array(),
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 400 || '' === $body ) {
			return new WP_Error( 'http', __( 'دریافت HTML صفحه ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		$result = self::analyze_html( $body, $url );
		self::store_scan( $result );
		if ( ! empty( $result['has_conflict'] ) ) {
			self::maybe_notify( $result );
		}

		return $result;
	}

	/**
	 * Current request URL.
	 */
	private function current_url(): string {
		if ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$scheme = is_ssl() ? 'https://' : 'http://';
			return $scheme . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		return home_url( '/' );
	}
}
