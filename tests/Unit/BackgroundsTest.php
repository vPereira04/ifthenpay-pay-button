<?php
/**
 * Golden-hash check that the generated backgrounds stay byte-for-byte the same.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Checkout\Backgrounds;
use PHPUnit\Framework\TestCase;

/**
 * The hashes were taken from CheckoutLook before the drawing moved out into Backgrounds. buttons.js draws the same
 * SVGs for the preview, so a changed digit here is a visible mismatch there: update them only on purpose.
 */
final class BackgroundsTest extends TestCase {

	private const HASHES = array(
		'crystals60-0'    => '583f0112ea8de6fb0d12845959733c4e',
		'waves60-0'       => '613bde24344cfdbdad676c975fad0323',
		'bokeh60-0'       => '5fae4106d38aee6010d0bc7c83f29976',
		'crystals60-35'   => '9e9776386ab7fb40559e44e359f814d9',
		'waves60-35'      => 'a833c5aca7658c3a5ac7a24600340803',
		'bokeh60-35'      => 'cab3b9b53fb53f4af1907ac4af929207',
		'crystals60-100'  => '9f1de5d09f32545e464123c6ff7b044c',
		'waves60-100'     => '9bbd04d414d7899a12d187fb1b82b01b',
		'bokeh60-100'     => 'dc82dbc7a109cf7db2dff40abcf15c4c',
		'crystals120-0'   => '2e571dea4593fef104f5accc0e3d1a6e',
		'waves120-0'      => '1e880210943782a9018dc3c2b68c686e',
		'bokeh120-0'      => 'afb9d364981b0b9b6e422fd039233298',
		'crystals120-35'  => '133a04ca527b6d9ae0054cb5a210472f',
		'waves120-35'     => 'e39318b139853ab42d968535110232b8',
		'bokeh120-35'     => 'ef06ed85019b7a4b8a4d5b856c0db787',
		'crystals120-100' => '8094ac546e90b443cb477a55c50bb52b',
		'waves120-100'    => '73a245b0d6e3d5034d80290aa6bbde45',
		'bokeh120-100'    => '921ce0b688df1c1f381058e958021a69',
		'crystals200-0'   => '123eda3e905cc2e4b5f63a71c66d3ed9',
		'waves200-0'      => '4d56bd1381eb4f7ad22964b59bf45a8a',
		'bokeh200-0'      => 'aca7916af79727930ffaa9109fe5a9a5',
		'crystals200-35'  => '9e543b4dd9959030c0d8f0b104259ad0',
		'waves200-35'     => '62b0e78f659e62c4427a740a19e41835',
		'bokeh200-35'     => '124152bff9e118e779bedbc024889827',
		'crystals200-100' => '383e00f85fdf028fbc0475773e143434',
		'waves200-100'    => '17478a10425365145870947a65b01960',
		'bokeh200-100'    => '37a5e8496f46439d1c7083f073a172a9',
		'liquid400'       => '65604bb411d3a662d14fa857932d06fb',
		'mesh400'         => 'bf708e3ecddfcaaba324e94e51ea29f8',
		'tide400'         => '3579d25602aa43faa7e305da78756d40',
		'liquid401'       => 'a216b554e862ad9fd802a38913492b8e',
		'mesh401'         => 'f4eb34c1901bdbd851e6e3426622bee3',
		'tide401'         => '6dbce6af921f6b3e584e46e92dc3936d',
		'liquid1400'      => 'a62b27430a572ad448614f5788e0e499',
		'mesh1400'        => '378556addc1fbc0e68f7af1abb19f2a2',
		'tide1400'        => 'f4fcb6116ef90039627772cb6ac028ef',
		'liquid1401'      => '9688d114a152811c9360edc2dca31e16',
		'mesh1401'        => '12f39d034f66751e99a226985c5c26c1',
		'tide1401'        => '739091c3d6ca7b07c3923bad6527784b',
		'liquid3000'      => 'ba7e43d038f93603bcec4d1ef7d12925',
		'mesh3000'        => 'ac881a9807bd6c0649f931065927c955',
		'tide3000'        => 'e06405c3cfea1f27cbb573075529b4b6',
		'liquid3001'      => 'a0391dcd01a445b346034d8628b4d7c8',
		'mesh3001'        => 'da37cc152da8e04f5e87ac89b6bc0a5c',
		'tide3001'        => 'b7f0e6282644a12579a4e49ca60fb8bd',
	);

	/**
	 * Every generator, over a spread of sizes and strengths, against its recorded hash.
	 */
	public function test_output_matches_the_recorded_hashes(): void {
		$actual = array();

		foreach ( array( 60, 120, 200 ) as $size ) {
			foreach ( array( 0, 35, 100 ) as $strength ) {
				$actual[ "crystals{$size}-{$strength}" ] = md5( Backgrounds::crystals( $size, $strength ) );
				$actual[ "waves{$size}-{$strength}" ]    = md5( Backgrounds::waves( $size, $strength ) );
				$actual[ "bokeh{$size}-{$strength}" ]    = md5( Backgrounds::bokeh( $size, $strength, '#112233', '#aabbcc' ) );
			}
		}

		foreach ( array( 40, 140, 300 ) as $size ) {
			foreach ( array( false, true ) as $still ) {
				$actual[ 'liquid' . $size . (int) $still ] = md5( Backgrounds::liquid( $size, '#112233', '#aabbcc', $still ) );
				$actual[ 'mesh' . $size . (int) $still ]   = md5( Backgrounds::mesh( $size, '#112233', '#aabbcc', $still ) );
				$actual[ 'tide' . $size . (int) $still ]   = md5( Backgrounds::tide( $size, '#112233', '#aabbcc', $still ) );
			}
		}

		$this->assertSame( self::HASHES, $actual );
	}

	/**
	 * Each one is a CSS url() holding an encoded SVG, with nothing that would break out of it.
	 */
	public function test_output_is_an_encoded_svg_url(): void {
		$svg = Backgrounds::crystals( 100, 50 );

		$this->assertStringStartsWith( 'url("data:image/svg+xml,%3Csvg', $svg );
		$this->assertStringEndsWith( '")', $svg );
		$this->assertStringNotContainsString( '<', $svg );
		$this->assertStringNotContainsString( '#', $svg );
	}
}
