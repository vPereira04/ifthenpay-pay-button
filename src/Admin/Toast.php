<?php
/**
 * The message that slides up at the bottom of our screens after an action.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Takes the place of WordPress's notice bar. A page queues its messages while it renders and I print them
 * together in the footer, so they stack in one spot and sit outside the page's own wrappers.
 */
class Toast {

	/**
	 * Milliseconds a message stays; an error stays until it is closed, because it says what to fix.
	 */
	private const DURATION = array(
		'success' => 5000,
		'warning' => 8000,
		'error'   => 0,
	);

	/**
	 * The markup a message may carry: the Undo link.
	 */
	private const ALLOWED = array(
		'a'      => array( 'href' => array() ),
		'strong' => array(),
	);

	/**
	 * Queued messages.
	 *
	 * @var list<array{html: string, type: string, wait: int}>
	 */
	private static array $queue = array();

	/**
	 * Queues a message. $html must already be escaped: it may carry a link, like Undo.
	 *
	 * @param string $html Escaped message.
	 * @param string $type success, warning or error.
	 */
	public static function add( string $html, string $type = 'success' ): void {
		$type = isset( self::DURATION[ $type ] ) ? $type : 'success';

		if ( array() === self::$queue ) {
			add_action( 'admin_footer', array( self::class, 'print_queue' ), 10, 0 );
		}

		self::$queue[] = array(
			'html' => $html,
			'type' => $type,
			'wait' => self::DURATION[ $type ],
		);
	}

	/**
	 * Prints what the page queued.
	 */
	public static function print_queue(): void {
		if ( array() === self::$queue ) {
			return;
		}
		?>
		<div class="iftp-btn-toasts" data-iftp-toasts>
			<?php foreach ( self::$queue as $toast ) : ?>
				<div class="iftp-btn-toast<?php echo 'success' === $toast['type'] ? '' : ' iftp-btn-toast--' . esc_attr( $toast['type'] ); ?>" role="<?php echo 'error' === $toast['type'] ? 'alert' : 'status'; ?>" data-iftp-flash="<?php echo esc_attr( (string) $toast['wait'] ); ?>">
					<span class="iftp-btn-toast__message"><?php echo wp_kses( $toast['html'], self::ALLOWED ); ?></span>
					<button type="button" class="iftp-btn-toast__close" data-iftp-flash-close aria-label="<?php esc_attr_e( 'Close', 'ifthenpay-pay-button' ); ?>">
						<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m4 4 8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		self::$queue = array();
	}
}
