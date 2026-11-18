<?php
/**
 * Admin: the WooCommerce > Settings > Sample Shelf tab and the per-product fields.
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screens. WooCommerce handles the nonces and capability checks for its settings pages
 * and for saving products; we only clean what we read.
 */
class Smpshelf_Admin {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_settings_page' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Smpshelf_Settings::OPT_PRICE, array( __CLASS__, 'sanitize_price' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . Smpshelf_Settings::OPT_MAX, array( __CLASS__, 'sanitize_max' ) );

		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
	}

	/**
	 * Add our tab.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public static function add_settings_page( $pages ) {
		if ( class_exists( 'WC_Settings_Page' ) ) {
			require_once SMPSHELF_PATH . 'includes/class-smpshelf-settings-page.php';
			$pages[] = new Smpshelf_Settings_Page();
		}
		return $pages;
	}

	/**
	 * Clean the typed sample price.
	 *
	 * @param mixed $value Typed value.
	 * @return string
	 */
	public static function sanitize_price( $value ) {
		return Smpshelf_Settings::normalize_price( $value, wc_get_price_decimals() );
	}

	/**
	 * Clean the typed limit.
	 *
	 * @param mixed $value Typed value.
	 * @return int
	 */
	public static function sanitize_max( $value ) {
		return Smpshelf_Settings::normalize_max( $value );
	}

	/**
	 * Fields on the product's General tab.
	 */
	public static function product_fields() {
		echo '<div class="options_group show_if_simple show_if_variable">';
		woocommerce_wp_select(
			array(
				'id'          => Smpshelf_Settings::META_ENABLED,
				'label'       => __( 'Sample', 'sample-shelf-for-woocommerce' ),
				'description' => __( 'Offer a "Request a sample" button on this product.', 'sample-shelf-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					''    => __( 'Use the shop setting', 'sample-shelf-for-woocommerce' ),
					'yes' => __( 'Offer a sample', 'sample-shelf-for-woocommerce' ),
					'no'  => __( 'No sample', 'sample-shelf-for-woocommerce' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => Smpshelf_Settings::META_PRICE,
				'label'       => __( 'Sample price', 'sample-shelf-for-woocommerce' ),
				'description' => __( 'Leave empty to use the shop setting. 0 means free. Enter the price the way you enter product prices (with or without tax).', 'sample-shelf-for-woocommerce' ),
				'desc_tip'    => true,
				'data_type'   => 'price',
			)
		);
		echo '</div>';
	}

	/**
	 * Save the product fields (WooCommerce has already checked the nonce and the user's right to edit).
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save_product( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product edit nonce before this hook.
		$choice = isset( $_POST[ Smpshelf_Settings::META_ENABLED ] ) ? Smpshelf_Settings::normalize_choice( sanitize_text_field( wp_unslash( $_POST[ Smpshelf_Settings::META_ENABLED ] ) ) ) : '';
		$price  = isset( $_POST[ Smpshelf_Settings::META_PRICE ] ) ? Smpshelf_Settings::normalize_price( sanitize_text_field( wp_unslash( $_POST[ Smpshelf_Settings::META_PRICE ] ) ), wc_get_price_decimals() ) : '';
		// phpcs:enable
		$product->update_meta_data( Smpshelf_Settings::META_ENABLED, $choice );
		$product->update_meta_data( Smpshelf_Settings::META_PRICE, $price );
	}
}
