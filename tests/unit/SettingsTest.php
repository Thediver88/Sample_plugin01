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
}
