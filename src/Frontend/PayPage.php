<?php
/**
 * The hosted pay page: /pay/{slug}/.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A page of its own for every active button, outside the theme: a link that works in an email, a QR code,
 * a social post, or as the redirect target of a form plugin ifthenpay has no integration with.
 */
class PayPage {

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Renders the form.
	 *
	 * @var ButtonRenderer
	 */
	private ButtonRenderer $renderer;

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout_settings;

	/**
	 * Sets up the page.
	 *
	 * @param ButtonRepository      $buttons           Buttons storage.
	 * @param ButtonRenderer        $renderer          Renders the form.
	 * @param CheckoutSettings|null $checkout_settings Checkout settings.
	 */
	public function __construct( ButtonRepository $buttons, ButtonRenderer $renderer, ?CheckoutSettings $checkout_settings = null ) {
		$this->buttons           = $buttons;
		$this->renderer          = $renderer;
		$this->checkout_settings = $checkout_settings ?? new CheckoutSettings();
	}

	/**
	 * Hooks the rewrite rule, the query var and the page itself.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * /{base}/{slug}/ → the button's page.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^' . preg_quote( $this->checkout_settings->get_pay_base(), '/' ) . '/([^/]+)/?$', 'index.php?' . PayLinks::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Makes the query var public, which also gives plain permalinks their ?iftp_btn_pay_button= form.
	 *
	 * @param array<int, string> $vars The public query vars.
	 * @return array<int, string>
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = PayLinks::QUERY_VAR;

		return $vars;
	}

	/**
	 * Prints the page for a known slug. An unknown one falls through to the theme's 404.
	 */
	public function handle(): void {
		$slug = sanitize_title( (string) get_query_var( PayLinks::QUERY_VAR ) );

		if ( '' === $slug ) {
			return;
		}

		$button = $this->buttons->find_active_by_slug( $slug );

		if ( null === $button ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			return;
		}

		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$this->render( $button );
		exit;
	}

	/**
	 * The page for a button that isn't saved yet, for the AI studio's preview. The same page, but it never
	 * starts a payment; the caller checks who's asking.
	 *
	 * @param Button $button The unsaved button.
	 */
	public function preview( Button $button ): void {
		header( 'X-Robots-Tag: noindex, nofollow' );

		$this->render( $button, true );
	}

	/**
	 * The page: the button's form, prefilled from the link, on a plain canvas.
	 *
	 * @param Button $button  The button.
	 * @param bool   $preview Whether it's the AI studio's preview, which can't take payments.
	 */
	private function render( Button $button, bool $preview = false ): void {
		ButtonRenderer::enqueue();

		$available = $preview ? true : ( new CheckoutService() )->availability( $button );
		$site      = (string) get_bloginfo( 'name' );
		$look      = CheckoutLook::for_button( $button, $this->checkout_settings );
		$own       = CheckoutLook::inline_style( $button );
		$classes   = trim( 'iftp-btn-page-body ' . CheckoutLook::page_classes( $look ) . ' ' . CheckoutLook::dark_class( $look ) );
		/* translators: 1: the button's title, 2: the site name */
		$title = sprintf( __( '%1$s · %2$s', 'ifthenpay-pay-button' ), $button->title, $site );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title ); ?></title>
		<?php
		wp_site_icon();
		wp_print_styles( array( ButtonRenderer::STYLE_HANDLE ) );
		?>
</head>
<body class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $own ? ' style="' . esc_attr( $own ) . '"' : ''; ?>>
		<?php echo CheckoutLook::video_markup( $look, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in video_markup(). ?>
	<main class="iftp-btn-page">
		<?php if ( $look['pay_logo'] ) : ?>
			<a class="iftp-btn-page__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo self::site_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in site_mark(). ?></a>
		<?php endif; ?>
		<?php if ( is_wp_error( $available ) ) : ?>
			<div class="iftp-btn-form iftp-btn-form--notice">
				<header class="iftp-btn-form__header"><span class="iftp-btn-form__site"><?php echo esc_html( $site ); ?></span></header>
				<div class="iftp-btn-form__summary">
					<h1 class="iftp-btn-form__title"><?php echo esc_html( $button->title ); ?></h1>
					<p class="iftp-btn-form__text"><?php echo esc_html( $available->get_error_message() ); ?></p>
				</div>
			</div>
		<?php else : ?>
			<?php $this->renderer->print_form( $button, 'iftp-btn-page', $preview ? array() : PayLinks::read_query(), false, $preview ); ?>
		<?php endif; ?>
		<?php if ( $look['pay_back'] ) : ?>
			<p class="iftp-btn-page__back"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $site ); ?></a></p>
		<?php endif; ?>
	</main>
		<?php wp_print_scripts( array( ButtonRenderer::SCRIPT_HANDLE ) ); ?>
</body>
</html>
		<?php
	}

	/**
	 * The site's logo for the top of the pay page: the theme's custom logo, else the site icon, else its name.
	 */
	public static function site_mark(): string {
		$site = (string) get_bloginfo( 'name' );
		$logo = (int) get_theme_mod( 'custom_logo' );
		$url  = $logo > 0 ? wp_get_attachment_image_url( $logo, 'medium' ) : false;
		$url  = is_string( $url ) && '' !== $url ? $url : get_site_icon_url( 96 );

		if ( '' !== $url ) {
			return '<img class="iftp-btn-page__logo-image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $site ) . '" />';
		}

		return '<span class="iftp-btn-page__name">' . esc_html( $site ) . '</span>';
	}
}
