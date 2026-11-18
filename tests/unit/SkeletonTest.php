<?php
/**
 * Placeholder so the unit-test suite runs; real tests arrive with the features.
 *
 * @package SampleShelf
 */

use PHPUnit\Framework\TestCase;

/**
 * Keeps the suite green until features are added.
 */
class SkeletonTest extends TestCase {
	/**
	 * The plugin file has a matching version constant and header.
	 */
	public function test_version_header_matches_constant() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/sample-shelf-for-woocommerce.php' );
		preg_match( '/Version:\s+(\S+)/', $source, $header );
		preg_match( "/define\( 'SMPSHELF_VERSION', '([^']+)'/", $source, $constant );
		$this->assertSame( $header[1], $constant[1] );
	}
}
