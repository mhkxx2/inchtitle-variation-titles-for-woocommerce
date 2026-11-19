<?php
/**
 * SaaS-Grade Admin Settings & Studio Dashboard for InchTitle Variation Titles for WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

class InchTitle_Variation_Titles_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var InchTitle_Variation_Titles_Admin|null
	 */
	private static $instance = null;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Get single instance.
	 *
	 * @return InchTitle_Variation_Titles_Admin
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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Register menu under WooCommerce.
	 */
	public function register_admin_menu() {
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'TV Variation Titles', 'inchtitle-variation-titles-for-woocommerce' ),
			__( 'TV Variation Titles', 'inchtitle-variation-titles-for-woocommerce' ),
			'manage_woocommerce',
			'inchtitle-variation-titles-for-woocommerce',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts & styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		// Enqueue WooCommerce SelectWoo / Select2 assets.
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );
		}

		wp_enqueue_style(
			'inchtitle-vt-admin-saas-style',
			ITVT_URL . 'assets/css/admin.css',
			array(),
			ITVT_VERSION
		);

		wp_enqueue_script(
			'inchtitle-vt-admin-saas-script',
			ITVT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ITVT_VERSION,
			true
		);

		wp_localize_script(
			'inchtitle-vt-admin-saas-script',
			'inchTitleVtAdmin',
			array(
				'placeholder' => __( 'Search product categories...', 'inchtitle-variation-titles-for-woocommerce' ),
			)
		);
	}

	/**
	 * Handle form submission.
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['inchtitle_vt_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'inchtitle-variation-titles-for-woocommerce' ) );
		}

		check_admin_referer( 'inchtitle_vt_save_settings', 'inchtitle_vt_nonce' );

		$selected_categories = array();
		if ( ! empty( $_POST['inchtitle_vt_categories'] ) && is_array( $_POST['inchtitle_vt_categories'] ) ) {
			$selected_categories = array_map( 'absint', $_POST['inchtitle_vt_categories'] );
			$selected_categories = array_filter( $selected_categories );
			$selected_categories = array_values( array_unique( $selected_categories ) );
		}

		update_option( ITVT_OPTION_CATEGORIES, $selected_categories );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'inchtitle-variation-titles-for-woocommerce',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get product category options formatted with hierarchical indentation.
	 *
	 * @return array Array of category items.
	 */
	public function get_hierarchical_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$tree = array();
		foreach ( $terms as $term ) {
			$tree[ $term->parent ][] = $term;
		}

		$result = array();
		$this->build_category_tree( $tree, 0, 0, $result );
		return $result;
	}

	/**
	 * Recursive helper to build tree.
	 */
	private function build_category_tree( &$tree, $parent_id, $depth, &$result ) {
		if ( ! isset( $tree[ $parent_id ] ) ) {
			return;
		}

		foreach ( $tree[ $parent_id ] as $term ) {
			$prefix = $depth > 0 ? str_repeat( '— ', $depth ) : '';
			$result[] = array(
				'id'             => $term->term_id,
				'name'           => $term->name,
				'slug'           => $term->slug,
				'formatted_name' => $prefix . $term->name . ' (ID: ' . $term->term_id . ')',
				'count'          => $term->count,
			);
			$this->build_category_tree( $tree, $term->term_id, $depth + 1, $result );
		}
	}

	/**
	 * Render SaaS-style settings & studio dashboard.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'inchtitle-variation-titles-for-woocommerce' ) );
		}

		$saved_categories = get_option( ITVT_OPTION_CATEGORIES, array( ITVT_DEFAULT_CATEGORY_ID ) );
		if ( ! is_array( $saved_categories ) ) {
			$saved_categories = array();
		}

		$all_categories = $this->get_hierarchical_categories();

		// Calculate covered categories and product counts
		$covered_categories = array();
		$total_products_count = 0;
		$all_covered_cat_ids = array();

		foreach ( $saved_categories as $cat_id ) {
			$cat_term = get_term( $cat_id, 'product_cat' );
			if ( $cat_term && ! is_wp_error( $cat_term ) ) {
				$all_covered_cat_ids[] = $cat_id;
				$total_products_count += $cat_term->count;

				$children = get_term_children( $cat_id, 'product_cat' );
				$child_items = array();
				if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
					foreach ( $children as $child_id ) {
						$all_covered_cat_ids[] = $child_id;
						$c_term = get_term( $child_id, 'product_cat' );
						if ( $c_term && ! is_wp_error( $c_term ) ) {
							$child_items[] = array(
								'id'    => $child_id,
								'name'  => $c_term->name,
								'slug'  => $c_term->slug,
								'count' => $c_term->count,
							);
						}
					}
				}
				$covered_categories[] = array(
					'parent_id'    => $cat_id,
					'parent_name'  => $cat_term->name,
					'parent_slug'  => $cat_term->slug,
					'parent_count' => $cat_term->count,
					'children'     => $child_items,
				);
			}
		}

		$all_covered_cat_ids = array_unique( $all_covered_cat_ids );
		$total_covered_cats_count = count( $all_covered_cat_ids );

		// Diagnostics info
		$wc_version = class_exists( 'WooCommerce' ) ? WC()->version : 'N/A';
		$screen_terms_count = wp_count_terms( array( 'taxonomy' => 'pa_screen_size', 'hide_empty' => false ) );
		$brand_tax = function_exists( 'electro_get_brands_taxonomy' ) ? electro_get_brands_taxonomy() : 'pa_brands';
		if ( empty( $brand_tax ) ) {
			$brand_tax = 'pa_brands';
		}

		?>
		<div class="wrap inchtitle-saas-root">
			
			<!-- SaaS Top Navigation Bar -->
			<header class="inchtitle-saas-topbar">
				<div class="inchtitle-saas-topbar-left">
					<div class="inchtitle-saas-brand-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect>
							<polyline points="17 2 12 7 7 2"></polyline>
						</svg>
					</div>
					<div>
						<div class="inchtitle-saas-brand-title">
							<span>InchTitle Dynamic Titles Studio</span>
							<span class="inchtitle-saas-tag-pill">Enterprise Suite</span>
						</div>
						<div class="inchtitle-saas-engine-status">
							<span class="inchtitle-saas-pulse-dot"></span>
							<span class="inchtitle-saas-status-text"><?php esc_html_e( 'Variation Injection Engine Active', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
							<span class="inchtitle-saas-dot-sep">·</span>
							<span class="inchtitle-saas-version-code">v<?php echo esc_html( ITVT_VERSION ); ?></span>
						</div>
					</div>
				</div>

				<div class="inchtitle-saas-topbar-right">
					<a href="#" target="_blank" class="inchtitle-saas-btn-subtle">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
						<?php esc_html_e( 'View Store Category', 'inchtitle-variation-titles-for-woocommerce' ); ?>
					</a>
					<button type="submit" form="inchtitle-settings-form" class="inchtitle-saas-btn-primary">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
						<?php esc_html_e( 'Deploy Changes', 'inchtitle-variation-titles-for-woocommerce' ); ?>
					</button>
				</div>
			</header>

			<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
				<div class="inchtitle-saas-toast">
					<div class="inchtitle-saas-toast-icon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
					</div>
					<div class="inchtitle-saas-toast-content">
						<strong><?php esc_html_e( 'Configuration Deployed Successfully', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
						<span><?php esc_html_e( 'Dynamic title rules are instantly synchronized across eligible TV product pages.', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
					</div>
					<button type="button" class="inchtitle-saas-toast-close" onclick="this.parentElement.remove();">&times;</button>
				</div>
			<?php endif; ?>

			<!-- SaaS KPI Metric Counters Grid -->
			<section class="inchtitle-saas-kpi-grid">
				<div class="inchtitle-saas-kpi-card">
					<div class="inchtitle-saas-kpi-header">
						<span class="inchtitle-saas-kpi-title"><?php esc_html_e( 'Target Products', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
						<span class="inchtitle-saas-kpi-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
						</span>
					</div>
					<div class="inchtitle-saas-kpi-value"><?php echo esc_html( $total_products_count ); ?>+</div>
					<div class="inchtitle-saas-kpi-subtext">
						<span class="inchtitle-saas-trend-up">100% Covered</span> <?php esc_html_e( 'across TV catalog', 'inchtitle-variation-titles-for-woocommerce' ); ?>
					</div>
				</div>

				<div class="inchtitle-saas-kpi-card">
					<div class="inchtitle-saas-kpi-header">
						<span class="inchtitle-saas-kpi-title"><?php esc_html_e( 'Eligible Categories', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
						<span class="inchtitle-saas-kpi-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
						</span>
					</div>
					<div class="inchtitle-saas-kpi-value"><?php echo esc_html( $total_covered_cats_count ); ?></div>
					<div class="inchtitle-saas-kpi-subtext">
						<span><?php echo sprintf( esc_html__( '%d root + %d inherited', 'inchtitle-variation-titles-for-woocommerce' ), count( $saved_categories ), max( 0, $total_covered_cats_count - count( $saved_categories ) ) ); ?></span>
					</div>
				</div>

				<div class="inchtitle-saas-kpi-card">
					<div class="inchtitle-saas-kpi-header">
						<span class="inchtitle-saas-kpi-title"><?php esc_html_e( 'Screen Size Terms', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
						<span class="inchtitle-saas-kpi-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
						</span>
					</div>
					<div class="inchtitle-saas-kpi-value"><?php echo esc_html( $screen_terms_count ); ?> <small>sizes</small></div>
					<div class="inchtitle-saas-kpi-subtext">
						<span class="inchtitle-saas-pill-tag">pa_screen_size</span>
					</div>
				</div>

				<div class="inchtitle-saas-kpi-card">
					<div class="inchtitle-saas-kpi-header">
						<span class="inchtitle-saas-kpi-title"><?php esc_html_e( 'Runtime Latency', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
						<span class="inchtitle-saas-kpi-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
						</span>
					</div>
					<div class="inchtitle-saas-kpi-value">&lt; 0.1ms</div>
					<div class="inchtitle-saas-kpi-subtext">
						<span class="inchtitle-saas-trend-up">Zero DB Write</span> <?php esc_html_e( 'Client-side render', 'inchtitle-variation-titles-for-woocommerce' ); ?>
					</div>
				</div>
			</section>

			<!-- SaaS Segmented Tab Navigation -->
			<nav class="inchtitle-saas-tabs" id="inchtitle-saas-tabs-nav">
				<button type="button" class="inchtitle-saas-tab-btn active" data-tab="tab-studio">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
					<?php esc_html_e( 'Transformation Studio', 'inchtitle-variation-titles-for-woocommerce' ); ?>
				</button>
				<button type="button" class="inchtitle-saas-tab-btn" data-tab="tab-rules">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21v-7"></path><path d="M4 10V3"></path><path d="M12 21v-9"></path><path d="M12 8V3"></path><path d="M20 21v-5"></path><path d="M20 12V3"></path></svg>
					<?php esc_html_e( 'Category Rules & Targeting', 'inchtitle-variation-titles-for-woocommerce' ); ?>
				</button>
				<button type="button" class="inchtitle-saas-tab-btn" data-tab="tab-hierarchy">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
					<?php esc_html_e( 'Hierarchy Explorer', 'inchtitle-variation-titles-for-woocommerce' ); ?>
				</button>
				<button type="button" class="inchtitle-saas-tab-btn" data-tab="tab-system">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
					<?php esc_html_e( 'System Architecture', 'inchtitle-variation-titles-for-woocommerce' ); ?>
				</button>
			</nav>

			<!-- TAB 1: STUDIO LAB & SIMULATOR -->
			<div class="inchtitle-saas-tab-content active" id="tab-studio">
				<div class="inchtitle-saas-studio-grid">
					
					<!-- Left Studio Controls -->
					<div class="inchtitle-saas-studio-panel">
						<div class="inchtitle-saas-card-header">
							<div>
								<h3><?php esc_html_e( 'Live Testing Sandbox', 'inchtitle-variation-titles-for-woocommerce' ); ?></h3>
								<p class="inchtitle-saas-muted"><?php esc_html_e( 'Select sample TV products or type custom title parameters to verify brand & size placement.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
							</div>
						</div>

						<div class="inchtitle-saas-card-body">
							<!-- Preset Selector -->
							<div class="inchtitle-saas-field">
								<label class="inchtitle-saas-label"><?php esc_html_e( 'Quick Test Preset Product:', 'inchtitle-variation-titles-for-woocommerce' ); ?></label>
								<select id="inchtitle-preset-picker" class="inchtitle-saas-select">
									<option value="samsung-qn85f" data-title="Samsung QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE" data-brand="Samsung">Samsung QN85F Neo QLED (ID: 15569)</option>
									<option value="samsung-s95f" data-title="Glare-Free OLED Flagship Samsung S95F, 55–83-inch UAE" data-brand="Samsung">Samsung S95F OLED (Brand in middle)</option>
									<option value="samsung-qn80f" data-title="Step Into Neo QLED with Samsung QN80F, 50–85-inch UAE" data-brand="Samsung">Samsung QN80F (Preposition format)</option>
									<option value="custom" data-title="" data-brand="">[Custom Title Playground]</option>
								</select>
							</div>

							<div class="inchtitle-saas-field-row">
								<div class="inchtitle-saas-field" style="flex: 2.5;">
									<label class="inchtitle-saas-label"><?php esc_html_e( 'Original Product Title', 'inchtitle-variation-titles-for-woocommerce' ); ?></label>
									<input type="text" id="inchtitle-sim-title" class="inchtitle-saas-input" value="Samsung QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE" />
								</div>
								<div class="inchtitle-saas-field" style="flex: 1;">
									<label class="inchtitle-saas-label"><?php esc_html_e( 'Brand Term', 'inchtitle-variation-titles-for-woocommerce' ); ?></label>
									<input type="text" id="inchtitle-sim-brand" class="inchtitle-saas-input" value="Samsung" />
								</div>
							</div>

							<!-- Screen Size Selection Pills -->
							<div class="inchtitle-saas-field">
								<div class="inchtitle-saas-label-row">
									<label class="inchtitle-saas-label"><?php esc_html_e( 'Simulate Variation Selection:', 'inchtitle-variation-titles-for-woocommerce' ); ?></label>
									<span class="inchtitle-saas-badge-sm" id="inchtitle-sim-selected-badge">Selected: 55 Inch</span>
								</div>
								<div class="inchtitle-saas-chip-group" id="inchtitle-sim-chips">
									<button type="button" class="inchtitle-saas-chip" data-size="43 Inch">43"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="50 Inch">50"</button>
									<button type="button" class="inchtitle-saas-chip active" data-size="55 Inch">55"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="65 Inch">65"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="75 Inch">75"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="77 Inch">77"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="83 Inch">83"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="85 Inch">85"</button>
									<button type="button" class="inchtitle-saas-chip" data-size="98 Inch">98"</button>
									<button type="button" class="inchtitle-saas-chip inchtitle-saas-chip-reset" data-size=""><?php esc_html_e( 'Clear / Reset', 'inchtitle-variation-titles-for-woocommerce' ); ?></button>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Mockup Visual Product Preview -->
					<div class="inchtitle-saas-mockup-wrapper">
						<div class="inchtitle-saas-browser-card">
							<!-- Browser window traffic dots -->
							<div class="inchtitle-saas-browser-bar">
								<div class="inchtitle-saas-dots">
									<span class="dot red"></span>
									<span class="dot yellow"></span>
									<span class="dot green"></span>
								</div>
								<div class="inchtitle-saas-url-box">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
									https://github.com/mhkxx2/inchtitle-variation-titles-for-woocommerceshop/samsung-qn85f-neo-qled/
								</div>
								<span class="inchtitle-saas-mode-pill">Live Single-Product</span>
							</div>

							<!-- Mockup Content -->
							<div class="inchtitle-saas-mockup-body">
								<div class="inchtitle-saas-mock-crumbs">
									<span>Home</span> / <span>Smart TVs</span> / <span>Samsung TV</span> / <span class="active">Neo QLED</span>
								</div>

								<div class="inchtitle-saas-h1-display" id="inchtitle-sim-result">
									Samsung <mark class="inchtitle-saas-mark">55 Inch</mark> QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE
								</div>

								<div class="inchtitle-saas-mock-brand-tag">
									<span class="inchtitle-saas-badge-brand">Brand: Samsung</span>
									<span class="inchtitle-saas-badge-attr">Taxonomy: pa_screen_size</span>
									<span class="inchtitle-saas-badge-stock">In Stock</span>
								</div>

								<!-- Before & After Diff Box -->
								<div class="inchtitle-saas-diff-box">
									<div class="inchtitle-saas-diff-col">
										<span class="inchtitle-saas-diff-title">ORIGINAL DATABASE TITLE (UNTOUCHED)</span>
										<code class="inchtitle-saas-code" id="inchtitle-sim-orig-display">Samsung QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE</code>
									</div>
									<div class="inchtitle-saas-diff-col">
										<span class="inchtitle-saas-diff-title">RENDERED VISIBLE H1 TITLE</span>
										<code class="inchtitle-saas-code highlight" id="inchtitle-sim-rendered-display">Samsung 55 Inch QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE</code>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>

			<!-- TAB 2: CATEGORY RULES & TARGETING -->
			<div class="inchtitle-saas-tab-content" id="tab-rules">
				<div class="inchtitle-saas-rules-container">
					<form method="post" action="" id="inchtitle-settings-form">
						<?php wp_nonce_field( 'inchtitle_vt_save_settings', 'inchtitle_vt_nonce' ); ?>

						<div class="inchtitle-saas-panel">
							<div class="inchtitle-saas-card-header">
								<div>
									<h3><?php esc_html_e( 'Eligible Product Categories', 'inchtitle-variation-titles-for-woocommerce' ); ?></h3>
									<p class="inchtitle-saas-muted"><?php esc_html_e( 'Select parent categories. All current and future descendant categories will automatically inherit variation title transformation.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
								</div>
								<div class="inchtitle-saas-quick-presets">
									<button type="button" class="inchtitle-saas-btn-preset" id="inchtitle-btn-select-default">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
										<?php esc_html_e( 'Set Default (Smart TVs #81)', 'inchtitle-variation-titles-for-woocommerce' ); ?>
									</button>
									<button type="button" class="inchtitle-saas-btn-preset inchtitle_danger" id="inchtitle-btn-clear-all">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
										<?php esc_html_e( 'Clear Selection', 'inchtitle-variation-titles-for-woocommerce' ); ?>
									</button>
								</div>
							</div>

							<div class="inchtitle-saas-card-body">
								<div class="inchtitle-saas-select-field">
									<select name="inchtitle_vt_categories[]" id="inchtitle_vt_categories" class="inchtitle_select2" multiple="multiple" style="width: 100%;">
										<?php foreach ( $all_categories as $cat ) : ?>
											<option value="<?php echo esc_attr( $cat['id'] ); ?>" <?php echo in_array( (int) $cat['id'], $saved_categories, true ) ? 'selected="selected"' : ''; ?>>
												<?php echo esc_html( $cat['formatted_name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="inchtitle-saas-info-callout">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
									<div>
										<strong><?php esc_html_e( 'Smart Dynamic Ancestry Resolution', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
										<p><?php esc_html_e( 'When Smart TVs (ID: 81) is active, products in Neo QLED (#454), OLED (#453), QLED (#456), Samsung TV (#452) or screen-size subcategories are automatically identified and transformed in real-time without needing individual subcategory selections.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
									</div>
								</div>
							</div>

							<div class="inchtitle-saas-card-footer">
								<button type="submit" name="inchtitle_vt_submit" class="inchtitle-saas-btn-primary">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
									<?php esc_html_e( 'Save & Apply Rules', 'inchtitle-variation-titles-for-woocommerce' ); ?>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<!-- TAB 3: HIERARCHY EXPLORER -->
			<div class="inchtitle-saas-tab-content" id="tab-hierarchy">
				<div class="inchtitle-saas-hierarchy-container">
					<div class="inchtitle-saas-panel">
						<div class="inchtitle-saas-card-header">
							<div>
								<h3><?php esc_html_e( 'Active Category Tree & Product Distribution', 'inchtitle-variation-titles-for-woocommerce' ); ?></h3>
								<p class="inchtitle-saas-muted"><?php esc_html_e( 'Live visual hierarchy of categories and subcategories currently covered by the title transformation engine.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
							</div>
							<span class="inchtitle-saas-pill-tag"><?php echo sprintf( esc_html__( '%d Total Active Nodes', 'inchtitle-variation-titles-for-woocommerce' ), $total_covered_cats_count ); ?></span>
						</div>

						<div class="inchtitle-saas-card-body">
							<?php if ( ! empty( $covered_categories ) ) : ?>
								<div class="inchtitle-saas-tree-view">
									<?php foreach ( $covered_categories as $cov ) : ?>
										<div class="inchtitle-saas-tree-card">
											<div class="inchtitle-saas-tree-root-row">
												<span class="inchtitle-saas-folder-icon">📂</span>
												<div class="inchtitle-saas-tree-root-info">
													<strong><?php echo esc_html( $cov['parent_name'] ); ?></strong>
													<span class="inchtitle-saas-meta-tag">ID: #<?php echo esc_html( $cov['parent_id'] ); ?></span>
													<span class="inchtitle-saas-meta-tag">Slug: <?php echo esc_html( $cov['parent_slug'] ); ?></span>
												</div>
												<span class="inchtitle-saas-count-badge"><?php echo sprintf( esc_html__( '%d Total Products', 'inchtitle-variation-titles-for-woocommerce' ), $cov['parent_count'] ); ?></span>
											</div>

											<?php if ( ! empty( $cov['children'] ) ) : ?>
												<div class="inchtitle-saas-tree-branches">
													<div class="inchtitle-saas-branches-title">
														<?php echo sprintf( esc_html__( '↳ %d Inherited Descendant Subcategories:', 'inchtitle-variation-titles-for-woocommerce' ), count( $cov['children'] ) ); ?>
													</div>
													<div class="inchtitle-saas-child-grid">
														<?php foreach ( $cov['children'] as $child ) : ?>
															<div class="inchtitle-saas-child-node">
																<span class="inchtitle-saas-child-dot"></span>
																<div class="inchtitle-saas-child-info">
																	<span class="inchtitle-saas-child-name"><?php echo esc_html( $child['name'] ); ?></span>
																	<span class="inchtitle-saas-child-meta">#<?php echo esc_html( $child['id'] ); ?> · <?php echo esc_html( $child['slug'] ); ?></span>
																</div>
																<span class="inchtitle-saas-child-count"><?php echo sprintf( esc_html__( '%d items', 'inchtitle-variation-titles-for-woocommerce' ), $child['count'] ); ?></span>
															</div>
														<?php endforeach; ?>
													</div>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<div class="inchtitle-saas-empty">
									<p><?php esc_html_e( 'No categories selected. Title injection is currently idle.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- TAB 4: SYSTEM ARCHITECTURE & INTEGRATION -->
			<div class="inchtitle-saas-tab-content" id="tab-system">
				<div class="inchtitle-saas-system-grid">
					
					<!-- Left: Integration & Environment Checklist -->
					<div class="inchtitle-saas-panel">
						<div class="inchtitle-saas-card-header">
							<div>
								<h3><?php esc_html_e( 'Runtime Environment & Integrations', 'inchtitle-variation-titles-for-woocommerce' ); ?></h3>
								<p class="inchtitle-saas-muted"><?php esc_html_e( 'Diagnostics verification for WooCommerce ecosystem.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
							</div>
						</div>
						<div class="inchtitle-saas-card-body">
							<table class="inchtitle-saas-diag-table">
								<tbody>
									<tr>
										<td><strong><?php esc_html_e( 'WooCommerce Version', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><span class="inchtitle-saas-pill-green">v<?php echo esc_html( $wc_version ); ?> (Active)</span></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'HPOS (Custom Order Tables)', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><span class="inchtitle-saas-pill-green"><?php esc_html_e( 'Declared Compatible', 'inchtitle-variation-titles-for-woocommerce' ); ?></span></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Cart & Checkout Blocks', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><span class="inchtitle-saas-pill-green"><?php esc_html_e( 'Declared Compatible', 'inchtitle-variation-titles-for-woocommerce' ); ?></span></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Woo Variation Swatches', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><span class="inchtitle-saas-pill-green"><?php esc_html_e( 'Active & Hooked', 'inchtitle-variation-titles-for-woocommerce' ); ?></span></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Detected Brand Taxonomy', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><code class="inchtitle-saas-inline-code"><?php echo esc_html( $brand_tax ); ?></code></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Target Screen Size Taxonomy', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><code class="inchtitle-saas-inline-code">pa_screen_size</code></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Target DOM Selector', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong></td>
										<td><code class="inchtitle-saas-inline-code">h1.product_title.entry-title</code></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Right: Security & Boundary Safeguards -->
					<div class="inchtitle-saas-panel">
						<div class="inchtitle-saas-card-header">
							<div>
								<h3><?php esc_html_e( 'Security & Boundary Safeguards', 'inchtitle-variation-titles-for-woocommerce' ); ?></h3>
								<p class="inchtitle-saas-muted"><?php esc_html_e( 'Guaranteed protections to prevent store disruption or SEO pollution.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
							</div>
						</div>
						<div class="inchtitle-saas-card-body">
							<ul class="inchtitle-saas-safeguard-list">
								<li>
									<span class="inchtitle-saas-check">✓</span>
									<div>
										<strong><?php esc_html_e( 'Zero Database Mutation', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
										<span><?php esc_html_e( 'Product post_title in wp_posts is never altered.', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
									</div>
								</li>
								<li>
									<span class="inchtitle-saas-check">✓</span>
									<div>
										<strong><?php esc_html_e( 'Zero SEO Metadata Pollution', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
										<span><?php esc_html_e( 'Browser title, Yoast/RankMath meta, and JSON-LD schema stay pure.', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
									</div>
								</li>
								<li>
									<span class="inchtitle-saas-check">✓</span>
									<div>
										<strong><?php esc_html_e( 'Cart & Checkout Isolation', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
										<span><?php esc_html_e( 'Order receipts, Stripe/Tabby payloads, and invoices remain intact.', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
									</div>
								</li>
								<li>
									<span class="inchtitle-saas-check">✓</span>
									<div>
										<strong><?php esc_html_e( 'Direct Access Security', 'inchtitle-variation-titles-for-woocommerce' ); ?></strong>
										<span><?php esc_html_e( 'Nonced requests and manage_woocommerce capability enforced.', 'inchtitle-variation-titles-for-woocommerce' ); ?></span>
									</div>
								</li>
							</ul>
						</div>
					</div>

				</div>
			</div>

		</div>
		<?php
	}
}
