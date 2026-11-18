<?php
/**
 * The "Request a sample" button on the product page.
 *
 * @package SampleShelf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders a separate sample form beside the normal add-to-cart form. It is a form of its own
 * (never nested, never part of form.cart) so themes that send the main form by AJAX can't turn a
 * sample request into a full-price order.
 */
class Smpshelf_Frontend {

	/**
	 * Products whose form was already printed on this page.
	 *
	 * @var bool[]
	 */
	private static $printed = array();

	/**
	 * How many "Add to Cart with Options" blocks are being rendered right now. Inside that block
	 * WooCommerce fires the after-form hook INSIDE its own form, where a second form must not be printed.
	 *
	 * @var int
	 */
	private static $in_options_block = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_after_form' ) );
		add_filter( 'render_block_woocommerce/add-to-cart-form', array( __CLASS__, 'render_after_block' ), 10, 3 );
		add_filter( 'pre_render_block', array( __CLASS__, 'block_start' ), 10, 2 );
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( __CLASS__, 'render_after_options_block' ), 10, 3 );
	}

	/**
	 * Classic product templates.
	 */
	public static function render_after_form() {
		global $product;
		if ( self::$in_options_block > 0 ) {
			return;
		}
		if ( $product instanceof WC_Product ) {
			echo self::form( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside form().
		}
	}

	/**
	 * Note that an "Add to Cart with Options" block started rendering.
	 *
	 * @param string|null $pre_render Value passed through.
	 * @param array       $block      Parsed block.
	 * @return string|null
	 */
	public static function block_start( $pre_render, $block ) {
		if ( null === $pre_render && is_array( $block ) && isset( $block['blockName'] ) && 'woocommerce/add-to-cart-with-options' === $block['blockName'] ) {
			++self::$in_options_block;
		}
		return $pre_render;
	}

	/**
	 * Print the sample form after (never inside) the "Add to Cart with Options" block.
	 *
	 * @param string   $content  Block HTML.
	 * @param array    $block    Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function render_after_options_block( $content, $block = array(), $instance = null ) {
		self::$in_options_block = max( 0, self::$in_options_block - 1 );
		return self::render_after_block( $content, $block, $instance );
	}

	/**
	 * Block product templates (only when the hook above did not already print the form).
	 *
	 * @param string   $content Block HTML.
	 * @param array    $block   Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function render_after_block( $content, $block = array(), $instance = null ) {
		unset( $block );
		$post_id = ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) ) ? absint( $instance->context['postId'] ) : get_the_ID();
		$product = $post_id ? wc_get_product( $post_id ) : null;
		return $product ? $content . self::form( $product ) : $content;
	}

	/**
	 * The sample form for a product, or "" when it can't offer a sample.
	 * Identical for every visitor, so full-page caches stay correct.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function form( $product ) {
		$id = $product->get_id();
		if ( isset( self::$printed[ $id ] ) || ! is_singular( 'product' ) ) {
			return '';
		}
		$variable = $product->is_type( 'variable' );
		if ( $variable ) {
			// A variable product is checked per variation when the shopper picks one; here it only needs to be on.
			$cutpack_too_old = class_exists( 'Cutpack_Settings' ) && Cutpack_Settings::is_enabled( $product ) && ! ( defined( 'CUTPACK_VERSION' ) && version_compare( CUTPACK_VERSION, '1.2.0', '>=' ) );
			if ( ! Smpshelf_Settings::is_switched_on( $product ) || ! $product->is_in_stock() || $cutpack_too_old ) {
				return '';
			}
		} elseif ( '' !== Smpshelf_Settings::problem( $product ) ) {
			return '';
		}
		self::$printed[ $id ] = true;

		wp_enqueue_style( 'smpshelf-sample', SMPSHELF_URL . 'assets/css/sample.css', array(), SMPSHELF_VERSION );
		if ( $variable ) {
			wp_enqueue_script( 'smpshelf-sample', SMPSHELF_URL . 'assets/js/sample.js', array(), SMPSHELF_VERSION, true );
		}

		$label = Smpshelf_Settings::button_text();
		$price = Smpshelf_Settings::price_for( $product );
		if ( $price > 0 ) {
			$label .= ' (' . wp_strip_all_tags( wc_price( wc_get_price_to_display( $product, array( 'price' => $price ) ) ) ) . ')';
		}

		ob_start();
		?>
		<form class="smpshelf-sample-form" method="post" action="<?php echo esc_url( get_permalink( $id ) ); ?>"<?php echo $variable ? ' data-smpshelf-variable="1"' : ''; ?>>
			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $id ); ?>" />
			<input type="hidden" name="quantity" value="1" />
			<input type="hidden" name="smpshelf_sample" value="1" />
			<?php if ( $variable ) : ?>
				<input type="hidden" name="variation_id" value="" class="smpshelf-variation-id" />
				<span class="smpshelf-attributes"></span>
			<?php endif; ?>
			<button type="submit" class="button smpshelf-button"<?php echo $variable ? ' disabled aria-disabled="true"' : ''; ?>><?php echo esc_html( $label ); ?></button>
			<?php if ( $variable ) : ?>
				<span class="smpshelf-hint" aria-live="polite"><?php esc_html_e( 'Choose an option first.', 'sample-shelf-for-woocommerce' ); ?></span>
			<?php endif; ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
