<?php
/**
 * Cart, checkout and orders: the server-side half of Sample Shelf.
 *
 * A sample is its own cart line (marked in the cart item data), quantity 1, priced here from the
 * settings. Nothing the browser sends about price, quantity or eligibility is trusted.
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds, prices, limits and records samples.
 */
class Smpshelf_Cart {

	/**
	 * Cart item data key marking a sample line.
	 */
	const KEY = 'smpshelf_sample';

	/**
	 * Hidden order item meta marking a sample.
	 */
	const ORDER_META = '_smpshelf_sample';

	/**
	 * Requests approved by our own validation in this request: parent product ID => variation ID.
	 * Only these can become sample lines, so a stray query-string flag can never mark a line.
	 *
	 * @var int[]
	 */
	private static $approved = array();

	/**
	 * Product objects that are samples (the objects themselves, so an ID can't be reused by another object).
	 *
	 * @var WC_Product[]
	 */
	private static $sample_objects = array();

	/**
	 * Product ID of the sample line added in this request (for the "added to cart" message).
	 *
	 * @var int
	 */
	private static $last_added = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Runs before Cut & Pack's own check (priority 10) so its skip filter sees our approval.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 5, 6 );
		add_filter( 'woocommerce_add_to_cart_quantity', array( __CLASS__, 'quantity' ), 20, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_item_data' ), 10, 3 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'line_added' ), 10, 2 );
		add_filter( 'woocommerce_order_again_cart_item_data', array( __CLASS__, 'order_again_data' ), 10, 2 );
		add_filter( 'wc_add_to_cart_message_html', array( __CLASS__, 'added_message' ), 10, 2 );

		// Cut & Pack 1.2+: a sample needs no size, and must not be removed from the cart.
		add_filter( 'cutpack_skip_add_to_cart', array( __CLASS__, 'skip_cutpack_add' ), 10, 2 );
		add_filter( 'cutpack_skip_cart_item', array( __CLASS__, 'skip_cutpack_item' ), 10, 2 );

		// Price, weight and shipping: set when the line is created and re-applied before every total.
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'prepare_item' ), 30 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'prepare_item' ), 30 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prepare_all' ), 1000 );
		foreach ( array( 'price', 'regular_price', 'sale_price', 'weight', 'length', 'width', 'height', 'shipping_class_id' ) as $prop ) {
			add_filter( 'woocommerce_product_get_' . $prop, array( __CLASS__, 'override_prop' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_product_variation_get_' . $prop, array( __CLASS__, 'override_prop' ), PHP_INT_MAX, 2 );
		}

		// Quantity is always 1.
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'quantity_field' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'update_validation' ), 10, 4 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'store_api_editable' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'store_api_one' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( __CLASS__, 'store_api_one' ), 10, 3 );

		// Re-check everything at cart and checkout time (classic and block).
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'remove_ineligible' ), 30 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'check_store_api' ), 10, 2 );

		// Stock, coupons, labels and the order record.
		add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'order_item_stock_quantity' ), 10, 3 );
		add_filter( 'woocommerce_prevent_adjust_line_item_product_stock', array( __CLASS__, 'prevent_admin_stock_adjust' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'coupon_valid' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item' ), 10, 3 );
	}

	/**
	 * Whether a line (or request data) is a sample.
	 *
	 * @param mixed $item Cart item array.
	 * @return bool
	 */
	public static function is_sample_item( $item ) {
		return is_array( $item ) && ! empty( $item[ self::KEY ] );
	}

	/**
	 * Whether this add-to-cart request asks for a sample.
	 *
	 * @param mixed $cart_item_data Cart item data passed to the validation filter (order again sets the key here).
	 * @return bool
	 */
	private static function is_sample_request( $cart_item_data = array() ) {
		if ( is_array( $cart_item_data ) && ! empty( $cart_item_data[ self::KEY ] ) ) {
			return true;
		}
		return isset( $_REQUEST[ self::KEY ] ) && '1' === sanitize_text_field( wp_unslash( $_REQUEST[ self::KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce add-to-cart requests carry no nonce (so full-page caching works); the flag is only honoured after server-side validation below.
	}

	/**
	 * Sample lines currently in the cart.
	 *
	 * @return array Cart key => cart item.
	 */
	private static function sample_lines() {
		$lines = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $lines;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( self::is_sample_item( $item ) ) {
				$lines[ $key ] = $item;
			}
		}
		return $lines;
	}

	/**
	 * Validate a sample request. Refuses anything that is not an eligible, new sample within the limit.
	 *
	 * @param bool  $passed         Whether earlier checks passed.
	 * @param int   $product_id     Product ID.
	 * @param int   $quantity       Quantity sent (ignored).
	 * @param int   $variation_id   Variation ID.
	 * @param array $variations     Posted attributes (unused).
	 * @param array $cart_item_data Cart item data.
	 * @return bool
	 */
	public static function validate( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		unset( $quantity, $variations );
		if ( ! self::is_sample_request( $cart_item_data ) ) {
			return $passed;
		}
		if ( ! $passed ) {
			return false;
		}
		// Samples are requested through the sample form only, never through the REST / Store API add-item route.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			wc_add_notice( __( 'Samples are not available for this product.', 'sample-shelf-for-woocommerce' ), 'error' );
			return false;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$product      = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variation' ) ) { // ?add-to-cart=<variation id>.
			$variation_id = $product_id;
			$product_id   = (int) $product->get_parent_id();
		}

		$problem = Smpshelf_Settings::problem( $product_id, $variation_id );
		if ( '' === $problem ) {
			foreach ( self::sample_lines() as $line ) {
				if ( (int) $line['product_id'] === $product_id && (int) $line['variation_id'] === $variation_id ) {
					$problem = __( 'This sample is already in your cart.', 'sample-shelf-for-woocommerce' );
					break;
				}
			}
		}
		if ( '' === $problem && count( self::sample_lines() ) >= Smpshelf_Settings::max_per_order() ) {
			$problem = sprintf(
				/* translators: %d: maximum number of samples per order */
				_n( 'You can request up to %d sample per order.', 'You can request up to %d samples per order.', Smpshelf_Settings::max_per_order(), 'sample-shelf-for-woocommerce' ),
				Smpshelf_Settings::max_per_order()
			);
		}

		if ( '' !== $problem ) {
			wc_add_notice( $problem, 'error' );
			return false;
		}

		self::$approved[ $product_id ] = $variation_id;
		return true;
	}

	/**
	 * Whether the product in this request was approved as a sample.
	 *
	 * @param int $product_id Parent product ID.
	 * @return bool
	 */
	private static function is_approved( $product_id ) {
		return isset( self::$approved[ absint( $product_id ) ] );
	}

	/**
	 * A sample is always one piece, whatever quantity the browser sent.
	 *
	 * @param int $quantity   Quantity sent.
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function quantity( $quantity, $product_id ) {
		return self::is_approved( $product_id ) ? 1 : $quantity;
	}

	/**
	 * Mark the cart line (only for requests our validation approved).
	 *
	 * @param array $data       Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function add_item_data( $data, $product_id ) {
		if ( self::is_approved( $product_id ) ) {
			$data[ self::KEY ] = 1;
		}
		return $data;
	}

	/**
	 * The line is in the cart: the approval is used up, so it can't leak to another line of the same product
	 * (for example in an "order again" loop).
	 *
	 * @param string $cart_item_key Cart item key (unused).
	 * @param int    $product_id    Product ID.
	 */
	public static function line_added( $cart_item_key, $product_id ) {
		unset( $cart_item_key );
		$product_id = absint( $product_id );
		if ( isset( self::$approved[ $product_id ] ) ) {
			self::$last_added = $product_id;
			unset( self::$approved[ $product_id ] );
		}
	}

	/**
	 * "Order again" on a sample order puts a sample back (it still goes through validation and the limits).
	 *
	 * @param array                 $data Cart item data.
	 * @param WC_Order_Item_Product $item Order item.
	 * @return array
	 */
	public static function order_again_data( $data, $item ) {
		if ( $item instanceof WC_Order_Item && 'yes' === $item->get_meta( self::ORDER_META ) ) {
			$data[ self::KEY ] = 1;
		}
		return $data;
	}

	/**
	 * Cut & Pack: this request is an approved sample, so no size is needed.
	 *
	 * @param bool $skip       Current value.
	 * @param int  $product_id Parent product ID.
	 * @return bool
	 */
	public static function skip_cutpack_add( $skip, $product_id ) {
		return $skip || self::is_approved( $product_id );
	}

	/**
	 * Cut & Pack: leave sample lines in the cart.
	 *
	 * @param bool  $skip Current value.
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function skip_cutpack_item( $skip, $item ) {
		return $skip || self::is_sample_item( $item );
	}

	/**
	 * Replace the "added to cart" message for a sample.
	 *
	 * @param string $message  Message HTML.
	 * @param array  $products Product ID => quantity.
	 * @return string
	 */
	public static function added_message( $message, $products ) {
		if ( ! is_array( $products ) || 1 !== count( $products ) || ! self::$last_added || (int) key( $products ) !== self::$last_added ) {
			return $message;
		}
		$product = wc_get_product( (int) key( $products ) );
		if ( ! $product ) {
			return $message;
		}
		return sprintf(
			'<a href="%1$s" class="button wc_forward">%2$s</a> %3$s',
			esc_url( wc_get_cart_url() ),
			esc_html__( 'View cart', 'sample-shelf-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: %s: product name */
					__( 'A sample of "%s" has been added to your cart.', 'sample-shelf-for-woocommerce' ),
					$product->get_name()
				)
			)
		);
	}

	/**
	 * Make a cart line's product object a sample: remember it so the price/weight/shipping filters apply.
	 *
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function prepare_item( $item ) {
		if ( ! self::is_sample_item( $item ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
			return $item;
		}
		self::$sample_objects[ spl_object_id( $item['data'] ) ] = $item['data'];
		$item['data']->set_price( self::sample_price( $item ) );
		$item['quantity'] = 1;
		return $item;
	}

	/**
	 * Re-apply sample prices before totals are calculated (runs last, after other price plugins).
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function prepare_all( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( self::is_sample_item( $item ) ) {
				$cart->cart_contents[ $key ] = self::prepare_item( $item );
			}
		}
	}

	/**
	 * Price of the sample on a cart line.
	 *
	 * @param array $item Cart item.
	 * @return float
	 */
	private static function sample_price( $item ) {
		return Smpshelf_Settings::price_for( $item['product_id'] );
	}

	/**
	 * Product getter filter: samples get the sample price, no weight/dimensions and the sample shipping class,
	 * even if another plugin changed the price in between.
	 *
	 * @param mixed      $value   Value read from the product.
	 * @param WC_Product $product Product object.
	 * @return mixed
	 */
	public static function override_prop( $value, $product ) {
		if ( empty( self::$sample_objects ) || ! is_object( $product ) || ! isset( self::$sample_objects[ spl_object_id( $product ) ] ) ) {
			return $value;
		}
		switch ( current_filter() ) {
			case 'woocommerce_product_get_price':
			case 'woocommerce_product_variation_get_price':
			case 'woocommerce_product_get_regular_price':
			case 'woocommerce_product_variation_get_regular_price':
				return (string) Smpshelf_Settings::price_for( $product );
			case 'woocommerce_product_get_sale_price':
			case 'woocommerce_product_variation_get_sale_price':
				return '';
			case 'woocommerce_product_get_shipping_class_id':
			case 'woocommerce_product_variation_get_shipping_class_id':
				return Smpshelf_Settings::shipping_class_id();
			default: // Weight and dimensions.
				return '';
		}
	}

	/**
	 * Classic cart: show a fixed "1" instead of a quantity box.
	 *
	 * @param string $html Quantity field HTML.
	 * @param string $key  Cart item key.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public static function quantity_field( $html, $key, $item ) {
		if ( ! self::is_sample_item( $item ) ) {
			return $html;
		}
		return '1<input type="hidden" name="' . esc_attr( 'cart[' . $key . '][qty]' ) . '" value="1" />';
	}

	/**
	 * Classic cart update: a sample can be removed, never increased.
	 *
	 * @param bool   $passed   Whether earlier checks passed.
	 * @param string $key      Cart item key.
	 * @param array  $values   Cart item.
	 * @param mixed  $quantity New quantity.
	 * @return bool
	 */
	public static function update_validation( $passed, $key, $values, $quantity ) {
		unset( $key );
		if ( self::is_sample_item( $values ) && (int) $quantity > 1 ) {
			wc_add_notice( __( 'Only one of each sample can be ordered.', 'sample-shelf-for-woocommerce' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Block cart: no quantity selector on samples.
	 *
	 * @param bool       $editable  Current value.
	 * @param WC_Product $product   Product.
	 * @param array|null $cart_item Cart item.
	 * @return bool
	 */
	public static function store_api_editable( $editable, $product, $cart_item = null ) {
		unset( $product );
		return self::is_sample_item( $cart_item ) ? false : $editable;
	}

	/**
	 * Block cart: minimum and maximum quantity of a sample is 1.
	 *
	 * @param int        $value     Current value.
	 * @param WC_Product $product   Product.
	 * @param array|null $cart_item Cart item.
	 * @return int
	 */
	public static function store_api_one( $value, $product, $cart_item = null ) {
		unset( $product );
		return self::is_sample_item( $cart_item ) ? 1 : $value;
	}

	/**
	 * Drop sample lines whose product can no longer offer one (switched off, out of stock, deleted).
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function remove_ineligible( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! self::is_sample_item( $item ) || '' === Smpshelf_Settings::problem( $item['product_id'], $item['variation_id'] ) ) {
				continue;
			}
			$name = $item['data'] instanceof WC_Product ? $item['data']->get_name() : '';
			$cart->remove_cart_item( $key );
			wc_add_notice(
				/* translators: %s: product name */
				sprintf( __( 'The sample of "%s" is no longer available and was removed from your cart.', 'sample-shelf-for-woocommerce' ), $name ),
				'notice'
			);
		}
	}

	/**
	 * Too many samples (for example the shop lowered the limit): an error that blocks checkout.
	 *
	 * @return string Error message, or "" when fine.
	 */
	private static function limit_error() {
		$max = Smpshelf_Settings::max_per_order();
		if ( count( self::sample_lines() ) <= $max ) {
			return '';
		}
		return sprintf(
			/* translators: %d: maximum number of samples per order */
			_n( 'You can request up to %d sample per order. Please remove some samples.', 'You can request up to %d samples per order. Please remove some samples.', $max, 'sample-shelf-for-woocommerce' ),
			$max
		);
	}

	/**
	 * Classic cart and checkout.
	 */
	public static function check_cart() {
		$error = self::limit_error();
		if ( '' !== $error ) {
			wc_add_notice( $error, 'error' );
		}
	}

	/**
	 * Block cart and checkout (Store API).
	 *
	 * @param WP_Error $errors Errors.
	 * @param WC_Cart  $cart   Cart.
	 */
	public static function check_store_api( $errors, $cart ) {
		unset( $cart );
		$error = self::limit_error();
		if ( '' !== $error && $errors instanceof WP_Error ) {
			$errors->add( 'smpshelf_limit', $error );
		}
	}

	/**
	 * Samples are not taken off the product's stock when the order is placed, unless the owner turned that on.
	 * (While a sample sits in the cart, WooCommerce still counts it in its in-cart stock check.)
	 *
	 * @param int                   $quantity Quantity to reduce.
	 * @param WC_Order              $order    Order.
	 * @param WC_Order_Item_Product $item     Order item.
	 * @return int
	 */
	public static function order_item_stock_quantity( $quantity, $order, $item ) {
		unset( $order );
		if ( $item instanceof WC_Order_Item && 'yes' === $item->get_meta( self::ORDER_META ) && ! Smpshelf_Settings::reduces_stock() ) {
			return 0;
		}
		return $quantity;
	}

	/**
	 * Editing a sample's order item in the admin must not take stock either.
	 *
	 * @param bool                  $prevent Current value.
	 * @param WC_Order_Item_Product $item    Order item.
	 * @return bool
	 */
	public static function prevent_admin_stock_adjust( $prevent, $item ) {
		if ( $item instanceof WC_Order_Item && 'yes' === $item->get_meta( self::ORDER_META ) && ! Smpshelf_Settings::reduces_stock() ) {
			return true;
		}
		return $prevent;
	}

	/**
	 * Percent and fixed-product coupons never apply to sample lines. (A fixed-cart coupon is taken off the whole cart by WooCommerce.)
	 *
	 * @param bool       $valid   Current value.
	 * @param WC_Product $product Product.
	 * @param WC_Coupon  $coupon  Coupon.
	 * @param array      $values  Cart item.
	 * @return bool
	 */
	public static function coupon_valid( $valid, $product, $coupon, $values ) {
		unset( $product, $coupon );
		return self::is_sample_item( $values ) ? false : $valid;
	}

	/**
	 * Label the line in the cart and checkout (classic and block).
	 *
	 * @param array $item_data Existing lines.
	 * @param array $item      Cart item.
	 * @return array
	 */
	public static function display_item_data( $item_data, $item ) {
		if ( self::is_sample_item( $item ) ) {
			$item_data[] = array(
				'key'   => __( 'Type', 'sample-shelf-for-woocommerce' ),
				'value' => __( 'Sample', 'sample-shelf-for-woocommerce' ),
			);
		}
		return $item_data;
	}

	/**
	 * Record the sample on the order (admin screen, emails, packing slips).
	 *
	 * @param WC_Order_Item_Product $order_item Order item.
	 * @param string                $cart_key   Cart item key.
	 * @param array                 $values     Cart item.
	 */
	public static function save_order_item( $order_item, $cart_key, $values ) {
		unset( $cart_key );
		if ( ! self::is_sample_item( $values ) ) {
			return;
		}
		$order_item->add_meta_data( self::ORDER_META, 'yes', true );
		$order_item->add_meta_data( __( 'Type', 'sample-shelf-for-woocommerce' ), __( 'Sample', 'sample-shelf-for-woocommerce' ), true );
	}
}
