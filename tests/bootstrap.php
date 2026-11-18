<?php
/**
 * Unit-test bootstrap. Loads pure PHP classes with minimal WordPress stand-ins.
 *
 * @package SampleShelf
 */

define( 'ABSPATH', __DIR__ . '/' );

// Minimal stand-ins so Smpshelf_Settings can be tested without WordPress or WooCommerce.
$GLOBALS['smpshelf_options']  = array();
$GLOBALS['smpshelf_products'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['smpshelf_options'] ) ? $GLOBALS['smpshelf_options'][ $name ] : $default;
}
function __( $text ) {
	return $text;
}
function apply_filters( $hook, $value ) {
	return $value;
}
function absint( $n ) {
	return abs( (int) $n );
}
function wc_get_price_decimals() {
	return 2;
}
function wc_get_product( $id ) {
	return isset( $GLOBALS['smpshelf_products'][ (int) $id ] ) ? $GLOBALS['smpshelf_products'][ (int) $id ] : false;
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Tiny product stand-in.
	 */
	class WC_Product {
		public $data;

		public function __construct( array $data ) {
			$this->data = array_merge(
				array(
					'id'          => 0,
					'type'        => 'simple',
					'parent'      => 0,
					'meta'        => array(),
					'purchasable' => true,
					'in_stock'    => true,
				),
				$data
			);
		}
		public function get_id() {
			return $this->data['id'];
		}
		public function get_type() {
			return $this->data['type'];
		}
		public function is_type( $type ) {
			return $this->data['type'] === $type;
		}
		public function get_parent_id() {
			return $this->data['parent'];
		}
		public function get_meta( $key ) {
			return isset( $this->data['meta'][ $key ] ) ? $this->data['meta'][ $key ] : '';
		}
		public function is_purchasable() {
			return $this->data['purchasable'];
		}
		public function is_in_stock() {
			return $this->data['in_stock'];
		}
	}
}
