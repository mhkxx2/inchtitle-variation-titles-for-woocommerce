<?php
/**
 * Uninstaller for InchTitle Variation Titles for WooCommerce.
 *
 * Cleans up options upon plugin deletion without touching products or taxonomies.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'inchtitle_variation_titles_categories' );
