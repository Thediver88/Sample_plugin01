<?php
/**
 * Plugin Name:          Sample Shelf for WooCommerce
 * Description:          Let shoppers request a free or low-cost sample of flooring, tile or fabric before they order.
 * Version:              0.1.0
 * Requires at least:    6.9
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Cartwright
 * Author URI:           https://cartwrightplugins.com/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          sample-shelf-for-woocommerce
 * WC requires at least: 10.0
 * WC tested up to:      11.2
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

define( 'SMPSHELF_VERSION', '0.1.0' );
define( 'SMPSHELF_FILE', __FILE__ );
define( 'SMPSHELF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMPSHELF_URL', plugin_dir_url( __FILE__ ) );

// Declare compatibility with High-Performance Order Storage and the block cart/checkout.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SMPSHELF_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SMPSHELF_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		/**
		 * Fires once Sample Shelf has loaded. The feature classes are added in later versions.
		 */
		do_action( 'smpshelf_loaded' );
	}
);
