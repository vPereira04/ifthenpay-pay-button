<?php
/**
 * The button as the AI sees it.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A smaller, plainer shape of the button config: only what a chat should decide (what it charges, asks and how
 * it looks), never the payment methods, redirects, webhooks or invoicing. The AI always answers with the whole
 * shape; it lands on the current config, so everything it doesn't cover stays, and Button::sanitize_config()
 * then checks it like any save from the editor.
 *
 * @phpstan-import-type ButtonConfig from Button
 */
class ButtonSchema {

	// Look keys the chat may set; the rest (images, video, CSS, popup colours) stay as they are.
	private const LOOK_KEYS = array( 'accent', 'surface', 'ink', 'radius', 'field_radius', 'fields', 'font', 'width', 'backdrop', 'steps', 'dark', 'pay_bg', 'pay_color', 'pay_grad', 'pay_color2', 'pay_fx', 'pay_fx_size', 'pay_fx_strength', 'card_opacity', 'scene', 'pay_logo' );

	private const DESIGN_KEYS = array( 'source', 'variant', 'color', 'color2', 'text', 'radius', 'border_width', 'border_style', 'border_color', 'size', 'full', 'icon', 'shadow', 'effect', 'attention', 'upper', 'logos' );

	private const SPOT_KEYS = array( 'mode', 'total', 'field', 'quantity', 'max_each' );

	/**
	 * The JSON schema the AI answers in. Lowercase types and every property required; each provider adapts it.
	 *
	 * @return array<string, mixed>
	 */
	public static function schema(): array {
		$hex   = self::text( 'A colour as #rrggbb.' );
		$maybe = self::text( 'A colour as #rrggbb, or "" to let it be worked out.' );

		return self::shape(
			array(
				'reply'       => self::text( 'Your message to the merchant, in their language: one to three short sentences on what you made or changed, then one idea for what they could ask next. Plain text, no Markdown, no lists.' ),
				'title'       => self::text( 'The button\'s name. Payers see it as the checkout heading. Up to 120 characters.' ),
				'summary'     => self::text( 'One line under the heading in the checkout, or "". Up to 200 characters.' ),
				'label'       => self::text( 'The text on the button, e.g. "Order flowers". "" uses the default, "Pay now". Up to 60 characters.' ),
				'amount_mode' => self::choice( Button::MODES, 'fixed: the merchant sets the price. payer: the payer types any amount (donations, tips, top-ups). link: the amount arrives in a pay link from another plugin; only keep it when the button already uses it.' ),
				'amount'      => self::number( 'Fixed price in euros. With spots it is the price per spot, and the default for options with no price of their own.' ),
				'show_amount' => self::flag( 'Show the price on the button, e.g. "Order flowers · 25,00 €".' ),
				'min'         => self::number( 'Lowest amount the payer may type in payer mode, in euros. At least 0.01.' ),
				'max'         => self::number( 'Highest amount the payer may type in payer mode, in euros.' ),
				'presets'     => self::listing( self::number( 'An amount in euros.' ), 'Up to 6 quick-pick amounts for payer mode, lowest first, or [].' ),
				'payer'       => self::shape(
					array_fill_keys( Button::FIELDS, self::choice( Button::FIELD_STATES, '' ) ),
					'Built-in payer details: name, email, phone, nif (the Portuguese tax number, for invoices) and note (a free-text message). Each off, optional or required. Email lets the payer get the payment details by email.'
				),
				'note_label'  => self::text( 'The label of the note field when it is on, e.g. "Message for the card", or "" for the default.' ),
				'first_step'  => self::text( 'The first step\'s title when page breaks split the form into steps, or "" for the default.' ),
				'form'        => self::listing(
					self::shape(
						array(
							'id'          => self::text( 'Unique id: lowercase letters, digits and _ (e.g. "bouquet"). Keep the id of a field that already exists, even when you rename it. For the two fixed blocks it is "amount" or "details".' ),
							'type'        => self::choice( array_merge( FormFields::ANCHORS, FormFields::INPUT_TYPES, array( FormFields::TYPE_CONTENT, FormFields::TYPE_PAGE ) ), 'amount: where the amount shows (fixed block, exactly once). details: where the payer details go (fixed block, exactly once). text, textarea, email, tel, number, date: inputs. select (dropdown), radio (single choice), checkboxes (multiple choice): pick from options. checkbox: one tick box to agree to something. content: a text block for the payer to read. page: a page break that starts a new step titled by its label.' ),
							'label'       => self::text( 'The question, the text block\'s text, or the new step\'s title. "" for amount and details.' ),
							'required'    => self::flag( 'The payer must answer it. Only for inputs.' ),
							'placeholder' => self::text( 'Hint inside an empty text, textarea, email, tel, number or select field, or "".' ),
							'help'        => self::text( 'A short line under the field, or "".' ),
							'options'     => self::listing( self::text( 'An option, up to 100 characters.' ), 'The choices of a select, radio or checkboxes field (up to 30), otherwise [].' ),
							'width'       => self::choice( array( 'full', 'half' ), 'half puts two short inputs side by side.' ),
							'when'        => self::shape(
								array(
									'field'  => self::text( 'The id of an earlier select, radio, checkboxes or checkbox field, or "" to always show.' ),
									'op'     => self::choice( FormFields::CONDITION_OPS, 'is: show when the answer is one of values. not: show when it is none of them.' ),
									'values' => self::listing( self::text( 'One of that field\'s options exactly, or "1" for a ticked checkbox.' ), '' ),
								),
								'Show this item only for some answers to an earlier choice. field "" means it always shows.'
							),
						),
						''
					),
					'The checkout, top to bottom. Must hold exactly one "amount" and one "details" item. Up to 40 items.'
				),
				'spots'       => self::shape(
					array(
						'mode'     => self::choice( Spots::MODES, 'none: no limit. total: one number of spots for everyone (a class with 20 seats). options: spots and prices per option of one select, radio or checkboxes field (ticket types, time slots, products).' ),
						'total'    => self::integer( 'The spots in total mode, or an optional overall cap in options mode (0 = none).' ),
						'field'    => self::text( 'In options mode, the id of the select, radio or checkboxes field whose options have spots, else "".' ),
						'options'  => self::listing(
							self::shape(
								array(
									'label' => self::text( 'The option, exactly as in the field.' ),
									'spots' => self::integer( 'How many of this option there are; 0 means no limit.' ),
									'price' => self::number( 'Its price per spot in euros; 0 uses the button\'s amount. Only with a fixed amount.' ),
								),
								''
							),
							'One entry per option of that field, in options mode; otherwise [].'
						),
						'quantity' => self::flag( 'The payer can take more than one spot in a payment.' ),
						'max_each' => self::integer( 'Most spots in one payment when quantity is on, 1 to 50.' ),
					),
					'How many people can pay.'
				),
				'button'      => self::shape(
					array(
						'source'       => self::choice( ButtonDesign::SOURCES, 'theme: look like the site theme\'s own buttons. custom: use the style below.' ),
						'variant'      => self::choice( ButtonDesign::VARIANTS, 'filled, outline, soft (tinted), gradient (color into color2), glass (frosted), 3d (raised with a darker edge), neon (glowing outline, best on dark pages), brutal (thick ink border, hard offset shadow) or ghost (text only).' ),
						'color'        => $hex,
						'color2'       => $maybe,
						'text'         => $maybe,
						'radius'       => self::integer( 'Corner radius in px, 0 to 32; 32 is a pill.' ),
						'border_width' => self::integer( 'Border width in px, 0 to 6. 2 is usual.' ),
						'border_style' => self::choice( ButtonDesign::BORDER_STYLES, 'double needs 3px or more.' ),
						'border_color' => self::text( 'The border colour as #rrggbb, or "" for the style\'s own.' ),
						'size'         => self::choice( ButtonDesign::SIZES, '' ),
						'full'         => self::flag( 'Full width.' ),
						'icon'         => self::choice( ButtonDesign::ICONS, '' ),
						'shadow'       => self::flag( '' ),
						'effect'       => self::choice( ButtonDesign::EFFECTS, 'On hover: lift, grow, shine (a light sweep), glow, fill (colour sweeps in) or nudge (the icon moves; needs an icon).' ),
						'attention'    => self::choice( ButtonDesign::ATTENTIONS, 'A looping effect that draws the eye until hovered: pulse, ripple, heartbeat, wiggle, bounce, shimmer, breathe or orbit (light running round the edge). Use none unless the button should stand out, e.g. donations or a sale.' ),
						'upper'        => self::flag( 'Capital letters.' ),
						'logos'        => self::flag( 'Show the payment method logos under the button.' ),
					),
					'How the button looks.'
				),
				'checkout'    => self::shape(
					array(
						'own'             => self::flag( 'true: this button has its own checkout look (the values below). false: it uses the site\'s look and the values below are ignored.' ),
						'accent'          => $hex,
						'surface'         => self::text( 'The card\'s background colour as #rrggbb. Keep it light unless the whole look is dark.' ),
						'ink'             => self::text( 'The text colour as #rrggbb; it must read well on surface.' ),
						'radius'          => self::integer( 'Card corner radius in px, 0 to 32.' ),
						'field_radius'    => self::integer( 'Field corner radius in px, 0 to 24.' ),
						'fields'          => self::choice( CheckoutLook::FIELD_STYLES, '' ),
						'font'            => self::choice( CheckoutLook::FONTS, 'system: a clean system font. theme: the site theme\'s font.' ),
						'width'           => self::choice( array_keys( CheckoutLook::WIDTHS ), '' ),
						'backdrop'        => self::choice( CheckoutLook::BACKDROPS, 'Behind the checkout popup on a page: blur, dim or light.' ),
						'steps'           => self::choice( CheckoutLook::STEP_STYLES, 'How a form with pages shows where the payer is: bars (a segment per page), labels (segments with page names), line (one bar with a %), numbers (numbered circles), dots, ring (a round meter) or text (just the words).' ),
						'dark'            => self::choice( CheckoutLook::DARK_MODES, 'auto follows the payer\'s device dark mode.' ),
						'pay_bg'          => self::choice( CheckoutLook::PAY_BGS, 'The pay page background: glow (soft accent light), plain (pay_color), gradient (pay_color into pay_color2). image and video only when the button already has one; you can\'t add media.' ),
						'pay_color'       => $maybe,
						'pay_grad'        => self::choice( CheckoutLook::PAY_GRADS, 'Gradient style: soft, aurora or diagonal.' ),
						'pay_color2'      => $maybe,
						'pay_fx'          => self::choice( CheckoutLook::PAY_FX, 'A pattern over the background: crystals (low-poly facets), waves (flowing lines), bokeh (soft out-of-focus lights), grain (film noise) or none.' ),
						'pay_fx_size'     => self::integer( 'Pattern size, 40 to 400.' ),
						'pay_fx_strength' => self::integer( 'Pattern strength, 0 to 100.' ),
						'card_opacity'    => self::integer( 'Card opacity, 0 to 100. Below 100 the card turns to frosted glass over the background.' ),
						'scene'           => self::flag( 'Open the checkout full screen on the pay page background, instead of a popup over the page.' ),
						'pay_logo'        => self::flag( 'Show the site logo above the card on the pay page.' ),
					),
					'How the checkout, the pay page and the result popups look.'
				),
			),
			''
		);
	}

	/**
	 * A config in the AI's shape.
	 *
	 * @param array<string, mixed> $config A sanitized config.
	 * @phpstan-param ButtonConfig $config
	 * @param string               $title  The button's title.
	 * @return array<string, mixed>
	 */
	public static function from_config( array $config, string $title ): array {
		return array(
			'title'       => $title,
			'summary'     => $config['summary'],
			'label'       => $config['label'],
			'amount_mode' => $config['amount_mode'],
			'amount'      => $config['amount'],
			'show_amount' => $config['show_amount'],
			'min'         => $config['min'],
			'max'         => $config['max'],
			'presets'     => $config['presets'],
			'payer'       => $config['fields'],
			'note_label'  => $config['note_label'],
			'first_step'  => $config['first_step'],
			'form'        => array_map(
				static fn( array $item ): array => array_intersect_key( $item, array_flip( array( 'id', 'type', 'label', 'required', 'placeholder', 'help', 'options', 'width', 'when' ) ) ),
				$config['form']
			),
			'spots'       => array_intersect_key( $config['spots'], array_flip( array_merge( self::SPOT_KEYS, array( 'options' ) ) ) ),
			'button'      => array_intersect_key( $config['design'], array_flip( self::DESIGN_KEYS ) ),
			'checkout'    => array( 'own' => Button::LOOK_OWN === $config['look_mode'] ) + array_intersect_key( $config['look'], array_flip( self::LOOK_KEYS ) ),
		);
	}

	/**
	 * Lays the AI's answer over a config and cleans it like an editor save.
	 *
	 * @param array<string, mixed> $answer The AI's answer.
	 * @param array<string, mixed> $base   The sanitized config it changes.
	 * @phpstan-param ButtonConfig $base
	 * @param string               $title  The current title, kept when the answer has none.
	 * @return array{title: string, config: ButtonConfig}
	 */
	public static function to_config( array $answer, array $base, string $title ): array {
		$config = $base;
		$pick   = static fn( string $key, array $from ): array => is_array( $from[ $key ] ?? null ) ? $from[ $key ] : array();

		foreach ( array( 'summary', 'label', 'amount_mode', 'amount', 'show_amount', 'min', 'max', 'presets', 'note_label', 'first_step' ) as $key ) {
			if ( array_key_exists( $key, $answer ) ) {
				$config[ $key ] = $answer[ $key ];
			}
		}

		// The editor's sanitizer reads "auto" colours from a flag, so an empty one has to say so.
		$design = array_intersect_key( $pick( 'button', $answer ), array_flip( self::DESIGN_KEYS ) );
		$look   = array_intersect_key( $pick( 'checkout', $answer ), array_flip( self::LOOK_KEYS ) );

		$config['fields'] = array_intersect_key( $pick( 'payer', $answer ), array_flip( Button::FIELDS ) ) + $base['fields'];
		$config['form']   = array_key_exists( 'form', $answer ) ? $pick( 'form', $answer ) : $base['form'];
		$config['spots']  = array_intersect_key( $pick( 'spots', $answer ), array_flip( array_merge( self::SPOT_KEYS, array( 'options' ) ) ) ) + $base['spots'];
		$config['design'] = $design + array(
			'text_auto'         => '' === ( $design['text'] ?? '' ),
			'color2_auto'       => '' === ( $design['color2'] ?? '' ),
			'border_color_auto' => '' === ( $design['border_color'] ?? $base['design']['border_color'] ),
		) + $base['design'];
		$config['look']   = $look + array(
			'pay_color_auto'  => '' === ( $look['pay_color'] ?? '' ),
			'pay_color2_auto' => '' === ( $look['pay_color2'] ?? '' ),
		) + $base['look'];

		if ( array_key_exists( 'own', $pick( 'checkout', $answer ) ) ) {
			$config['look_mode'] = ! empty( $answer['checkout']['own'] ) ? Button::LOOK_OWN : Button::LOOK_SITE;
		}

		$named = is_scalar( $answer['title'] ?? null ) ? mb_substr( sanitize_text_field( (string) $answer['title'] ), 0, 120 ) : '';

		return array(
			'title'  => '' !== $named ? $named : $title,
			'config' => Button::sanitize_config( $config ),
		);
	}

	/**
	 * What changed between two versions, in a few short lines for the chat. Money lines are flagged, so the
	 * merchant checks every price the AI set.
	 *
	 * @param array<string, mixed> $before The earlier version, in the AI's shape.
	 * @param array<string, mixed> $after  The new one.
	 * @return array<int, array{text: string, money: bool}>
	 */
	public static function changes( array $before, array $after ): array {
		$lines = array();
		$add   = static function ( string $text, bool $money = false ) use ( &$lines ): void {
			$lines[] = array(
				'text'  => $text,
				'money' => $money,
			);
		};

		if ( $before['title'] !== $after['title'] && '' !== $after['title'] ) {
			/* translators: %s: the button's name */
			$add( sprintf( __( 'Named “%s”', 'ifthenpay-pay-button' ), $after['title'] ) );
		}

		if ( self::price_line( $before ) !== self::price_line( $after ) && self::base_counts( $after ) ) {
			$add( self::price_line( $after ), true );
		}

		if ( $before['label'] !== $after['label'] && '' !== $after['label'] ) {
			/* translators: %s: the text on the button */
			$add( sprintf( __( 'Button text “%s”', 'ifthenpay-pay-button' ), $after['label'] ) );
		}

		self::form_changes( $before, $after, $add );

		if ( $before['payer'] !== $after['payer'] ) {
			$asked = array_keys( array_filter( $after['payer'], static fn( string $state ): bool => Button::FIELD_OFF !== $state ) );
			$names = array(
				'name'  => __( 'name', 'ifthenpay-pay-button' ),
				'email' => __( 'email', 'ifthenpay-pay-button' ),
				'phone' => __( 'phone', 'ifthenpay-pay-button' ),
				'nif'   => __( 'NIF', 'ifthenpay-pay-button' ),
				'note'  => __( 'note', 'ifthenpay-pay-button' ),
			);

			$add(
				array() !== $asked
					/* translators: %s: the payer details asked for, e.g. "name, email" */
					? sprintf( __( 'Asks for %s', 'ifthenpay-pay-button' ), implode( ', ', array_map( static fn( string $field ): string => $names[ $field ], $asked ) ) )
					: __( 'Asks for no payer details', 'ifthenpay-pay-button' )
			);
		}

		foreach ( self::spot_lines( $before, $after ) as $line ) {
			$add( $line[0], $line[1] );
		}

		if ( $before['button'] !== $after['button'] ) {
			$add( ButtonDesign::SOURCE_THEME === $after['button']['source'] ? __( 'Button follows the theme', 'ifthenpay-pay-button' ) : __( 'New button design', 'ifthenpay-pay-button' ) );
		}

		$look_before = array_diff_key( $before['checkout'], array_flip( array( 'pay_bg', 'pay_fx' ) ) );
		$look_after  = array_diff_key( $after['checkout'], array_flip( array( 'pay_bg', 'pay_fx' ) ) );

		if ( $look_before !== $look_after ) {
			$add( $after['checkout']['own'] ? __( 'New checkout colours and shape', 'ifthenpay-pay-button' ) : __( 'Checkout uses the site look', 'ifthenpay-pay-button' ) );
		}

		if ( $after['checkout']['own'] && ( $before['checkout']['pay_bg'] !== $after['checkout']['pay_bg'] || $before['checkout']['pay_fx'] !== $after['checkout']['pay_fx'] ) ) {
			$add(
				/* translators: %s: the pay page background, e.g. "gradient + bokeh" */
				sprintf( __( 'Pay page background: %s', 'ifthenpay-pay-button' ), $after['checkout']['pay_bg'] . ( 'none' !== $after['checkout']['pay_fx'] ? ' + ' . $after['checkout']['pay_fx'] : '' ) )
			);
		}

		return $lines;
	}

	/**
	 * Every price a config sets, for the editor to ask the merchant to check.
	 *
	 * @param array<string, mixed> $config A sanitized config.
	 * @phpstan-param ButtonConfig $config
	 * @return array<int, string>
	 */
	public static function prices( array $config ): array {
		$state   = self::from_config( $config, '' );
		$lines   = self::base_counts( $state ) ? array( self::price_line( $state ) ) : array();
		$options = Spots::MODE_OPTIONS === $config['spots']['mode'] && Button::MODE_FIXED === $config['amount_mode'] ? $config['spots']['options'] : array();

		foreach ( $options as $option ) {
			if ( $option['price'] > 0 ) {
				/* translators: 1: an option, 2: its price */
				$lines[] = sprintf( __( '%1$s: %2$s', 'ifthenpay-pay-button' ), $option['label'], CheckoutService::format_money( $option['price'] ) );
			}
		}

		return $lines;
	}

	/**
	 * Whether the button's own price reaches the payer: not when every option has a price of its own.
	 *
	 * @param array<string, mixed> $state A version in the AI's shape.
	 */
	private static function base_counts( array $state ): bool {
		if ( Button::MODE_FIXED !== $state['amount_mode'] || Spots::MODE_OPTIONS !== $state['spots']['mode'] || array() === $state['spots']['options'] ) {
			return true;
		}

		return array() !== array_filter( $state['spots']['options'], static fn( array $option ): bool => (float) $option['price'] <= 0 );
	}

	/**
	 * The price in one line.
	 *
	 * @param array<string, mixed> $state A version in the AI's shape.
	 */
	private static function price_line( array $state ): string {
		if ( Button::MODE_FIXED === $state['amount_mode'] ) {
			/* translators: %s: the price */
			return sprintf( __( 'Price %s', 'ifthenpay-pay-button' ), CheckoutService::format_money( (float) $state['amount'] ) );
		}

		if ( Button::MODE_LINK === $state['amount_mode'] ) {
			return __( 'Amount comes in the pay link', 'ifthenpay-pay-button' );
		}

		$line = sprintf(
			/* translators: 1: lowest amount, 2: highest amount */
			__( 'Payer chooses %1$s – %2$s', 'ifthenpay-pay-button' ),
			CheckoutService::format_money( (float) $state['min'] ),
			CheckoutService::format_money( (float) $state['max'] )
		);

		if ( array() !== $state['presets'] ) {
			/* translators: 1: the amount range, 2: the quick-pick amounts */
			$line = sprintf( __( '%1$s, quick picks %2$s', 'ifthenpay-pay-button' ), $line, implode( ' · ', array_map( static fn( $amount ): string => CheckoutService::format_money( (float) $amount ), $state['presets'] ) ) );
		}

		return $line;
	}

	/**
	 * Fields added, removed or renamed, and steps.
	 *
	 * @param array<string, mixed> $before The earlier version.
	 * @param array<string, mixed> $after  The new one.
	 * @param callable             $add    Adds a line.
	 */
	private static function form_changes( array $before, array $after, callable $add ): void {
		$fields = static function ( array $form ): array {
			$out = array();

			foreach ( $form as $item ) {
				if ( in_array( $item['type'], FormFields::INPUT_TYPES, true ) ) {
					$out[ $item['id'] ] = $item;
				}
			}

			return $out;
		};
		$old    = $fields( $before['form'] );
		$new    = $fields( $after['form'] );
		$types  = FormFields::type_labels();
		$added  = array_diff_key( $new, $old );

		if ( count( $added ) > 3 ) {
			/* translators: %s: how many fields */
			$add( sprintf( _n( 'Added %s field', 'Added %s fields', count( $added ), 'ifthenpay-pay-button' ), number_format_i18n( count( $added ) ) ) );
		} else {
			foreach ( $added as $item ) {
				/* translators: 1: the field's label, 2: its type, e.g. Dropdown */
				$add( sprintf( __( 'Added “%1$s” (%2$s)', 'ifthenpay-pay-button' ), $item['label'], $types[ $item['type'] ] ?? $item['type'] ) );
			}
		}

		foreach ( array_diff_key( $old, $new ) as $item ) {
			/* translators: %s: the field's label */
			$add( sprintf( __( 'Removed “%s”', 'ifthenpay-pay-button' ), $item['label'] ) );
		}

		foreach ( array_intersect_key( $new, $old ) as $id => $item ) {
			if ( $item !== $old[ $id ] ) {
				/* translators: %s: the field's label */
				$add( sprintf( __( 'Changed “%s”', 'ifthenpay-pay-button' ), $item['label'] ) );
			}
		}

		$steps = static fn( array $form ): int => 1 + count( array_filter( $form, static fn( array $item ): bool => FormFields::TYPE_PAGE === $item['type'] ) );

		if ( $steps( $before['form'] ) !== $steps( $after['form'] ) ) {
			$count = $steps( $after['form'] );

			/* translators: %s: how many steps */
			$add( 1 === $count ? __( 'One page, no steps', 'ifthenpay-pay-button' ) : sprintf( __( 'Split into %s steps', 'ifthenpay-pay-button' ), number_format_i18n( $count ) ) );
		}

		$texts = static fn( array $form ): array => array_column( array_filter( $form, static fn( array $item ): bool => FormFields::TYPE_CONTENT === $item['type'] ), 'label' );

		if ( $texts( $before['form'] ) !== $texts( $after['form'] ) && array() !== $texts( $after['form'] ) ) {
			$add( __( 'Wrote the text blocks', 'ifthenpay-pay-button' ) );
		}
	}

	/**
	 * Spots, with each option's price flagged.
	 *
	 * @param array<string, mixed> $before The earlier version.
	 * @param array<string, mixed> $after  The new one.
	 * @return array<int, array{0: string, 1: bool}>
	 */
	private static function spot_lines( array $before, array $after ): array {
		$old   = $before['spots'];
		$new   = $after['spots'];
		$lines = array();

		if ( array_intersect_key( $old, array_flip( self::SPOT_KEYS ) ) !== array_intersect_key( $new, array_flip( self::SPOT_KEYS ) ) ) {
			if ( Spots::MODE_TOTAL === $new['mode'] ) {
				/* translators: %s: how many spots */
				$lines[] = array( sprintf( _n( '%s spot in total', '%s spots in total', (int) $new['total'], 'ifthenpay-pay-button' ), number_format_i18n( (int) $new['total'] ) ), false );
			} elseif ( Spots::MODE_OPTIONS === $new['mode'] ) {
				$lines[] = array( __( 'Spots per option', 'ifthenpay-pay-button' ), false );
			} else {
				$lines[] = array( __( 'No limit on spots', 'ifthenpay-pay-button' ), false );
			}
		}

		if ( Spots::MODE_OPTIONS === $new['mode'] && $old['options'] !== $new['options'] ) {
			foreach ( $new['options'] as $option ) {
				$spots = 0 === (int) $option['spots']
					? __( 'no limit', 'ifthenpay-pay-button' )
					/* translators: %s: how many spots */
					: sprintf( _n( '%s spot', '%s spots', (int) $option['spots'], 'ifthenpay-pay-button' ), number_format_i18n( (int) $option['spots'] ) );
				$price = (float) $option['price'] > 0 && Button::MODE_FIXED === $after['amount_mode'] ? CheckoutService::format_money( (float) $option['price'] ) . ' · ' : '';

				$lines[] = array( $option['label'] . ': ' . $price . $spots, '' !== $price );
			}
		}

		return $lines;
	}

	/**
	 * An object with every property required.
	 *
	 * @param array<string, array<string, mixed>> $properties Its properties.
	 * @param string                              $about      What it is.
	 * @return array<string, mixed>
	 */
	private static function shape( array $properties, string $about ): array {
		return self::about(
			array(
				'type'       => 'object',
				'properties' => $properties,
				'required'   => array_keys( $properties ),
			),
			$about
		);
	}

	/**
	 * A list.
	 *
	 * @param array<string, mixed> $items Each item.
	 * @param string               $about What it is.
	 * @return array<string, mixed>
	 */
	private static function listing( array $items, string $about ): array {
		return self::about(
			array(
				'type'  => 'array',
				'items' => $items,
			),
			$about
		);
	}

	/**
	 * One of a few strings.
	 *
	 * @param array<int, string> $values The allowed values.
	 * @param string             $about  What it is.
	 * @return array<string, mixed>
	 */
	private static function choice( array $values, string $about ): array {
		return self::about(
			array(
				'type' => 'string',
				'enum' => array_values( $values ),
			),
			$about
		);
	}

	/**
	 * A string.
	 *
	 * @param string $about What it is.
	 * @return array<string, mixed>
	 */
	private static function text( string $about ): array {
		return self::about( array( 'type' => 'string' ), $about );
	}

	/**
	 * A number.
	 *
	 * @param string $about What it is.
	 * @return array<string, mixed>
	 */
	private static function number( string $about ): array {
		return self::about( array( 'type' => 'number' ), $about );
	}

	/**
	 * A whole number.
	 *
	 * @param string $about What it is.
	 * @return array<string, mixed>
	 */
	private static function integer( string $about ): array {
		return self::about( array( 'type' => 'integer' ), $about );
	}

	/**
	 * A true or false.
	 *
	 * @param string $about What it is.
	 * @return array<string, mixed>
	 */
	private static function flag( string $about ): array {
		return self::about( array( 'type' => 'boolean' ), $about );
	}

	/**
	 * Adds a description when there is one.
	 *
	 * @param array<string, mixed> $schema The schema.
	 * @param string               $about  What it is.
	 * @return array<string, mixed>
	 */
	private static function about( array $schema, string $about ): array {
		return '' !== $about ? array( 'description' => $about ) + $schema : $schema;
	}
}
