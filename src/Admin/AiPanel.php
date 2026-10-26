<?php
/**
 * The AI connection's screens.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Ai\AiController;
use Ifthenpay\PayButton\Ai\Connection;
use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Ai\Vault;
use Ifthenpay\PayButton\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The same settings contract as the ifthenpay key: a Connect popup (or the same form inline, in the studio),
 * then the connected state with the model to use and Disconnect. ai.js drives both.
 */
class AiPanel {

	public const SCRIPT_HANDLE = 'iftp-btn-ai';
	public const STYLE_HANDLE  = 'iftp-btn-ai';

	/**
	 * The AI connection.
	 *
	 * @var Connection
	 */
	private Connection $connection;

	/**
	 * Sets up the panel.
	 *
	 * @param Connection|null $connection The AI connection.
	 */
	public function __construct( ?Connection $connection = null ) {
		$this->connection = $connection ?? new Connection();
	}

	/**
	 * Enqueues ai.js and ai.css, with what they need.
	 *
	 * @param array<string, mixed> $studio The studio's starting state, when it's the studio.
	 */
	public function enqueue( array $studio = array() ): void {
		$js  = 'assets/js/ai.js';
		$css = 'assets/css/ai.css';

		wp_enqueue_style( self::STYLE_HANDLE, IFTP_BTN_URL . $css, array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( $css ) );
		wp_enqueue_script( self::SCRIPT_HANDLE, IFTP_BTN_URL . $js, array( Plugin::UI_SCRIPT_HANDLE ), Plugin::asset_version( $js ), true );

		$providers = array();

		foreach ( Connection::providers() as $provider ) {
			$providers[ $provider->id() ] = array(
				'label'  => $provider->label(),
				'keyUrl' => $provider->key_url(),
				'host'   => (string) wp_parse_url( $provider->key_url(), PHP_URL_HOST ),
			);
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'iftpBtnAi',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( AiController::NONCE ),
				'providers' => $providers,
				'studio'    => $studio,
				'maxLength' => Studio::MAX_MESSAGE,
				'i18n'      => self::strings(),
			)
		);
	}

	/**
	 * The panel on Gateway Configuration.
	 */
	public function render_panel(): void {
		$summary = $this->connection->summary();
		?>
		<section class="iftp-btn-panel iftp-btn-ai-panel" aria-labelledby="iftp-btn-ai-title" data-iftp-ai-panel>
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-ai-title" class="iftp-btn-panel__title"><?php echo self::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'AI assistant', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Connect your own Gemini, Claude or ChatGPT key, then describe a button in a chat and watch it being built. Each message is billed to your own account with that provider.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<?php $this->render_status( $summary ); ?>
			</div>
		</section>
		<?php
		if ( null === $summary ) {
			$this->render_dialog();
		} else {
			$this->render_disconnect_dialog();
		}
	}

	/**
	 * Connected or not, the model picker, and the matching action.
	 *
	 * @param array<string, mixed>|null $summary What Connection::summary() gave.
	 */
	public function render_status( ?array $summary ): void {
		?>
		<div class="iftp-btn-ai-status">
			<p class="iftp-btn-connection__status iftp-btn-connection__status--<?php echo null !== $summary ? 'connected' : 'disconnected'; ?>">
				<span class="iftp-btn-connection__dot" aria-hidden="true"></span>
				<?php
				if ( null !== $summary ) {
					/* translators: 1: the AI provider, e.g. Gemini, 2: the key's last characters, e.g. ••••1a2B */
					echo esc_html( sprintf( __( 'Connected to %1$s · key %2$s', 'ifthenpay-pay-button' ), $summary['label'], $summary['hint'] ) );
				} elseif ( $this->connection->is_broken() ) {
					esc_html_e( 'Connect again: this site\'s secret keys changed, so the saved key can\'t be opened.', 'ifthenpay-pay-button' );
				} else {
					esc_html_e( 'Not connected', 'ifthenpay-pay-button' );
				}
				?>
			</p>
			<?php if ( null !== $summary ) : ?>
				<div class="iftp-btn-field iftp-btn-ai-status__model">
					<label class="iftp-btn-field__label" for="iftp-btn-ai-model"><?php esc_html_e( 'Model', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control" id="iftp-btn-ai-model" aria-describedby="iftp-btn-ai-model-help" data-iftp-ai-model>
						<?php foreach ( $summary['models'] as $id => $name ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $summary['model'], $id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="iftp-btn-field__help" id="iftp-btn-ai-model-help"><?php esc_html_e( 'The first one is quick and good at this. Bigger models think longer and cost more.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-connection__action" data-iftp-ai-disconnect-open aria-haspopup="dialog"><?php esc_html_e( 'Disconnect', 'ifthenpay-pay-button' ); ?></button>
			<?php else : ?>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--primary iftp-btn-connection__action" data-iftp-ai-connect-open aria-haspopup="dialog"><?php esc_html_e( 'Connect an AI', 'ifthenpay-pay-button' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The connect form: which AI, and its key. In a popup on Gateway Configuration, inline in the studio.
	 *
	 * @param bool $inline Whether it sits in the page rather than a popup.
	 */
	public function render_form( bool $inline ): void {
		$providers = Connection::providers();
		$first     = (string) array_key_first( $providers );
		?>
		<form class="iftp-btn-dialog__form iftp-btn-ai-connect<?php echo $inline ? ' iftp-btn-ai-connect--inline' : ''; ?>" data-iftp-ai-connect-form novalidate>
			<?php if ( $inline ) : ?>
				<span class="iftp-btn-ai-connect__mark" aria-hidden="true"><?php echo self::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
			<?php endif; ?>
			<h2 id="iftp-btn-ai-connect-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Connect an AI', 'ifthenpay-pay-button' ); ?></h2>
			<p id="iftp-btn-ai-connect-desc" class="iftp-btn-dialog__text"><?php esc_html_e( 'Pick the AI you have a key for and paste it. It builds your buttons from a chat; each message is billed to your own account with that provider.', 'ifthenpay-pay-button' ); ?></p>

			<fieldset class="iftp-btn-ai-providers">
				<legend class="screen-reader-text"><?php esc_html_e( 'AI provider', 'ifthenpay-pay-button' ); ?></legend>
				<?php foreach ( $providers as $id => $provider ) : ?>
					<label class="iftp-btn-ai-provider iftp-btn-ai-provider--<?php echo esc_attr( $id ); ?>">
						<input type="radio" class="iftp-btn-ai-provider__input" name="provider" value="<?php echo esc_attr( $id ); ?>" <?php checked( $first, $id ); ?> data-iftp-ai-provider />
						<span class="iftp-btn-ai-provider__dot" aria-hidden="true"></span>
						<span class="iftp-btn-ai-provider__name"><?php echo esc_html( $provider->label() ); ?></span>
						<span class="iftp-btn-ai-provider__by"><?php echo esc_html( $provider->maker() ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-ai-key"><?php esc_html_e( 'API key', 'ifthenpay-pay-button' ); ?></label>
				<input type="password" class="iftp-btn-field__control" id="iftp-btn-ai-key" name="key" autocomplete="off" spellcheck="false" maxlength="300" required aria-describedby="iftp-btn-ai-key-help iftp-btn-ai-connect-error" data-iftp-ai-key />
				<p id="iftp-btn-ai-key-help" class="iftp-btn-field__help">
					<a href="<?php echo esc_url( $providers[ $first ]->key_url() ); ?>" target="_blank" rel="noopener noreferrer" data-iftp-ai-key-link><?php echo esc_html( self::key_link_text( $providers[ $first ]->label() ) ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ifthenpay-pay-button' ); ?></span></a>
				</p>
				<p id="iftp-btn-ai-connect-error" class="iftp-btn-field__error" role="alert" data-iftp-ai-connect-error></p>
			</div>

			<p class="iftp-btn-ai-connect__safe">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/></svg>
				<span><?php esc_html_e( 'Encrypted with this site\'s secret keys before it\'s saved, and never shown again. Only administrators can use it, and only to build buttons.', 'ifthenpay-pay-button' ); ?></span>
			</p>
			<?php if ( ! Vault::is_strong() ) : ?>
				<p class="iftp-btn-ai-connect__warn"><?php esc_html_e( 'This site\'s wp-config.php has no secret keys of its own, so WordPress keeps them in the database, next to the encrypted key. Add them to wp-config.php for full protection.', 'ifthenpay-pay-button' ); ?></p>
			<?php endif; ?>

			<div class="iftp-btn-dialog__actions">
				<?php if ( ! $inline ) : ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
				<?php endif; ?>
				<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-ai-connect-submit><?php esc_html_e( 'Connect', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * The ✦ mark the AI parts wear.
	 */
	public static function sparkle(): string {
		return '<svg class="iftp-btn-sparkle" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M10.5 3.5c.4 3.9 2.6 6.1 6.5 6.5-3.9.4-6.1 2.6-6.5 6.5-.4-3.9-2.6-6.1-6.5-6.5 3.9-.4 6.1-2.6 6.5-6.5z"/><path d="M18 14c.2 1.9 1.1 2.8 3 3-1.9.2-2.8 1.1-3 3-.2-1.9-1.1-2.8-3-3 1.9-.2 2.8-1.1 3-3z"/></svg>';
	}

	/**
	 * The connect popup.
	 */
	private function render_dialog(): void {
		?>
		<dialog class="iftp-btn-dialog iftp-btn-ai-dialog" aria-labelledby="iftp-btn-ai-connect-title" aria-describedby="iftp-btn-ai-connect-desc" data-iftp-ai-connect-dialog>
			<?php $this->render_form( false ); ?>
		</dialog>
		<?php
	}

	/**
	 * The disconnect confirmation.
	 */
	private function render_disconnect_dialog(): void {
		?>
		<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-ai-disconnect-title" aria-describedby="iftp-btn-ai-disconnect-desc" data-iftp-ai-disconnect-dialog>
			<h2 id="iftp-btn-ai-disconnect-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Disconnect the AI?', 'ifthenpay-pay-button' ); ?></h2>
			<p id="iftp-btn-ai-disconnect-desc" class="iftp-btn-dialog__text"><?php esc_html_e( 'The key is deleted from this site. Your buttons stay as they are, and you can connect again at any time.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close autofocus><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--danger" data-iftp-ai-disconnect-confirm><?php esc_html_e( 'Disconnect', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</dialog>
		<?php
	}

	/**
	 * "Get a Gemini key".
	 *
	 * @param string $label The provider's name.
	 */
	private static function key_link_text( string $label ): string {
		/* translators: %s: the AI provider, e.g. Gemini */
		return sprintf( __( 'Get a %s key', 'ifthenpay-pay-button' ), $label );
	}

	/**
	 * The strings ai.js shows.
	 *
	 * @return array<string, string|array<int, string>>
	 */
	private static function strings(): array {
		return array(
			/* translators: %s: the AI provider, e.g. Gemini */
			'getKey'        => __( 'Get a %s key', 'ifthenpay-pay-button' ),
			'connect'       => __( 'Connect', 'ifthenpay-pay-button' ),
			'connecting'    => __( 'Checking the key…', 'ifthenpay-pay-button' ),
			'needKey'       => __( 'Paste your API key.', 'ifthenpay-pay-button' ),
			'modelSaved'    => __( 'Model saved.', 'ifthenpay-pay-button' ),
			'error'         => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
			'you'           => __( 'You', 'ifthenpay-pay-button' ),
			'assistant'     => __( 'AI', 'ifthenpay-pay-button' ),
			'welcomeNew'    => __( 'Hi! Tell me what you\'re selling or collecting for, and I\'ll build the whole button: the form, the prices and the look. Then keep chatting to change anything.', 'ifthenpay-pay-button' ),
			/* translators: %s: the button's title */
			'welcomeEdit'   => __( 'What should change on “%s”? I can add or remove fields, change prices or spots, or give it a whole new look.', 'ifthenpay-pay-button' ),
			'ideasNew'      => array(
				__( 'A bouquet order with sizes and delivery', 'ifthenpay-pay-button' ),
				__( 'A yoga class with 12 spots', 'ifthenpay-pay-button' ),
				__( 'A donation with quick amounts', 'ifthenpay-pay-button' ),
				__( 'Workshop tickets: early bird and regular', 'ifthenpay-pay-button' ),
			),
			'ideasEdit'     => array(
				__( 'Give it a fresh look', 'ifthenpay-pay-button' ),
				__( 'Split the form into steps', 'ifthenpay-pay-button' ),
				__( 'Limit it to 20 spots', 'ifthenpay-pay-button' ),
				__( 'Also ask for a phone number', 'ifthenpay-pay-button' ),
			),
			'thinking'      => array(
				__( 'Reading what you asked…', 'ifthenpay-pay-button' ),
				__( 'Laying out the fields…', 'ifthenpay-pay-button' ),
				__( 'Setting the prices…', 'ifthenpay-pay-button' ),
				__( 'Picking the colours…', 'ifthenpay-pay-button' ),
				__( 'Polishing the pay page…', 'ifthenpay-pay-button' ),
			),
			'retry'         => __( 'Try again', 'ifthenpay-pay-button' ),
			'undone'        => __( 'Went back one step.', 'ifthenpay-pay-button' ),
			'checkPrices'   => __( 'Check the prices', 'ifthenpay-pay-button' ),
			'checkMoney'    => __( 'Price set by the AI: check it', 'ifthenpay-pay-button' ),
			'tooLong'       => __( 'That message is too long. Keep it under 2,000 characters.', 'ifthenpay-pay-button' ),
			'previewLoaded' => __( 'Preview updated.', 'ifthenpay-pay-button' ),
		);
	}
}
