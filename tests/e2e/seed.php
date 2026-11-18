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
// WP-CLI can't detect mod_rewrite, so write Apache's rules ourselves (wp-env serves via Apache).
file_put_contents(
	ABSPATH . '.htaccess',
	"# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n"
);
