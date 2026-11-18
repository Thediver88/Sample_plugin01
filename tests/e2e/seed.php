<?php
/**
 * Seeds the test store (run with `wp eval-file`).
 *
 * @package SampleShelf
 */

update_option( 'woocommerce_currency', 'GBP' );
update_option( 'permalink_structure', '/%postname%/' );

$existing = wc_get_product_id_by_sku( 'SS-TILE' );
if ( $existing ) {
	wp_delete_post( $existing, true );
}

$product = new WC_Product_Simple();
$product->set_name( 'Sample Tile' );
$product->set_slug( 'sample-tile' );
$product->set_sku( 'SS-TILE' );
$product->set_regular_price( '30' );
$product->set_status( 'publish' );
$product->save();
flush_rewrite_rules();
