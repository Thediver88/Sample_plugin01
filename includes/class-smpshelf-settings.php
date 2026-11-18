<?php
/**
 * Settings and eligibility: what the shop owner configured, and which products can offer a sample.
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the global settings and per-product options. Everything is decided here, on the server.
 */
class Smpshelf_Settings {

	const OPT_ENABLED  = 'smpshelf_enabled_default';
	const OPT_PRICE    = 'smpshelf_price';
	const OPT_MAX      = 'smpshelf_max_per_order';
	const OPT_STOCK    = 'smpshelf_reduce_stock';
	const OPT_TEXT     = 'smpshelf_button_text';
	const OPT_SHIPPING = 'smpshelf_shipping_class';

	const META_ENABLED = '_smpshelf_enabled';
	const META_PRICE   = '_smpshelf_price';

	const DEFAULT_MAX = 5;
	const HARD_MAX    = 50;

	/**
	 * Clean a price typed by the shop owner.
	 *
	 * @param mixed $raw      Typed value ("2,50", "2.5", "", "-1").
	 * @param int   $decimals Number of decimals the shop uses.
	 * @return string "" when empty or invalid, otherwise a plain decimal string (never negative).
	 */
	public static function normalize_price( $raw, $decimals = 2 ) {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$value = str_replace( ',', '.', trim( (string) $raw ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$number = max( 0.0, (float) $value );
		return number_format( round( $number, max( 0, (int) $decimals ) ), max( 0, (int) $decimals ), '.', '' );
	}

	/**
	 * Clean the "samples per order" limit.
	 *
	 * @param mixed $raw Typed value.
	 * @return int Between 1 and HARD_MAX; the default when empty or invalid.
	 */
	public static function normalize_max( $raw ) {
		if ( ! is_scalar( $raw ) || ! is_numeric( $raw ) ) {
			return self::DEFAULT_MAX;
		}
		return (int) min( self::HARD_MAX, max( 1, (int) $raw ) );
	}

	/**
	 * Clean a per-product on/off choice.
	 *
	 * @param mixed $raw Posted value.
	 * @return string "yes", "no" or "" (use the shop default).
	 */
	public static function normalize_choice( $raw ) {
		return ( is_string( $raw ) && in_array( $raw, array( 'yes', 'no' ), true ) ) ? $raw : '';
	}

	/**
	 * Maximum number of samples in one order.
	 *
	 * @return int
	 */
	public static function max_per_order() {
		return self::normalize_max( get_option( self::OPT_MAX, self::DEFAULT_MAX ) );
	}

	/**
	 * Whether samples take stock from the product.
	 *
	 * @return bool
	 */
	public static function reduces_stock() {
		return 'yes' === get_option( self::OPT_STOCK, 'no' );
	}

	/**
	 * Shipping class given to sample lines (0 = none), so a sample is not charged pallet freight.
	 *
	 * @return int
	 */
	public static function shipping_class_id() {
		return absint( get_option( self::OPT_SHIPPING, 0 ) );
	}

	/**
	 * Text on the sample button (translated default when the owner left it empty).
	 *
	 * @return string
	 */
	public static function button_text() {
		$text = trim( (string) get_option( self::OPT_TEXT, '' ) );
		return '' === $text ? __( 'Request a sample', 'sample-shelf-for-woocommerce' ) : $text;
	}

	/**
	 * The parent product for a product or variation.
	 *
	 * @param WC_Product|int $product Product, variation or ID.
	 * @return WC_Product|null
	 */
	public static function parent_product( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( absint( $product ) );
		if ( $product && $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		return $product ? $product : null;
	}

	/**
	 * Price of one sample, never read from the browser.
	 *
	 * @param WC_Product|int $product Product or ID (a variation uses its parent's setting).
	 * @return float
	 */
	public static function price_for( $product ) {
		$parent_product = self::parent_product( $product );
		$override       = $parent_product ? self::normalize_price( $parent_product->get_meta( self::META_PRICE ), wc_get_price_decimals() ) : '';
		$price          = '' !== $override ? $override : self::normalize_price( get_option( self::OPT_PRICE, '' ), wc_get_price_decimals() );
		return '' === $price ? 0.0 : (float) $price;
	}

	/**
	 * Whether the owner switched samples on for this product.
	 *
	 * @param WC_Product $parent_product Parent (or simple) product.
	 * @return bool
	 */
	public static function is_switched_on( $parent_product ) {
		$choice = self::normalize_choice( $parent_product->get_meta( self::META_ENABLED ) );
		$on     = '' === $choice ? 'yes' === get_option( self::OPT_ENABLED, 'no' ) : 'yes' === $choice;

		/**
		 * Filter whether samples are switched on for a product.
		 *
		 * @since 1.0.0
		 *
		 * @param bool       $on             Whether samples are on (from the product and shop settings).
		 * @param WC_Product $parent_product Parent (or simple) product.
		 */
		return (bool) apply_filters( 'smpshelf_product_enabled', $on, $parent_product );
	}

	/**
	 * Why a product can't offer a sample, or "" when it can.
	 *
	 * @param WC_Product|int $product   Product, or its ID.
	 * @param int            $variation Variation ID for a variable product, else 0.
	 * @return string Empty string when eligible, otherwise a message for the shopper.
	 */
	public static function problem( $product, $variation = 0 ) {
		$parent_product = self::parent_product( $product );
		if ( ! $parent_product || ! in_array( $parent_product->get_type(), array( 'simple', 'variable' ), true ) || ! self::is_switched_on( $parent_product ) ) {
			return __( 'Samples are not available for this product.', 'sample-shelf-for-woocommerce' );
		}
		$target = $parent_product;
		if ( $parent_product->is_type( 'variable' ) ) {
			$target = $variation ? wc_get_product( absint( $variation ) ) : null;
			if ( ! $target || ! $target->is_type( 'variation' ) || (int) $target->get_parent_id() !== (int) $parent_product->get_id() ) {
				return __( 'Choose an option first.', 'sample-shelf-for-woocommerce' );
			}
		}
		if ( ! $target->is_purchasable() || ! $target->is_in_stock() ) {
			return __( 'This sample is not available right now.', 'sample-shelf-for-woocommerce' );
		}
		// Cut & Pack measured products need Cut & Pack 1.2+ (it lets a sample skip the size).
		if ( class_exists( 'Cutpack_Settings' ) && Cutpack_Settings::is_enabled( $parent_product ) && ! ( defined( 'CUTPACK_VERSION' ) && version_compare( CUTPACK_VERSION, '1.2.0', '>=' ) ) ) {
			return __( 'Samples are not available for this product.', 'sample-shelf-for-woocommerce' );
		}
		return '';
	}
}
