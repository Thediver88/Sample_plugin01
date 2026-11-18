<?php
/**
 * Builds a known test store (run with `wp eval-file`). Safe to run repeatedly.
 *
 * @package SampleShelf
 */

// phpcs:disable -- test fixture, not shipped.

update_option( 'woocommerce_currency', 'GBP' );
update_option( 'woocommerce_currency_pos', 'left' );
update_option( 'woocommerce_price_num_decimals', '2' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_default_country', 'GB' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery' ) );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'permalink_structure', '/%postname%/' );

// Sample Shelf settings: off by default (products opt in), free, at most 3 per order, no stock use.
update_option( 'smpshelf_enabled_default', 'no' );
update_option( 'smpshelf_price', '' );
update_option( 'smpshelf_max_per_order', '3' );
update_option( 'smpshelf_reduce_stock', 'no' );
update_option( 'smpshelf_button_text', '' );
update_option( 'smpshelf_shipping_class', '0' );

function smpshelf_seed_clean( $sku ) {
	$id = wc_get_product_id_by_sku( $sku );
	if ( $id ) {
		wp_delete_post( $id, true );
	}
}

function smpshelf_seed_simple( $sku, $name, $slug, $price, array $meta = array(), array $props = array() ) {
	smpshelf_seed_clean( $sku );
	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_slug( $slug );
	$product->set_sku( $sku );
	$product->set_regular_price( (string) $price );
	$product->set_status( 'publish' );
	foreach ( $props as $setter => $value ) {
		$product->$setter( $value );
	}
	foreach ( $meta as $key => $value ) {
		$product->update_meta_data( $key, $value );
	}
	return $product->save();
}

// A heavy shipping class costing £50 extra on the flat rate, used by the slate and the oak.
$heavy = get_term_by( 'slug', 'heavy', 'product_shipping_class' );
if ( ! $heavy ) {
	$inserted = wp_insert_term( 'Heavy', 'product_shipping_class', array( 'slug' => 'heavy' ) );
	$heavy    = get_term( $inserted['term_id'], 'product_shipping_class' );
}
$heavy_id = (int) $heavy->term_id;

smpshelf_seed_simple( 'SS-TILE', 'Sample Tile', 'sample-tile', 30, array( '_smpshelf_enabled' => 'yes' ) );
smpshelf_seed_simple( 'SS-SLATE', 'Paid Slate', 'paid-slate', 50, array( '_smpshelf_enabled' => 'yes', '_smpshelf_price' => '2.50' ), array( 'set_weight' => '25', 'set_shipping_class_id' => $heavy_id ) );
smpshelf_seed_simple( 'SS-MUG', 'Plain Mug', 'plain-mug', 9 );
smpshelf_seed_simple(
	'SS-LINEN',
	'Stocked Linen',
	'stocked-linen',
	10,
	array( '_smpshelf_enabled' => 'yes' ),
	array(
		'set_manage_stock'   => true,
		'set_stock_quantity' => 3,
	)
);

// A 50% coupon, to prove it never touches a sample line.
$coupon_id = wc_get_coupon_id_by_code( 'HALF' );
if ( $coupon_id ) {
	wp_delete_post( $coupon_id, true );
}
$coupon = new WC_Coupon();
$coupon->set_code( 'HALF' );
$coupon->set_discount_type( 'percent' );
$coupon->set_amount( 50 );
$coupon->save();

// A variable product: two finishes, samples on.
smpshelf_seed_clean( 'SS-OAK' );
$oak = new WC_Product_Variable();
$oak->set_name( 'Sample Oak' );
$oak->set_slug( 'sample-oak' );
$oak->set_sku( 'SS-OAK' );
$oak->set_status( 'publish' );
$attribute = new WC_Product_Attribute();
$attribute->set_name( 'Finish' );
$attribute->set_options( array( 'Natural', 'Grey' ) );
$attribute->set_visible( true );
$attribute->set_variation( true );
$oak->set_attributes( array( $attribute ) );
$oak->update_meta_data( '_smpshelf_enabled', 'yes' );
$oak->set_shipping_class_id( $heavy_id );
$oak->set_weight( '20' );
$oak_id = $oak->save();
foreach ( array( 'Natural' => '40', 'Grey' => '45' ) as $finish => $price ) {
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $oak_id );
	$variation->set_attributes( array( 'finish' => $finish ) );
	$variation->set_regular_price( $price );
	$variation->set_status( 'publish' );
	$variation->set_stock_status( 'instock' );
	$variation->save();
}
WC_Product_Variable::sync( $oak_id );

// Classic (shortcode) cart and checkout pages, alongside WooCommerce's block ones.
function smpshelf_seed_page( $slug, $title, $content ) {
	$page = get_page_by_path( $slug );
	$args = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	);
	if ( $page ) {
		$args['ID'] = $page->ID;
	}
	return wp_insert_post( $args );
}
smpshelf_seed_page( 'classic-cart', 'Classic Cart', '[woocommerce_cart]' );
smpshelf_seed_page( 'classic-checkout', 'Classic Checkout', '[woocommerce_checkout]' );

// One UK zone with a flat rate, so classic checkout offers payment methods.
foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
	$old_zone = new WC_Shipping_Zone( $zone_data['id'] );
	$old_zone->delete();
}
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'United Kingdom' );
$zone->add_location( 'GB', 'country' );
$zone->save();
$instance_id = $zone->add_shipping_method( 'flat_rate' );
$method      = WC_Shipping_Zones::get_shipping_method( $instance_id );
update_option( $method->get_instance_option_key(), array( 'title' => 'Flat rate', 'tax_status' => 'none', 'cost' => '5', 'type' => 'class', 'class_cost_' . $heavy_id => '50' ) );

// A 20% UK VAT rate. It only applies in tests that switch taxes on (see the test add-on below).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates" );
WC_Tax::_insert_tax_rate(
	array(
		'tax_rate_country'  => 'GB',
		'tax_rate'          => '20.0000',
		'tax_rate_name'     => 'VAT',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 0,
		'tax_rate_order'    => 1,
		'tax_rate_class'    => '',
	)
);

// Test-only add-on (mu-plugin): switches on conditions from cookies/query strings so tests can reach states
// that need an admin. It is never shipped.
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents(
	WPMU_PLUGIN_DIR . '/smpshelf-test-helper.php',
	<<<'PHPCODE'
<?php
// Cookie smpshelf_test_off=1: samples are switched off everywhere.
add_filter( 'smpshelf_product_enabled', function ( $on ) { return ! empty( $_COOKIE['smpshelf_test_off'] ) ? false : $on; } );
// Cookie smpshelf_test_tax=1: prices are entered and shown including 20% VAT.
if ( ! empty( $_COOKIE['smpshelf_test_tax'] ) ) {
	add_filter( 'pre_option_woocommerce_calc_taxes', function () { return 'yes'; } );
	add_filter( 'pre_option_woocommerce_prices_include_tax', function () { return 'yes'; } );
	add_filter( 'pre_option_woocommerce_tax_display_cart', function () { return 'incl'; } );
}
// ?smpshelf_weight=1 on any page prints the cart's total weight.
add_action( 'wp_footer', function () {
	if ( ! empty( $_GET['smpshelf_weight'] ) && function_exists( 'WC' ) && WC()->cart ) {
		echo '<div id="smpshelf-test-weight">weight=' . esc_html( (string) (float) WC()->cart->get_cart_contents_weight() ) . '</div>';
	}
} );
PHPCODE
);

// A customer with a completed order holding one sample and two normal Sample Tiles, for "Order again".
$shopper = get_user_by( 'login', 'shopper' );
if ( ! $shopper ) {
	$shopper_id = wp_create_user( 'shopper', 'shopper-pass-123', 'shopper@example.com' );
} else {
	$shopper_id = $shopper->ID;
}
foreach ( wc_get_orders( array( 'customer_id' => $shopper_id, 'limit' => -1 ) ) as $old_order ) {
	$old_order->delete( true );
}
$tile    = wc_get_product( wc_get_product_id_by_sku( 'SS-TILE' ) );
$order   = wc_create_order( array( 'customer_id' => $shopper_id ) );
$normal  = $order->add_product( $tile, 2 );
$sample  = $order->add_product( $tile, 1, array( 'subtotal' => 0, 'total' => 0 ) );
wc_add_order_item_meta( $sample, '_smpshelf_sample', 'yes' );
wc_add_order_item_meta( $sample, 'Sample', 'Yes' );
$order->set_address( array( 'first_name' => 'Sho', 'last_name' => 'Pper', 'country' => 'GB', 'email' => 'shopper@example.com' ), 'billing' );
$order->calculate_totals( false );
$order->set_status( 'completed' );
$order->save();
update_option( 'smpshelf_test_order_id', $order->get_id() );

flush_rewrite_rules();
// WP-CLI can't detect mod_rewrite, so write Apache's rules ourselves (wp-env serves via Apache).
file_put_contents(
	ABSPATH . '.htaccess',
	"# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n"
);
WP_CLI::success( 'Sample Shelf test store ready.' );
