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

flush_rewrite_rules();
// WP-CLI can't detect mod_rewrite, so write Apache's rules ourselves (wp-env serves via Apache).
file_put_contents(
	ABSPATH . '.htaccess',
	"# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n"
);
WP_CLI::success( 'Sample Shelf test store ready.' );
