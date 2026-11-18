<?php
/**
 * Tests for the pure input-cleaning helpers.
 *
 * @package SampleShelf
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-smpshelf-settings.php';

/**
 * Settings input cleaning.
 */
class SettingsTest extends TestCase {

	/**
	 * Prices accept comma or dot and are never negative.
	 */
	public function test_normalize_price() {
		$this->assertSame( '2.50', Smpshelf_Settings::normalize_price( '2,5' ) );
		$this->assertSame( '2.50', Smpshelf_Settings::normalize_price( ' 2.5 ' ) );
		$this->assertSame( '0.00', Smpshelf_Settings::normalize_price( '-4' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_price( '' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_price( 'free' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_price( array( '1' ) ) );
		$this->assertSame( '3', Smpshelf_Settings::normalize_price( '2.6', 0 ) );
		$this->assertSame( '1.235', Smpshelf_Settings::normalize_price( '1.2345', 3 ) );
	}

	/**
	 * The per-order limit stays between 1 and 50.
	 */
	public function test_normalize_max() {
		$this->assertSame( 5, Smpshelf_Settings::normalize_max( '' ) );
		$this->assertSame( 5, Smpshelf_Settings::normalize_max( 'abc' ) );
		$this->assertSame( 1, Smpshelf_Settings::normalize_max( '0' ) );
		$this->assertSame( 1, Smpshelf_Settings::normalize_max( -3 ) );
		$this->assertSame( 7, Smpshelf_Settings::normalize_max( '7' ) );
		$this->assertSame( 50, Smpshelf_Settings::normalize_max( 9999 ) );
	}

	/**
	 * Only yes/no are accepted per product; anything else means "use the shop default".
	 */
	public function test_normalize_choice() {
		$this->assertSame( 'yes', Smpshelf_Settings::normalize_choice( 'yes' ) );
		$this->assertSame( 'no', Smpshelf_Settings::normalize_choice( 'no' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_choice( '' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_choice( 'maybe' ) );
		$this->assertSame( '', Smpshelf_Settings::normalize_choice( array( 'yes' ) ) );
	}

	/**
	 * Start every test from a clean shop.
	 */
	public function setUp(): void {
		$GLOBALS['smpshelf_options']  = array();
		$GLOBALS['smpshelf_products'] = array();
	}

	/**
	 * Add a product to the fake shop.
	 *
	 * @param array $data Product data.
	 * @return WC_Product
	 */
	private function product( array $data ) {
		$product                                  = new WC_Product( $data );
		$GLOBALS['smpshelf_products'][ $product->get_id() ] = $product;
		return $product;
	}

	/**
	 * Price: product override, else shop price, else free; variations use the parent's setting.
	 */
	public function test_price_for() {
		$plain = $this->product( array( 'id' => 1 ) );
		$this->assertEqualsWithDelta( 0.0, Smpshelf_Settings::price_for( $plain ), 0.0001 );

		$GLOBALS['smpshelf_options']['smpshelf_price'] = '2,5';
		$this->assertEqualsWithDelta( 2.5, Smpshelf_Settings::price_for( $plain ), 0.0001 );

		$over = $this->product( array( 'id' => 2, 'meta' => array( '_smpshelf_price' => '0' ) ) );
		$this->assertEqualsWithDelta( 0.0, Smpshelf_Settings::price_for( $over ), 0.0001 ); // 0 = free beats the shop price.

		$parent    = $this->product( array( 'id' => 3, 'type' => 'variable', 'meta' => array( '_smpshelf_price' => '4' ) ) );
		$variation = $this->product( array( 'id' => 4, 'type' => 'variation', 'parent' => 3 ) );
		$this->assertEqualsWithDelta( 4.0, Smpshelf_Settings::price_for( $variation ), 0.0001 );
		$this->assertEqualsWithDelta( 4.0, Smpshelf_Settings::price_for( 4 ), 0.0001 ); // By ID too.
		unset( $parent );
	}

	/**
	 * Eligibility rules.
	 */
	public function test_problem() {
		$off = $this->product( array( 'id' => 10 ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( $off ) ); // Off by default.

		$GLOBALS['smpshelf_options']['smpshelf_enabled_default'] = 'yes';
		$this->assertSame( '', Smpshelf_Settings::problem( $off ) );

		$opted_out = $this->product( array( 'id' => 11, 'meta' => array( '_smpshelf_enabled' => 'no' ) ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( $opted_out ) );

		$GLOBALS['smpshelf_options']['smpshelf_enabled_default'] = 'no';
		$opted_in = $this->product( array( 'id' => 12, 'meta' => array( '_smpshelf_enabled' => 'yes' ) ) );
		$this->assertSame( '', Smpshelf_Settings::problem( $opted_in ) );

		$grouped = $this->product( array( 'id' => 13, 'type' => 'grouped', 'meta' => array( '_smpshelf_enabled' => 'yes' ) ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( $grouped ) );

		$sold_out = $this->product( array( 'id' => 14, 'in_stock' => false, 'meta' => array( '_smpshelf_enabled' => 'yes' ) ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( $sold_out ) );

		$hidden = $this->product( array( 'id' => 15, 'purchasable' => false, 'meta' => array( '_smpshelf_enabled' => 'yes' ) ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( $hidden ) );
	}

	/**
	 * A variable product needs one of its own variations.
	 */
	public function test_problem_variable() {
		$this->product( array( 'id' => 20, 'type' => 'variable', 'meta' => array( '_smpshelf_enabled' => 'yes' ) ) );
		$this->product( array( 'id' => 21, 'type' => 'variation', 'parent' => 20 ) );
		$this->product( array( 'id' => 22, 'type' => 'variation', 'parent' => 99 ) ); // Someone else's variation.
		$this->product( array( 'id' => 23, 'type' => 'variation', 'parent' => 20, 'in_stock' => false ) );

		$this->assertTrue( '' !== Smpshelf_Settings::problem( 20 ) ); // No option chosen.
		$this->assertSame( '', Smpshelf_Settings::problem( 20, 21 ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( 20, 22 ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( 20, 23 ) );
		$this->assertTrue( '' !== Smpshelf_Settings::problem( 20, 12345 ) );
	}
}
