<?php
/**
 * Frontend Handler for InchTitle Variation Titles for WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

class InchTitle_Variation_Titles_Frontend {

	/**
	 * Single instance.
	 *
	 * @var InchTitle_Variation_Titles_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Target attribute taxonomy.
	 *
	 * @var string
	 */
	const TARGET_ATTRIBUTE = 'pa_screen_size';

	/**
	 * Get single instance.
	 *
	 * @return InchTitle_Variation_Titles_Frontend
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 20 );
		add_filter( 'woocommerce_product_variation_title', array( $this, 'filter_archive_variation_title' ), 30, 4 );
	}

	/**
	 * Check if product is eligible for dynamic variation titles.
	 *
	 * @param WC_Product|null $product WooCommerce product object.
	 * @return bool True if eligible, false otherwise.
	 */
	public function is_product_eligible( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		// Must be a variable product.
		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		// Check category eligibility.
		$selected_categories = get_option( ITVT_OPTION_CATEGORIES, array( ITVT_DEFAULT_CATEGORY_ID ) );
		if ( empty( $selected_categories ) || ! is_array( $selected_categories ) ) {
			return false;
		}

		$selected_categories = array_map( 'absint', $selected_categories );
		$product_id = $product->get_id();
		$product_cat_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

		if ( empty( $product_cat_ids ) ) {
			return false;
		}

		$category_matched = false;
		foreach ( $product_cat_ids as $cat_id ) {
			$cat_id = (int) $cat_id;
			if ( in_array( $cat_id, $selected_categories, true ) ) {
				$category_matched = true;
				break;
			}

			// Check ancestors.
			$ancestors = get_ancestors( $cat_id, 'product_cat' );
			if ( ! empty( $ancestors ) && is_array( $ancestors ) ) {
				foreach ( $ancestors as $ancestor_id ) {
					if ( in_array( (int) $ancestor_id, $selected_categories, true ) ) {
						$category_matched = true;
						break 2;
					}
				}
			}
		}

		if ( ! $category_matched ) {
			return false;
		}

		// Check if product uses pa_screen_size for variations.
		$attributes = $product->get_variation_attributes();
		if ( empty( $attributes ) || ! isset( $attributes[ self::TARGET_ATTRIBUTE ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Detect brand for the product according to priority.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Detected brand name.
	 */
	public function detect_product_brand( $product ) {
		$product_id = $product->get_id();
		$brand = '';

		// Priority 1: Check theme brand taxonomy if defined.
		$theme_brand_tax = function_exists( 'electro_get_brands_taxonomy' ) ? electro_get_brands_taxonomy() : '';

		$brand_taxonomies = array_unique(
			array_filter(
				array(
					$theme_brand_tax,
					'pa_brands',
					'product_brand',
					'pa_brand',
					'brand',
					'p_brand',
					'yith_product_brand',
				)
			)
		);

		foreach ( $brand_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$brand = trim( (string) $terms[0] );
					if ( ! empty( $brand ) ) {
						break;
					}
				}
			}
		}

		// Priority 4: Fallback to first word of product title if no taxonomy brand found.
		if ( empty( $brand ) ) {
			$title = $product->get_name();
			if ( preg_match( '/^([a-zA-Z0-9\x{0600}-\x{06FF}]+)/u', trim( $title ), $matches ) ) {
				$brand = $matches[1];
			}
		}

		return $brand;
	}

	/**
	 * Build normalized screen size label dictionary.
	 *
	 * @return array Array mapping slug/name to formatted label (e.g. '55-inch' => '55 Inch').
	 */
	public function get_normalized_screen_sizes() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TARGET_ATTRIBUTE,
				'hide_empty' => false,
			)
		);

		$size_map = array();

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$name = $term->name;
				$slug = $term->slug;

				// Format: e.g. "55-inch" -> "55 Inch", "55inch" -> "55 Inch", "55" -> "55 Inch"
				$formatted = $this->normalize_size_label( $name );

				$size_map[ $slug ] = $formatted;
				$size_map[ $name ] = $formatted;
				$size_map[ sanitize_title( $slug ) ] = $formatted;
			}
		}

		return $size_map;
	}

	/**
	 * Normalize a screen size string to clean Title Case (e.g. "55-inch" -> "55 Inch").
	 *
	 * @param string $raw Raw size string.
	 * @return string Normalized size string.
	 */
	public function normalize_size_label( $raw ) {
		$clean = trim( (string) $raw );

		if ( preg_match( '/^(\d+)\s*[-_]?\s*(?:inch|inches|in|\"|\')?$/i', $clean, $matches ) ) {
			return $matches[1] . ' Inch';
		}

		// If term contains inch with dash or space.
		$clean = preg_replace( '/[-_]?\s*inch(es)?/i', ' Inch', $clean );
		return ucwords( trim( $clean ) );
	}

	/**
	 * Match category variation-card titles to the single-product title format.
	 *
	 * Example: "Sony BRAVIA ... - 55-inch" becomes
	 * "Sony 55 Inch BRAVIA ..." without changing the stored product title.
	 *
	 * @param string               $title        Generated variation title.
	 * @param WC_Product_Variation $product      Variation product object.
	 * @param string               $title_base   Parent title.
	 * @param string               $title_suffix Formatted variation attributes.
	 * @return string
	 */
	public function filter_archive_variation_title( $title, $product, $title_base = '', $title_suffix = '' ) {
		if ( is_admin() || ! is_product_category() || ! $product instanceof WC_Product_Variation ) {
			return $title;
		}

		$parent = wc_get_product( $product->get_parent_id() );
		if ( ! $this->is_product_eligible( $parent ) ) {
			return $title;
		}

		$raw_size = $product->get_attribute( self::TARGET_ATTRIBUTE );
		$size     = $this->normalize_size_label( $raw_size );
		if ( empty( $size ) ) {
			return $title;
		}

		$base_title = $parent->get_name();
		$brand      = $this->detect_product_brand( $parent );

		if ( ! empty( $brand ) ) {
			$pattern = '/\b' . preg_quote( $brand, '/' ) . '\b/iu';
			if ( preg_match( $pattern, $base_title ) ) {
				return preg_replace( $pattern, '$0 ' . $size, $base_title, 1 );
			}

			return trim( $brand . ' ' . $size . ' ' . $base_title );
		}

		return trim( $size . ' ' . $base_title );
	}

	/**
	 * Conditionally enqueue frontend script and localize data.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $this->is_product_eligible( $product ) ) {
			return;
		}

		$brand = $this->detect_product_brand( $product );
		$size_map = $this->get_normalized_screen_sizes();

		wp_enqueue_script(
			'inchtitle-variation-titles-for-woocommerce-frontend',
			ITVT_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			ITVT_VERSION,
			true
		);

		wp_localize_script(
			'inchtitle-variation-titles-for-woocommerce-frontend',
			'inchTitleVariationTitlesData',
			array(
				'originalTitle'    => $product->get_name(),
				'brand'            => $brand,
				'attributeKey'     => 'attribute_' . self::TARGET_ATTRIBUTE,
				'attributeTaxonomy'=> self::TARGET_ATTRIBUTE,
				'sizeMap'          => $size_map,
				'titleSelector'    => 'h1.product_title.entry-title',
				'fallbackSelector' => '.product_title',
			)
		);
	}
}
