<?php
/**
 * The minified assets match their sources.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Without SCRIPT_DEBUG the site loads the .min files, so a source edited without running build-assets.sh would
 * ship unchanged. The build writes a hash of every source into build-manifest.json; this fails when one differs.
 */
final class AssetsBuildTest extends TestCase {

	/**
	 * The plugin folder, with a trailing slash.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Resets the state before each test.
	 */
	protected function setUp(): void {
		$this->root = dirname( __DIR__, 2 ) . '/';
	}

	/**
	 * What the build recorded.
	 *
	 * @return array<string, string>
	 */
	private function manifest(): array {
		$json = json_decode( (string) file_get_contents( $this->root . 'assets/build-manifest.json' ), true );

		$this->assertTrue( is_array( $json ), 'assets/build-manifest.json is missing or not valid JSON; run ./build-assets.sh.' );

		return $json;
	}

	/**
	 * Every stylesheet and script the build covers, as the script lists them.
	 *
	 * @return array<int, string>
	 */
	private function sources(): array {
		$found    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->root . 'assets', \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			$path = str_replace( $this->root, '', $file->getPathname() );

			if ( ! preg_match( '/\.(css|js)$/', $path ) || false !== strpos( $path, '.min.' ) || 0 === strpos( $path, 'assets/js/vendor/' ) ) {
				continue;
			}

			$found[] = $path;
		}

		sort( $found );

		return $found;
	}

	/**
	 * Every source is in the manifest.
	 */
	public function test_every_source_is_in_the_manifest(): void {
		$this->assertSame( $this->sources(), array_keys( $this->manifest() ), 'A source was added or removed without running ./build-assets.sh.' );
	}

	/**
	 * No source changed since the build.
	 */
	public function test_no_source_changed_since_the_build(): void {
		$stale = array();

		foreach ( $this->manifest() as $path => $hash ) {
			if ( hash_file( 'sha256', $this->root . $path ) !== $hash ) {
				$stale[] = $path;
			}
		}

		$this->assertSame( array(), $stale, 'Edited since the last build: run ./build-assets.sh.' );
	}

	/**
	 * Every source has a min file.
	 */
	public function test_every_source_has_a_min_file(): void {
		$missing = array();

		foreach ( array_keys( $this->manifest() ) as $path ) {
			if ( ! is_file( $this->root . preg_replace( '/\.(css|js)$/', '.min.$1', $path ) ) ) {
				$missing[] = $path;
			}
		}

		$this->assertSame( array(), $missing );
	}
}
