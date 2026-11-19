<?php
/**
 * The demo page: a stand-in website with the pay button on it, to try the button and its checkout.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\SchemeControl;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A made-up page with the real button in the middle of it. Clicking the button opens the real checkout, so the
 * button and the checkout can both be tried in one go. A saved button takes a real payment, but marked as a test (TEST- reference).
 *
 * It shows a saved button (a link), or the editor's unsaved form (the editor posts it to a frame), so what's on
 * screen is what the editor holds right now.
 */
class DemoPage {

	/**
	 * The admin-ajax action.
	 */
	public const ACTION = 'iftp_btn_demo';

	/**
	 * The nonce action.
	 */
	private const NONCE = 'iftp_btn_demo';

	/**
	 * Stylesheet and script handles.
	 */
	private const HANDLE = 'iftp-btn-demo';

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Sets up the page.
	 *
	 * @param ButtonRepository $buttons Buttons storage.
	 */
	public function __construct( ButtonRepository $buttons ) {
		$this->buttons = $buttons;
	}

	/**
	 * Hooks the endpoint.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'render' ) );
	}

	/**
	 * Where the demo page for a button is. A saved button opens as saved; the editor posts its form here instead.
	 *
	 * @param int $id The button's ID, or 0 for one that isn't saved yet.
	 */
	public static function url( int $id = 0 ): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'button'   => $id,
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * The inline style that dresses the made-up site like the button: its colours, corner radius and border weight,
	 * so a merchant sees how the button sits on a site that looks like it. Only a custom look has them; the theme's
	 * and "my own code" leave the site as it was.
	 *
	 * @param array<string, mixed> $design The button's design, sanitized.
	 */
	private static function accent_style( array $design ): string {
		if ( ButtonDesign::SOURCE_CUSTOM !== $design['source'] ) {
			return '';
		}

		$colors = array( (string) $design['color'], ButtonDesign::second_color( $design ) );

		if ( 'spectrum' === $design['variant'] && is_array( $design['stops'] ) && count( $design['stops'] ) > 1 ) {
			$colors = array_map(
				static fn( array $stop ): string => (string) $stop['color'],
				array_values( $design['stops'] )
			);
		}

		$colors = array_values( array_filter( array_map( array( ButtonDesign::class, 'hex' ), $colors ), static fn( string $hex ): bool => '' !== $hex ) );

		if ( array() === $colors ) {
			return '';
		}

		$style = sprintf(
			'--demo-a:%1$s;--demo-c:%2$s;--demo-on:%3$s;--demo-r:%4$dpx;--demo-bw:%5$dpx',
			$colors[0],
			$colors[ count( $colors ) - 1 ],
			ButtonDesign::on_color( $colors[0] ),
			min( 28, max( 0, (int) $design['radius'] ) ),
			min( 3, max( 1, (int) $design['border_width'] ) )
		);

		// With more than two colours the middle one is set too; with two, the CSS blends them.
		if ( count( $colors ) > 2 ) {
			$style .= sprintf( ';--demo-b:%s', $colors[ intdiv( count( $colors ) - 1, 2 ) ] );
		}

		return ' style="' . esc_attr( $style ) . '"';
	}

	/**
	 * Prints the page, and stops.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		if ( false === check_ajax_referer( self::NONCE, '_wpnonce', false ) ) {
			wp_die( esc_html__( 'This preview has expired. Open it again from the editor.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		$this->show( $this->button() );
	}

	/**
	 * Prints the demo page for a button, and stops. The editor, the list and the AI studio all preview through it, so
	 * every preview is the same page: the button on a made-up site, and the checkout once it is clicked.
	 *
	 * @param Button $button The button to show.
	 */
	public function show( Button $button ): void {
		header( 'X-Robots-Tag: noindex, nofollow' );
		nocache_headers();

		$renderer = new ButtonRenderer();

		ButtonRenderer::enqueue();
		wp_register_style( self::HANDLE, IFTP_BTN_URL . Plugin::asset_path( 'assets/css/demo.css' ), array(), Plugin::asset_version( 'assets/css/demo.css' ) );
		wp_register_script( self::HANDLE, IFTP_BTN_URL . Plugin::asset_path( 'assets/js/demo.js' ), array( Plugin::SCHEME_HANDLE ), Plugin::asset_version( 'assets/js/demo.js' ), true );
		// The checkout opened on this page follows the colour mode picked here too.
		wp_add_inline_script( Plugin::SCHEME_HANDLE, 'window.iftpBtnSchemeSelf = true;', 'before' );

		$site    = (string) get_bloginfo( 'name' );
		$tagline = (string) get_bloginfo( 'description' );
		$back    = $button->id > 0 ? ButtonsPage::edit_url( $button->id ) : ButtonsPage::list_url();
		$menu    = array( __( 'Home', 'ifthenpay-pay-button' ), __( 'About', 'ifthenpay-pay-button' ), __( 'Blog', 'ifthenpay-pay-button' ), __( 'Contact', 'ifthenpay-pay-button' ) );
		$title   = '' !== $button->title ? $button->title : __( 'Your pay button', 'ifthenpay-pay-button' );
		$accent  = self::accent_style( ButtonDesign::for_button( $button, new CheckoutSettings() ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( sprintf( /* translators: %s: the button's title */ __( 'Demo · %s', 'ifthenpay-pay-button' ), $title ) ); ?></title>
		<?php
		$renderer->with_scenes();
		// The head prints before the button is rendered, so its own CSS has to be taken in first or the stylesheet goes out without it.
		$renderer->register_code( $button );
		wp_add_inline_style( ButtonRenderer::STYLE_HANDLE, $renderer->code_css() );
		wp_print_styles( array( ButtonRenderer::STYLE_HANDLE, self::HANDLE ) );
		?>
</head>
<body class="iftp-btn-demo" data-theme="light">
	<div class="iftp-btn-demo__bar" role="toolbar" aria-label="<?php esc_attr_e( 'Demo toolbar', 'ifthenpay-pay-button' ); ?>">
		<span class="iftp-btn-demo__bar-item iftp-btn-demo__bar-logo" aria-hidden="true">
			<svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M20 10c0 5.5-4.5 10-10 10S0 15.5 0 10 4.5 0 10 0s10 4.5 10 10zM1 10c0 3.6 2.1 6.7 5.1 8.2L1.8 6.4C1.3 7.5 1 8.7 1 10zm14.9-.6c0-1.1-.4-1.9-.7-2.5-.5-.7-.9-1.4-.9-2.1 0-.8.6-1.6 1.5-1.6h.1C14.4 1.8 12.3 1 10 1 6.9 1 4.1 2.6 2.5 5.1h.5c.9 0 2.3-.1 2.3-.1.5 0 .5.7.1.7 0 0-.5.1-1 .1l3.2 9.6 1.9-5.8-1.4-3.8c-.5 0-.9-.1-.9-.1-.5 0-.4-.7.1-.7 0 0 1.4.1 2.2.1.9 0 2.3-.1 2.3-.1.5 0 .5.7 0 .7 0 0-.5.1-1 .1l3.2 9.5.9-2.9c.4-1.2.6-2.1.6-2.8zm-5.7 1.2l-2.7 7.8c.8.2 1.6.4 2.5.4 1 0 2-.2 2.9-.5l-.1-.1-2.6-7.6zm7.8-5.1c.1.3.1.6.1 1 0 1-.2 2.1-.8 3.5l-2.7 7.8c2.6-1.5 4.4-4.4 4.4-7.6 0-1.6-.4-3-1-4.2z"/></svg>
		</span>
		<span class="iftp-btn-demo__bar-item"><?php echo esc_html( '' !== $site ? $site : __( 'Your site', 'ifthenpay-pay-button' ) ); ?></span>
		<span class="iftp-btn-demo__bar-item iftp-btn-demo__bar-hide"><?php esc_html_e( 'Customize', 'ifthenpay-pay-button' ); ?></span>
		<span class="iftp-btn-demo__bar-item iftp-btn-demo__bar-hide">+ <?php esc_html_e( 'New', 'ifthenpay-pay-button' ); ?></span>
		<span class="iftp-btn-demo__badge"><?php esc_html_e( 'Demo page', 'ifthenpay-pay-button' ); ?></span>
		<span class="iftp-btn-demo__hint"><?php esc_html_e( 'Click the button to try the checkout. A payment made here is a test: its reference starts with TEST-.', 'ifthenpay-pay-button' ); ?></span>
		<?php SchemeControl::render( 'iftp-btn-demo__scheme' ); ?>
	</div>

	<div class="iftp-btn-demo__site"<?php echo $accent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from validated hex colours and whole numbers in accent_style(). ?>>
		<header class="iftp-btn-demo__header">
			<div class="iftp-btn-demo__branding">
				<span class="iftp-btn-demo__logo" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( '' !== $site ? $site : 'W', 0, 1 ) ) ); ?></span>
				<div>
					<p class="iftp-btn-demo__sitetitle"><?php echo esc_html( '' !== $site ? $site : __( 'Your site', 'ifthenpay-pay-button' ) ); ?></p>
					<p class="iftp-btn-demo__tagline"><?php echo '' !== $tagline ? esc_html( $tagline ) : wp_kses( __( 'Just <em>not</em> another WordPress site.', 'ifthenpay-pay-button' ), array( 'em' => array() ) ); ?></p>
				</div>
			</div>
			<nav class="iftp-btn-demo__menu" aria-hidden="true">
				<?php foreach ( $menu as $index => $label ) : ?>
					<span class="iftp-btn-demo__menuitem<?php echo 0 === $index ? ' is-current' : ''; ?>"><?php echo esc_html( $label ); ?></span>
				<?php endforeach; ?>
				<svg class="iftp-btn-demo__search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
			</nav>
		</header>

		<div class="iftp-btn-demo__layout">
			<main class="iftp-btn-demo__main">
				<article class="iftp-btn-demo__post">
					<div class="iftp-btn-demo__featured" aria-hidden="true"><i></i><i></i><i></i></div>
					<span class="iftp-btn-demo__cat"><?php esc_html_e( 'Featured', 'ifthenpay-pay-button' ); ?></span>
					<h1 class="iftp-btn-demo__title"><?php echo esc_html( $title ); ?></h1>
					<p class="iftp-btn-demo__meta">
						<span class="iftp-btn-demo__avatar" aria-hidden="true"></span>
						<?php echo esc_html( sprintf( /* translators: %s: the date */ __( 'By admin on %s', 'ifthenpay-pay-button' ), wp_date( get_option( 'date_format' ) ) ) ); ?>
					</p>
					<?php if ( '' !== $button->config['summary'] ) : ?>
						<p class="iftp-btn-demo__text"><?php echo esc_html( $button->config['summary'] ); ?></p>
					<?php else : ?>
						<p class="iftp-btn-demo__text"><?php esc_html_e( 'Here is some page content, as your visitors would read it. Right below is your pay button, the same one you would put on a page with its shortcode or block.', 'ifthenpay-pay-button' ); ?></p>
					<?php endif; ?>

					<div class="iftp-btn-demo__stage">
						<?php echo $renderer->render_button( $button, array( 'demo' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ButtonRenderer escapes what it builds. ?>
					</div>

					<p class="iftp-btn-demo__text"><?php esc_html_e( 'More content continues here, after the button. Visitors scroll past it like on any other page.', 'ifthenpay-pay-button' ); ?></p>
				</article>
			</main>

			<aside class="iftp-btn-demo__sidebar" aria-hidden="true">
				<section class="iftp-btn-demo__widget">
					<h2><?php esc_html_e( 'Search', 'ifthenpay-pay-button' ); ?></h2>
					<span class="iftp-btn-demo__field"><?php esc_html_e( 'Search…', 'ifthenpay-pay-button' ); ?></span>
				</section>
				<section class="iftp-btn-demo__widget">
					<h2><?php esc_html_e( 'Recent Posts', 'ifthenpay-pay-button' ); ?></h2>
					<ul><li><i></i></li><li><i></i></li><li><i></i></li></ul>
				</section>
				<section class="iftp-btn-demo__widget">
					<h2><?php esc_html_e( 'Categories', 'ifthenpay-pay-button' ); ?></h2>
					<ul><li><i></i></li><li><i></i></li></ul>
				</section>
			</aside>
		</div>

		<footer class="iftp-btn-demo__footer">
			<div class="iftp-btn-demo__cols" aria-hidden="true">
				<div><i></i><i></i><i></i></div>
				<div><i></i><i></i><i></i></div>
				<div><i></i><i></i><i></i></div>
			</div>
			<p class="iftp-btn-demo__credit">
				<span><?php echo esc_html( sprintf( /* translators: %s: the site's name */ __( '© %s', 'ifthenpay-pay-button' ), '' !== $site ? $site : __( 'Your site', 'ifthenpay-pay-button' ) ) ); ?></span>
				<span><?php esc_html_e( 'Proudly powered by WordPress', 'ifthenpay-pay-button' ); ?></span>
			</p>
		</footer>
	</div>

	<a class="iftp-btn-demo__back" href="<?php echo esc_url( $back ); ?>" data-iftp-demo-back>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
		<?php esc_html_e( 'Close', 'ifthenpay-pay-button' ); ?>
	</a>
		<?php
		if ( '' !== trim( $renderer->code_js() ) ) {
			wp_add_inline_script( ButtonRenderer::SCRIPT_HANDLE, $renderer->code_js() );
		}

		wp_print_scripts( array( ButtonRenderer::SCRIPT_HANDLE, self::HANDLE ) );
		?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * The button to show: the editor's unsaved form when one was posted, else the saved one.
	 */
	private function button(): Button {
		// phpcs:disable WordPress.Security.NonceVerification -- checked in render().
		$id    = isset( $_REQUEST['button'] ) ? absint( wp_unslash( $_REQUEST['button'] ) ) : 0;
		$saved = $id > 0 ? $this->buttons->find( $id ) : null;
		$posts = isset( $_POST['config'] ) && is_array( $_POST['config'] );

		if ( ! $posts ) {
			if ( null === $saved ) {
				wp_die( esc_html__( 'That button no longer exists.', 'ifthenpay-pay-button' ), '', array( 'response' => 404 ) );
			}

			return $saved;
		}

		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$config = map_deep( wp_unslash( $_POST['config'] ), static fn( $value ) => is_string( $value ) ? $value : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Button::sanitize_config() cleans every key by its own rules.
		// phpcs:enable WordPress.Security.NonceVerification

		// Custom code runs here as it would on the site, so a draft only brings its own if its author may save that. The button's CSS is
		// the exception, as in the save, so a button the AI drew shows its new design in the preview.
		$sent           = is_array( $config['code'] ?? null ) ? $config['code'] : array();
		$kept           = $saved->config['code'] ?? array();
		$config['code'] = current_user_can( 'unfiltered_html' ) ? $sent : array_merge( $kept, array_intersect_key( $sent, array( 'button_css' => true ) ) );

		return new Button( $id, $title, '', true, Button::sanitize_config( $config ) );
	}
}
