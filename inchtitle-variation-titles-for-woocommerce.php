<?php
/**
 * Plugin Name:       InchTitle Variation Titles for WooCommerce
 * Plugin URI: https://karazshop.ae/
 * Description:       Dynamically inject selected screen-size / attribute variations into the visible product H1 title for eligible WooCommerce categories — ideal for TVs and size-based catalogs.
 * Version:           1.0.0
 * Author:            ShopForge Labs
 * Author URI: https://mudassar.net/
 * Text Domain:       inchtitle-variation-titles-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   11.0.1
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined( 'ABSPATH' ) || exit;

define( 'ITVT_VERSION', '1.0.0' );
define( 'ITVT_FILE', __FILE__ );
define( 'ITVT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ITVT_URL', plugin_dir_url( __FILE__ ) );
define( 'ITVT_OPTION_CATEGORIES', 'inchtitle_variation_titles_categories' );
define( 'ITVT_DEFAULT_CATEGORY_ID', 0 );

/**
 * Main plugin class.
 */
final class InchTitle_Variation_Titles {

	/**
	 * Single instance of the class.
	 *
	 * @var InchTitle_Variation_Titles|null
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return InchTitle_Variation_Titles
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once ITVT_PATH . 'includes/class-admin-settings.php';
		require_once ITVT_PATH . 'includes/class-frontend-handler.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_feature_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		register_activation_hook( ITVT_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Declare compatibility with WooCommerce features (HPOS, Blocks, Block Editor).
	 */
	public function declare_wc_feature_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ITVT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ITVT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', ITVT_FILE, true );
		}
	}

	/**
	 * On plugins loaded.
	 */
	public function on_plugins_loaded() {
		load_plugin_textdomain( 'inchtitle-variation-titles-for-woocommerce', false, dirname( plugin_basename( ITVT_FILE ) ) . '/languages' );

		// Only bootstrap if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		if ( is_admin() ) {
			InchTitle_Variation_Titles_Admin::get_instance();
		}

		InchTitle_Variation_Titles_Frontend::get_instance();
	}

	/**
	 * Admin notice when WooCommerce is inactive.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'InchTitle Variation Titles for WooCommerce requires WooCommerce to be installed and active.', 'inchtitle-variation-titles-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		// Initialize default category if not already saved.
		$existing_categories = get_option( ITVT_OPTION_CATEGORIES, false );
		if ( false === $existing_categories ) {
			update_option( ITVT_OPTION_CATEGORIES, ITVT_DEFAULT_CATEGORY_ID ? array( ITVT_DEFAULT_CATEGORY_ID ) : array() );
		}
	}
}

/**
 * Initialize the plugin.
 */
function inchtitle_variation_titles() {
	return InchTitle_Variation_Titles::get_instance();
}

inchtitle_variation_titles();
