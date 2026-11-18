<?php
/**
 * Runs when the plugin is deleted: removes Sample Shelf's settings and per-product options.
 * Order records (which items were samples) are kept: they are order history.
 *
 * @package SampleShelf
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the data of the current site.
 */
function smpshelf_uninstall_site() {
	foreach ( array( 'smpshelf_enabled_default', 'smpshelf_price', 'smpshelf_max_per_order', 'smpshelf_reduce_stock', 'smpshelf_button_text', 'smpshelf_shipping_class' ) as $option ) {
		delete_option( $option );
	}
	delete_post_meta_by_key( '_smpshelf_enabled' );
	delete_post_meta_by_key( '_smpshelf_price' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $smpshelf_site_id ) {
		switch_to_blog( $smpshelf_site_id );
		smpshelf_uninstall_site();
		restore_current_blog();
	}
} else {
	smpshelf_uninstall_site();
}
