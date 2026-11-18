<?php
/**
 * The WooCommerce > Settings > Sample Shelf tab. Loaded only inside WooCommerce's settings screens.
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab.
 */
class Smpshelf_Settings_Page extends WC_Settings_Page {

	/**
	 * Set up the tab.
	 */
	public function __construct() {
		$this->id    = 'smpshelf';
		$this->label = __( 'Sample Shelf', 'sample-shelf-for-woocommerce' );
		parent::__construct();
	}

	/**
	 * Shipping classes as a list for the select box.
	 *
	 * @return array
	 */
	private function shipping_class_options() {
		$options = array( '0' => __( 'No shipping class', 'sample-shelf-for-woocommerce' ) );
		foreach ( WC()->shipping()->get_shipping_classes() as $class ) {
			$options[ (string) $class->term_id ] = $class->name;
		}
		return $options;
	}

	/**
	 * Fields of the (only) section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		return array(
			array(
				'title' => __( 'Sample Shelf', 'sample-shelf-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Let shoppers request a sample of a product. You can switch samples on or off for each product on its edit screen.', 'sample-shelf-for-woocommerce' ),
				'id'    => 'smpshelf_options',
			),
			array(
				'title'   => __( 'Samples on by default', 'sample-shelf-for-woocommerce' ),
				'desc'    => __( 'Offer a sample on every simple and variable product (a product can still opt out).', 'sample-shelf-for-woocommerce' ),
				'id'      => Smpshelf_Settings::OPT_ENABLED,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => __( 'Sample price', 'sample-shelf-for-woocommerce' ),
				'desc'     => __( 'Price of one sample. Leave empty or 0 for free samples. Enter it the way you enter product prices (with or without tax).', 'sample-shelf-for-woocommerce' ),
				'id'       => Smpshelf_Settings::OPT_PRICE,
				'type'     => 'text',
				'default'  => '',
				'desc_tip' => true,
				'css'      => 'width: 6em;',
			),
			array(
				'title'             => __( 'Samples per order', 'sample-shelf-for-woocommerce' ),
				'desc'              => __( 'The most samples one order can contain. Each product (or variation) can be sampled once per order.', 'sample-shelf-for-woocommerce' ),
				'id'                => Smpshelf_Settings::OPT_MAX,
				'type'              => 'number',
				'default'           => Smpshelf_Settings::DEFAULT_MAX,
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => Smpshelf_Settings::HARD_MAX,
					'step' => 1,
				),
				'css'               => 'width: 6em;',
			),
			array(
				'title'   => __( 'Stock', 'sample-shelf-for-woocommerce' ),
				'desc'    => __( 'Samples reduce the product\'s stock when the order is placed. (While a sample is in the cart, and for products that are out of stock, WooCommerce still treats it like any other item: samples cannot be requested for out-of-stock products.)', 'sample-shelf-for-woocommerce' ),
				'id'      => Smpshelf_Settings::OPT_STOCK,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => __( 'Sample shipping class', 'sample-shelf-for-woocommerce' ),
				'desc'     => __( 'Shipping class given to samples, so a small sample is not charged the freight of a full pack. Samples always count as weightless.', 'sample-shelf-for-woocommerce' ),
				'id'       => Smpshelf_Settings::OPT_SHIPPING,
				'type'     => 'select',
				'default'  => '0',
				'options'  => $this->shipping_class_options(),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Button text', 'sample-shelf-for-woocommerce' ),
				'desc'     => __( 'Leave empty for "Request a sample".', 'sample-shelf-for-woocommerce' ),
				'id'       => Smpshelf_Settings::OPT_TEXT,
				'type'     => 'text',
				'default'  => '',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'smpshelf_options',
			),
		);
	}
}
