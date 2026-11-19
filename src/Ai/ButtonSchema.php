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
use Ifthenpay\PayButton\Buttons\FieldIcons;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CustomCode;

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
	private const LOOK_KEYS = array( 'accent', 'surface', 'ink', 'radius', 'field_radius', 'fields', 'font', 'width', 'backdrop', 'steps', 'dark', 'pay_bg', 'pay_color', 'pay_grad', 'pay_color2', 'pay_fx', 'pay_fx_size', 'pay_fx_strength', 'pay_fx_still', 'card_opacity', 'card_blur', 'scene', 'pay_logo', 'dark_surface', 'dark_ink', 'dark_accent', 'card_skin', 'card_skin_strength', 'pay_mark_place', 'pay_mark_size' );

	private const DESIGN_KEYS = array( 'source', 'variant', 'color', 'color2', 'text', 'radius', 'border_width', 'border_style', 'border_color', 'size', 'full', 'icon', 'icon_style', 'shadow', 'effect', 'attention', 'upper', 'logos', 'still', 'font' );

	private const SPOT_KEYS = array( 'mode', 'total', 'field', 'quantity', 'max_each' );

	// Where the button's text must stay readable on its colour; the variants that fill the plate with colour.
	private const FILLED = array( 'filled', 'split', 'halo', '3d', 'gloss', 'stripe', 'cut', 'clay', 'aurora' );

	/**
	 * The JSON schema the AI answers in. Lowercase types; each provider adapts it. By default every property is
	 * required, so the whole button comes back; as a patch only "reply" is, and the answer holds just what changes: a
	 * shorter answer is quicker and far less likely to be cut off or to time out, which is what slower and free models need.
	 *
	 * @param bool $patch  Whether the answer may leave out what doesn't change.
	 * @param bool $design Whether the look is in play. When it isn't, its fields lose their long explanations: the choices stay, and
	 *                     the schema is a third smaller.
	 * @param bool $site   Whether it is the site's Default Design: only the look is asked for, which is a third of the schema.
	 * @return array<string, mixed>
	 */
	public static function schema( bool $patch = false, bool $design = true, bool $site = false ): array {
		$schema = $site ? self::site( self::build() ) : self::build();

		if ( ! $patch ) {
			return $schema;
		}

		if ( ! $design ) {
			foreach ( array( 'button', 'checkout' ) as $section ) {
				$schema['properties'][ $section ] = self::bare( $schema['properties'][ $section ] );
			}
		}

		$schema['required'] = array( 'reply' );

		// These sections are laid over the current ones key by key. The form and the spots' options are lists, so they stay whole.
		foreach ( array( 'payer', 'spots', 'button', 'checkout' ) as $section ) {
			unset( $schema['properties'][ $section ]['required'] );
		}

		return $schema;
	}

	/**
	 * The schema cut down to the Default Design: the reply, the button's design and the checkout's look, with no drawn code
	 * and no way to follow the site (it is the site). Small enough for a free plan's token limit.
	 *
	 * @param array<string, mixed> $schema The full schema.
	 * @return array<string, mixed>
	 */
	private static function site( array $schema ): array {
		$schema['properties'] = array_intersect_key( $schema['properties'], array_flip( array( 'reply', 'button', 'checkout' ) ) );

		foreach ( array(
			'button'   => array( 'css', 'js', 'source' ),
			'checkout' => array( 'code', 'css', 'js' ),
		) as $section => $keys ) {
			foreach ( $keys as $key ) {
				unset( $schema['properties'][ $section ]['properties'][ $key ] );
			}

			$schema['properties'][ $section ]['required'] = array_values( array_diff( $schema['properties'][ $section ]['required'], $keys ) );
		}

		// The source is only theme or custom here, and the style below needs custom.
		$schema['properties']['button']['properties']['source'] = self::choice( ButtonDesign::OWN_SOURCES, 'custom: use the style below; pick it whenever you design the button. theme: look like the site theme\'s own buttons, ignoring the style below; only when the merchant asks for that.' );
		$schema['properties']['button']['required'][]           = 'source';
		$schema['required']                                     = array_keys( $schema['properties'] );

		return $schema;
	}

	/**
	 * A schema without its descriptions, at every depth.
	 *
	 * @param array<string, mixed> $schema A schema, or a part of one.
	 * @return array<string, mixed>
	 */
	private static function bare( array $schema ): array {
		unset( $schema['description'] );

		foreach ( $schema as $key => $value ) {
			if ( is_array( $value ) && 'enum' !== $key ) {
				$schema[ $key ] = self::bare( $value );
			}
		}

		return $schema;
	}

	/**
	 * The full schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function build(): array {
		$hex   = self::text( 'A colour as #rrggbb.' );
		$maybe = self::text( 'A colour as #rrggbb, or "" to let it be worked out.' );

		return self::shape(
			array(
				'plan'        => self::text( 'Your scratch pad, written before you draw, and never shown. Only when you draw the button or the checkout in code, otherwise "". In at most 100 words: each colour the merchant named and the big area of the plate it fills; the motifs and which side each sits on; the numbers, in em: each motif\'s offset from its edge plus its width, and the padding on that side, which must be at least offset plus width plus 0.6; the one hover motion.' ),
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
							'id'             => self::text( 'Unique id: lowercase letters, digits and _ (e.g. "bouquet"). Keep the id of a field that already exists, even when you rename it. For the two fixed blocks it is "amount" or "details".' ),
							'type'           => self::choice( array_merge( FormFields::ANCHORS, FormFields::INPUT_TYPES, array( FormFields::TYPE_CONTENT, FormFields::TYPE_PAGE ) ), 'amount: where the amount shows (fixed block, exactly once). details: where the payer details go (fixed block, exactly once). text, textarea, email, tel, number, date: inputs. select (dropdown), radio (single choice), checkboxes (multiple choice): pick from options. checkbox: one tick box to agree to something. content: a text block for the payer to read. page: a page break that starts a new step titled by its label.' ),
							'label'          => self::text( 'The question, the text block\'s text, or the new step\'s title. "" for amount and details.' ),
							'required'       => self::flag( 'The payer must answer it. Only for inputs.' ),
							'placeholder'    => self::text( 'Hint inside an empty text, textarea, email, tel, number or select field, or "".' ),
							'help'           => self::text( 'A short line under the field, or "".' ),
							'icon'           => self::choice( array_merge( array( '' ), FieldIcons::names() ), 'A small icon before the field\'s title (user for a name, mail for an email, phone, calendar or clock for dates and times, pin or home for addresses, note for messages, and so on), or "" for none. Only for inputs other than a single checkbox; use sparingly and consistently.' ),
							'options'        => self::listing( self::text( 'An option, up to 100 characters.' ), 'The choices of a select, radio or checkboxes field (up to 30), otherwise [].' ),
							'pictures'       => self::flag( 'Radio or checkboxes only: show the options as picture cards. An option with no picture gets a coloured tile, so turn it on only when most options have one.' ),
							'option_details' => self::listing(
								self::shape(
									array(
										'option' => self::text( 'The option, exactly as in options.' ),
										'price'  => self::number( 'What picking it adds to the amount, in euros; 0 adds nothing. Not for the spots field: its prices are in spots.options.' ),
										'spots'  => self::integer( 'How many of this option there are; 0 means no limit. Not for the spots field.' ),
										'image'  => self::integer( 'The id of a picture from the media library list, or 0 for none. Never invent an id.' ),
									),
									''
								),
								'One entry per option of a select, radio or checkboxes field, otherwise []. Keep what is there unless the merchant asks to change it.'
							),
							'width'          => self::choice( array( 'full', 'half' ), 'half puts two short inputs side by side.' ),
							'when'           => self::shape(
								array(
									'field'  => self::text( 'The id of an earlier input field to look at (any select, radio, checkboxes, checkbox, number, text, textarea, email, tel or date field), or "" to always show. On a page item, it hides the whole page, and every field on it, unless the answer meets the rule.' ),
									'op'     => self::choice( FormFields::CONDITION_OPS, 'is / not: the answer is one of values, or none of them (select, radio, checkboxes, checkbox; also a number equal to the value). gt / lt: a number field is more than / less than the one number in values. contains: a typed text has these words in it, ignoring capitals (e.g. show a field when a text says "wow"). On text, textarea, email, tel and date fields is / not mean exactly that text, ignoring capitals. filled / empty: the field has any answer, or none (values []).' ),
									'values' => self::listing( self::text( 'For is / not on a choice: one of that field\'s options exactly, or "1" for a ticked checkbox. For gt / lt (and is / not on a number): one number as text, e.g. "10". For is / not / contains on a text field: the one text to compare with. For filled / empty: none.' ), '' ),
								),
								'Show this item (or page) only for some answers to an earlier field. field "" means it always shows. The amount and details items, and the page that holds them, can never be hidden.'
							),
							'rules'          => self::shape(
								array(
									'min'     => self::text( 'Number fields only: the smallest number allowed, e.g. "1", or "" for none.' ),
									'max'     => self::text( 'Number fields only: the largest number allowed, e.g. "10" for guests, or "" for none.' ),
									'whole'   => self::flag( 'Number fields only: whole numbers only (guests, tickets, years).' ),
									'min_len' => self::integer( 'Text, textarea and tel fields only: the fewest characters, or 0.' ),
									'max_len' => self::integer( 'Text, textarea and tel fields only: the most characters, or 0.' ),
								),
								'Limits the server holds the payer to. Always set them when a number has a natural range (guests 1 to 10, age 0 to 120, quantity 1 to 20) and whole when it counts things; leave the rest empty.'
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
						'source'       => self::choice( ButtonDesign::SOURCES, 'custom: use the style below; pick it whenever you design the button, always for a new one. site: the site\'s default button design, and everything below is ignored; only when the merchant asks for the site\'s default. theme: look like the site theme\'s own buttons, also ignoring the style below. code: you draw the button yourself in "css" (see the instructions on drawn buttons); every other style key below is then ignored. Use it only when no ready-made variant fits the subject, never when one does.' ),
						'variant'      => self::choice( ButtonDesign::VARIANTS, 'filled, outline, soft (tinted), aurora (color and color2 slowly drifting, alive without being loud), split (label on color, an end block in color2 holding the icon, very clear call to action), halo (a lit pill with a soft coloured ring and glow), glass (frosted), 3d (raised with a darker edge), neon (glowing outline, best on dark pages), brutal (thick ink border, hard offset shadow) ghost (text only) luxe (sharp metallic gold-style plate with a fine inner frame, color into color2; premium, jewellery, fashion, high-end services), beam (dark plate with a light of color and color2 endlessly circling its edge; tech, SaaS, gaming, night looks), clay (soft inflated pillow with gentle highlights, no hard edges; friendly, kids, wellness, food) ticket (a coupon with notched sides and a dashed tear line; events, tickets, vouchers, offers), gloss (a glassy jelly with a bright highlight band; playful, shops, apps), ring (a solid button with a thin ring floating round it; clean, modern, SaaS), stripe (candy stripes that slide on hover; sales, food, fun) cut (chamfered corners with an accent edge in color2; tech, gaming, sport), or one of the squared styles whose background moves on its own: checker (a chequerboard sliding along the colour; retro, sport, fun), hazard (caution tape in ink and the colour running round the plate; warnings, builds, industry, sales), scanline (a dark screen in the colour with a bright bar sweeping down; tech, gaming, terminals), marquee (a plate edged with chasing lights in color2; shows, cinema, tickets, nightlife), pixel (stepped 8-bit corners with a glint; games, retro, kids) blueprint (a drifting drafting grid behind an outline; engineering, architecture, SaaS), spectrum (a many-coloured rainbow gradient; always the default rainbow, color and color2 are ignored; playful, pride, creative, kids), or the food styles, for donut shops, bakeries, cake and pizza places: glazed (a donut seen from above: dough ring, icing in color2 with drips and sprinkles), frosting (a slice of cake: sponge in color, thick scalloped frosting in color2, a cherry on top) or coder (a dark terminal where code types itself out; colour is the green phosphor), medic (a dark heart monitor with a glowing heartbeat line and a cross in colour), music (colour into violet with an equalizer), pilot (a sky in colour, a paper plane climbing through streaming air), school (an exercise-book page in colour with ruled lines, a red margin and a pencil that scribbles on hover; for schools, courses, tutoring and stationery), cartoon (a comic sticker: thick dark outline, hard offset shadow, shine and a star, boings on hover; for kids, games, toys and playful brands), basketball (hardwood planks with a three-point arc and a ball that bounces on hover; colour a wood tone), running (a track with lane lines, a chequered finish and a sprinter; colour a terracotta), football (a mown pitch in colour with a ball on the right that rolls on hover; colour a grass green), pizza (a pizza seen from above: a puffy golden baked crust with charred specks, tomato sauce in color2 just inside it, melted glossy cheese in color with a few drips, pepperoni in color2 and basil leaves at the ends; pizzerias, takeaway, Italian food). For a food button pick the dough or cheese colour as color and the icing, cream, tomato or pepperoni colour as color2; for pizza use a cheese yellow like #f6c344 and a tomato red like #c8321f.' ),
						'color'        => $hex,
						'color2'       => $maybe,
						'text'         => $maybe,
						'radius'       => self::integer( 'Corner radius in px, 0 to 32; 32 is a pill.' ),
						'border_width' => self::integer( 'Border width in px, 0 to 6. 2 is usual.' ),
						'border_style' => self::choice( ButtonDesign::BORDER_STYLES, 'double needs 3px or more.' ),
						'border_color' => self::text( 'The border colour as #rrggbb, or "" for the style\'s own.' ),
						'size'         => self::choice( ButtonDesign::SIZES, '' ),
						'full'         => self::flag( 'Full width.' ),
						'icon'         => self::choice( ButtonDesign::ICONS, 'Pick by subject: cart for shops, gift for gifts and vouchers, ticket for events, calendar for bookings and classes, star for premium, sparkles for beauty and anything special, paw for pets, leaf for plants, food and eco, heart for donations, lock or card for trust, bolt for fast or instant, arrow to go on.' ),
						'icon_style'   => self::choice( ButtonDesign::ICON_STYLES, 'How the icon is drawn: outline (the default), filled (solid shapes) or duotone (soft fill under the outline).' ),
						'shadow'       => self::flag( '' ),
						'effect'       => self::choice( ButtonDesign::EFFECTS, 'On hover: lift, grow, shine (a light sweep), glow, fill (colour sweeps in) nudge (the icon moves; needs an icon) gild (a fine frame draws in and light sweeps across; pairs with luxe) or tilt (a playful springy tilt and swell).' ),
						'attention'    => self::choice( ButtonDesign::ATTENTIONS, 'A looping effect that draws the eye until hovered: pulse, ripple, heartbeat, wiggle, bounce, shimmer, breathe or orbit (light running round the edge). Use none unless the button should stand out, e.g. donations or a sale.' ),
						'upper'        => self::flag( 'Capital letters.' ),
						'font'         => self::choice( ButtonDesign::FONTS, 'The button\'s lettering. Empty: the style\'s own. inter, outfit, grotesk (Space Grotesk): clean sans; playfair: elegant serif; mono: typewriter; pixelify, silkscreen, vt323, press-start: pixel / 8-bit fonts for games, retro and kids; fredoka: soft rounded, friendly (bakeries, donut shops, kids); pacifico: casual brush script (cafes, bakeries, surf); chewy: chunky cartoon lettering (sweets, party, kids).' ),
						'still'        => self::flag( 'Pause the style own animation: the moving background of aurora, beam, stripe, checker, hazard, scanline, marquee, pixel and blueprint, and the loops and hover motion of the sports and profession styles (football, basketball, running, coder, medic, music, pilot) and the food styles. true (the default) holds it still, lighter on older phones; false lets it move.' ),
						'logos'        => self::flag( 'Show the payment method logos under the button.' ),
						'css'          => self::text( 'Only with source "code": the CSS that draws the button, up to 20000 characters, otherwise "". Keep the existing CSS when you change something else; send the whole stylesheet again when you change the drawing.' ),
					) + ( current_user_can( 'unfiltered_html' ) ? array( 'js' => self::text( 'Only with source "code": optional JavaScript for the button, "" when CSS is enough. Write it when the button needs what CSS cannot do: physics or a ball that bounces, following the pointer, a burst of particles, a counter, a canvas. See "Script" in the instructions on drawn buttons. Keep the existing script when you change something else.' ) ) : array() ),
					'How the button looks.'
				),
				'checkout'    => self::shape(
					array(
						'own'                => self::flag( 'true: this button has its own checkout look (the values below). false: it uses the site\'s look and the values below are ignored.' ),
						'accent'             => $hex,
						'surface'            => self::text( 'The card\'s background colour as #rrggbb. Keep it light unless the whole look is dark.' ),
						'ink'                => self::text( 'The text colour as #rrggbb; it must read well on surface.' ),
						'radius'             => self::integer( 'Card corner radius in px, 0 to 32.' ),
						'field_radius'       => self::integer( 'Field corner radius in px, 0 to 24.' ),
						'fields'             => self::choice( CheckoutLook::FIELD_STYLES, 'outline, soft (filled), underline, pixel, well (sunken fields, the button a raised key), stamp (dashed, coupon-like; the button too), marker (tinted fill with a brand bar on the left; the button too), doily (a fine dotted inner border with a hairline outside and a soft tint at the centre; bakery, cafe and boutique looks).' ),
						'frame'              => self::choice( CheckoutLook::FRAMES, 'The checkout card\'s edge: none, a fine hairline in the accent, gilded (hairline, a glint along the top and a glow round it; premium and luxury looks), pixel, matte (a tinted picture-frame band inside the edge), glow (a lit brand-colour edge with a soft halo), long (a flat long shadow off the lower right) or awning (a striped shop awning with a scalloped valance across the top; bakeries, donut shops, cafes). The button picks up the edge too.' ),
						'font'               => self::choice( CheckoutLook::FONTS, 'system: a clean system font. theme: the site theme\'s font. inter, outfit, grotesk, playfair (serif), mono (typewriter), or the pixel fonts pixelify, silkscreen, vt323, press-start, or the friendly fredoka (soft rounded), pacifico (bakery script) and chewy (cartoon).' ),
						'width'              => self::choice( array_keys( CheckoutLook::WIDTHS ), '' ),
						'backdrop'           => self::choice( CheckoutLook::BACKDROPS, 'Behind the checkout popup on a page: blur, dim or light.' ),
						'steps'              => self::choice( CheckoutLook::STEP_STYLES, 'How a form with pages shows where the payer is: bars (a segment per page), labels (segments with page names), line (one bar with a %), numbers (numbered circles), dots, ring (a round meter), text (just the words), chevrons (arrow-shaped breadcrumb blocks), stairs (bars rising like steps), pills (numbered chips, the current one showing its title), counter (a big % with a hairline), gauge (a half-dial, a slice a page), pixel (8-bit health bar) donuts (a glazed donut per page, sprinkles when done; bakeries, donut shops, sweets, kids) or pizza (the pages are wedges of pizzas of at most six slices, more pages mean more pizzas; the current slice is pulled out and gets its toppings as it is answered, and on the last page the slices come back together into whole pizzas; pizzerias, takeaway, restaurants, food).' ),
						'dark'               => self::choice( CheckoutLook::DARK_MODES, 'auto follows the payer\'s device dark mode.' ),
						'dark_surface'       => self::text( 'The card\'s background in dark mode as #rrggbb. Default is a navy; set it when the merchant asks for another dark: neutral charcoal or black (#121212, #18181b, #0e0e10) is the safe choice, a warm near-black (#14110f) suits luxury.' ),
						'dark_ink'           => self::text( 'The text colour in dark mode as #rrggbb, light enough to read on dark_surface (e.g. #f4f1ea).' ),
						'dark_accent'        => self::text( 'The accent in dark mode as #rrggbb, or "" to keep the accent. Set it when the accent is too dark to show on dark_surface (e.g. gold #d4af37 on black).' ),
						'pay_bg'             => self::choice( CheckoutLook::PAY_BGS, 'The pay page background: glow (soft accent light), plain (pay_color), gradient (pay_color into pay_color2). image and video only when the button already has one; you can\'t add media.' ),
						'pay_color'          => $maybe,
						'pay_grad'           => self::choice( CheckoutLook::PAY_GRADS, 'Gradient style: soft, aurora or diagonal.' ),
						'pay_color2'         => $maybe,
						'pay_fx'             => self::choice( CheckoutLook::PAY_FX, 'A pattern over the background: crystals (low-poly facets), waves (flowing lines), bokeh (soft out-of-focus lights), grain (film noise), dots (a halftone dot grid), grid (blueprint lines), stripes (fine diagonal pinstripes), rings (ripples that spread out from the card), gilt (fine accent crosshatch with a slow sheen; luxury looks), liquid (glossy blobs in the accent and second colour that flow and merge), mesh (soft clouds of the accent, second colour and white that drift and blend), tide (layered waves rolling past), rays (beams of the second colour fanning from the top; premium, events), spotlight (a soft light behind the card; minimal, dark looks), starlight (tiny twinkling stars; night, luxury, tech) satin (a silky sheen sliding across; fashion, beauty), confetti (colourful flecks drifting down in layers; celebrations, events, kids, fundraising), hexagons (a honeycomb; tech, science, food, craft), diamonds (a harlequin check; fashion, luxury, classic), scales (overlapping fish scales; seafood, marine, dragons, games), chevrons (rows of arrowheads rising up; sport, logistics, finance, progress), bubbles (clear rings floating up; drinks, cleaning, kids, spas), fireflies (warm drifting lights; night events, magic, cosy, hospitality), sprinkles (glossy candy rods and dots falling past in layers; donut shops, bakeries, kids, parties), pizza (pepperoni, mushrooms, olives, peppers, tomato and basil drifting past in layers; pizzerias, takeaway, Italian food), gingham (a picnic-cloth check in the accent; pizzerias, bakeries, cafes, picnic, rustic) or none.' ),
						'pay_fx_size'        => self::integer( 'Pattern size, 40 to 400.' ),
						'pay_fx_strength'    => self::integer( 'Pattern strength, 0 to 100.' ),
						'pay_fx_still'       => self::flag( 'Hold the pattern still. true keeps it frozen (lighter on older phones); false lets it move (liquid, mesh, tide, rings, bokeh, waves, rays, spotlight, starlight, satin, confetti, hexagons, diamonds, scales, chevrons, bubbles and fireflies come alive). Payers who ask their device for less motion always get it still.' ),
						'card_opacity'       => self::integer( 'Card opacity, 0 to 100. Below 100 the card turns to frosted glass over the background; 70 to 92 reads best.' ),
						'card_blur'          => self::integer( 'How much the glass card blurs what is behind it, 0 to 40 px; 14 to 20 is usual. Only matters below 100 opacity.' ),
						'scene'              => self::flag( 'Open the checkout full screen on the pay page background, instead of a popup over the page.' ),
						'pay_logo'           => self::flag( 'Show the site logo, or the merchant\'s own picture, with the card on the pay page.' ),
						'pay_mark_place'     => self::choice( CheckoutLook::MARK_PLACES, 'Where the logo goes: above the card, or inside it at the top.' ),
						'pay_mark_size'      => self::integer( 'Width of the logo or picture in px, 40 to 480; 120 to 200 is usual.' ),
						'card_skin_strength' => self::integer( 'How visible the card design is, 0 to 100; 30 to 60 is subtle and usual.' ),
						'card_skin'          => self::choice( CheckoutLook::CARD_SKINS, 'A still design on the card itself: dots (fine dots, notebook), notebook (ruled lines and a red margin), graph (graph paper), polka (big soft dots), corner (a soft light from the corner), or pizzeria (a tomato edge over a cardboard weave; pizza places), none.' ),
					) + self::checkout_code_keys(),
					'How the checkout, the pay page and the result popups look.'
				),
			),
			''
		);
	}

	/**
	 * The keys with which the AI draws the checkout popup and the pay page itself. Only for a user who may post unfiltered HTML:
	 * a stylesheet that replaces the whole payment screen is as powerful as that, and the editor holds it to the same rule.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function checkout_code_keys(): array {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return array();
		}

		return array(
			'code' => self::flag( 'true: you draw the checkout popup and the pay page yourself, in "css" and "js" below (see "Drawn checkout" in the instructions), and every other value of checkout is then ignored. false: the ready-made values are used. Use it when the merchant asks for a look the ready-made values cannot give (a themed scene, liquid glass, a custom backdrop, an animated card, a checkout that matches a drawn button); never when they can. Keep it true while a drawn checkout is being changed.' ),
			'css'  => self::text( 'Only with code true: the CSS of the checkout popup and the pay page, up to 20000 characters, otherwise "". Keep the existing CSS when you change something else; send the whole stylesheet again when you change the drawing.' ),
			'js'   => self::text( 'Only with code true: optional JavaScript for the checkout and the pay page, "" when CSS is enough (a highlight that follows the pointer, a ball that bounces). Keep the existing script when you change something else.' ),
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
				static fn( array $item ): array => array_intersect_key( $item, array_flip( array( 'id', 'type', 'label', 'required', 'placeholder', 'help', 'icon', 'options', 'width', 'when', 'rules' ) ) ) + array(
					'pictures'       => $item['pictures'],
					'option_details' => self::option_details( $item ),
				),
				$config['form']
			),
			'spots'       => array_intersect_key( $config['spots'], array_flip( array_merge( self::SPOT_KEYS, array( 'options' ) ) ) ),
			'button'      => array_intersect_key( $config['design'], array_flip( self::DESIGN_KEYS ) ) + self::drawn( $config ),
			'checkout'    => array( 'own' => Button::LOOK_OWN === $config['look_mode'] ) + array_intersect_key( $config['look'], array_flip( self::LOOK_KEYS ) ) + self::drawn_page( $config ),
		);
	}

	/**
	 * Why the code in an answer can't be used, or null when it can or there is none. Drawn code is refused whole rather than
	 * half-saved, so the AI gets told and can fix it. The button's CSS is for everyone; script and the checkout's code only for a
	 * user who may post unfiltered HTML, as in the editor.
	 *
	 * @param array<string, mixed> $answer The AI's answer.
	 */
	public static function drawn_problem( array $answer ): ?string {
		$may    = current_user_can( 'unfiltered_html' );
		$fields = array(
			'button_css' => array( 'button', 'css', true ),
			'button_js'  => array( 'button', 'js', $may ),
			'page_css'   => array( 'checkout', 'css', $may ),
			'page_js'    => array( 'checkout', 'js', $may ),
		);

		foreach ( $fields as $field => $where ) {
			$part = is_array( $answer[ $where[0] ] ?? null ) ? $answer[ $where[0] ] : array();
			$code = is_string( $part[ $where[1] ] ?? null ) ? CustomCode::clean( $part[ $where[1] ] ) : '';

			// The checkout's code only counts while it is being used.
			if ( '' === $code || ! $where[2] || ( 'checkout' === $where[0] && empty( $part['code'] ) ) ) {
				continue;
			}

			$error = str_ends_with( $field, '_css' ) ? CustomCode::css_error( $code, true ) : CustomCode::js_error( $code );

			if ( null !== $error ) {
				return CustomCode::message( $field, $error );
			}
		}

		return null;
	}

	/**
	 * A fault in how the drawn button is laid out that is worth one more try, or null. Unlike drawn_problem() it never stops
	 * the drawing from being used.
	 *
	 * @param array<string, mixed> $answer The AI's answer.
	 */
	public static function layout_problem( array $answer ): ?string {
		$button = is_array( $answer['button'] ?? null ) ? $answer['button'] : array();
		$css    = is_string( $button['css'] ?? null ) ? CustomCode::clean( $button['css'] ) : '';

		return '' === $css ? null : Layout::clash( $css );
	}

	/**
	 * The code of a drawn button, in the AI's shape.
	 *
	 * @param array<string, mixed> $config A sanitized config.
	 * @phpstan-param ButtonConfig $config
	 * @return array<string, string>
	 */
	private static function drawn( array $config ): array {
		return array(
			'css' => $config['code']['button_css'],
			'js'  => $config['code']['button_js'],
		);
	}

	/**
	 * The code of a drawn checkout, in the AI's shape. Only for a user who may change it, so the others' answers never carry it.
	 *
	 * @param array<string, mixed> $config A sanitized config.
	 * @phpstan-param ButtonConfig $config
	 * @return array<string, bool|string>
	 */
	private static function drawn_page( array $config ): array {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return array();
		}

		return array(
			'code' => Button::LOOK_CODE === $config['look_mode'],
			'css'  => $config['code']['page_css'],
			'js'   => $config['code']['page_js'],
		);
	}

	/**
	 * A new accent, surface or text colour brings its dark mode along, unless the answer set the dark colours itself: the
	 * dark card is the same hue family as the light one, so the two modes match instead of the dark one staying navy.
	 *
	 * @param array<string, mixed> $look   The look keys the answer set.
	 * @param array<string, mixed> $design The button keys the answer set.
	 * @param array<string, mixed> $base   The config it changes.
	 * @return array<string, mixed>
	 */
	private static function matched( array $look, array $design, array $base ): array {
		$changed = array_intersect_key( $look, array_flip( array( 'accent', 'surface', 'ink' ) ) );

		if ( array() === $changed || isset( $look['dark_surface'] ) || isset( $look['dark_ink'] ) ) {
			return $look;
		}

		$accent = ButtonDesign::hex( (string) ( $look['accent'] ?? $base['look']['accent'] ?? '' ) );
		$ink    = ButtonDesign::hex( (string) ( $look['ink'] ?? $base['look']['ink'] ?? '' ) );

		if ( '' === $accent || '' === $ink ) {
			return $look;
		}

		return $look + self::dark_from( $accent, $ink );
	}

	/**
	 * With nothing said about the button, it keeps matching the checkout: when the accent changes and the button wore the old
	 * accent, it takes the new one.
	 *
	 * @param array<string, mixed> $look   The look keys the answer set.
	 * @param array<string, mixed> $design The button keys the answer set.
	 * @param array<string, mixed> $base   The config it changes.
	 * @return array<string, mixed>
	 */
	private static function followed( array $look, array $design, array $base ): array {
		$new = ButtonDesign::hex( (string) ( $look['accent'] ?? '' ) );
		$old = ButtonDesign::hex( (string) ( $base['look']['accent'] ?? '' ) );

		if ( '' !== $new && $new !== $old && ! isset( $design['color'] ) && ButtonDesign::hex( (string) ( $base['design']['color'] ?? '' ) ) === $old ) {
			$design['color'] = $new;
		}

		return $design;
	}

	/**
	 * A dark mode that matches a light one: a near-black in the accent's hue family, light text with a hint of it, and the
	 * accent lifted when it would be too dark to show on that card.
	 *
	 * @param string $accent The light mode's accent, #rrggbb.
	 * @param string $ink    The light mode's text, #rrggbb.
	 * @return array{dark_surface: string, dark_ink: string, dark_accent: string}
	 */
	private static function dark_from( string $accent, string $ink ): array {
		list( $hue, $sat, $light ) = self::hsl( $accent );
		$tint                      = $sat > 0.12 ? $hue : self::hsl( $ink )[0];

		return array(
			'dark_surface' => self::from_hsl( $tint, min( 0.3, $sat * 0.45 ), 0.09 ),
			'dark_ink'     => self::from_hsl( $tint, min( 0.14, $sat * 0.2 ), 0.93 ),
			'dark_accent'  => $light < 0.55 ? self::from_hsl( $hue, min( 0.8, $sat ), 0.62 ) : '',
		);
	}

	/**
	 * A #rrggbb colour as hue (0 to 360), saturation and lightness (0 to 1).
	 *
	 * @param string $hex The colour.
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function hsl( string $hex ): array {
		$r   = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$g   = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$b   = hexdec( substr( $hex, 5, 2 ) ) / 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		$d   = $max - $min;

		if ( $d < 0.00001 ) {
			return array( 0.0, 0.0, $l );
		}

		$s = $d / ( 1 - abs( 2 * $l - 1 ) );

		if ( $max === $r ) {
			$h = fmod( ( $g - $b ) / $d, 6 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}

		return array( fmod( $h * 60 + 360, 360 ), $s, $l );
	}

	/**
	 * Hue (0 to 360), saturation and lightness (0 to 1) as #rrggbb.
	 *
	 * @param float $h Hue.
	 * @param float $s Saturation.
	 * @param float $l Lightness.
	 */
	private static function from_hsl( float $h, float $s, float $l ): string {
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		if ( $h < 60 ) {
			$rgb = array( $c, $x, 0 );
		} elseif ( $h < 120 ) {
			$rgb = array( $x, $c, 0 );
		} elseif ( $h < 180 ) {
			$rgb = array( 0, $c, $x );
		} elseif ( $h < 240 ) {
			$rgb = array( 0, $x, $c );
		} elseif ( $h < 300 ) {
			$rgb = array( $x, 0, $c );
		} else {
			$rgb = array( $c, 0, $x );
		}

		return sprintf( '#%02x%02x%02x', (int) round( ( $rgb[0] + $m ) * 255 ), (int) round( ( $rgb[1] + $m ) * 255 ), (int) round( ( $rgb[2] + $m ) * 255 ) );
	}

	/**
	 * Lays the AI's answer over a config and cleans it like an editor save.
	 *
	 * @param array<string, mixed> $answer The AI's answer.
	 * @param array<string, mixed> $base   The sanitized config it changes.
	 * @phpstan-param ButtonConfig $base
	 * @param string               $title  The current title, kept when the answer has none.
	 * @param bool                 $prices Whether the AI may set prices; when not, every price stays as it was.
	 * @return array{title: string, config: ButtonConfig}
	 */
	public static function to_config( array $answer, array $base, string $title, bool $prices = true ): array {
		$config = $base;
		$pick   = static fn( string $key, array $from ): array => is_array( $from[ $key ] ?? null ) ? $from[ $key ] : array();
		// A model that can leave things out sometimes writes null instead; that means "as it is".
		$answer = self::without_nulls( $answer );

		if ( ! $prices ) {
			$answer = self::without_prices( $answer, $base );
		}

		foreach ( array( 'summary', 'label', 'amount_mode', 'amount', 'show_amount', 'min', 'max', 'presets', 'note_label', 'first_step' ) as $key ) {
			if ( array_key_exists( $key, $answer ) ) {
				$config[ $key ] = $answer[ $key ];
			}
		}

		// The editor's sanitizer reads "auto" colours from a flag, so an empty one has to say so.
		$design = array_intersect_key( $pick( 'button', $answer ), array_flip( self::DESIGN_KEYS ) );
		$look   = array_intersect_key( $pick( 'checkout', $answer ), array_flip( self::LOOK_KEYS ) );

		$look   = self::matched( $look, $design, $base );
		$design = self::followed( $look, $design, $base );

		$config['fields'] = array_intersect_key( $pick( 'payer', $answer ), array_flip( Button::FIELDS ) ) + $base['fields'];
		$config['form']   = array_key_exists( 'form', $answer ) ? $pick( 'form', $answer ) : $base['form'];
		$config['form']   = self::with_option_details( is_array( $config['form'] ) ? $config['form'] : array(), $base['form'], $prices );
		$config['spots']  = array_intersect_key( $pick( 'spots', $answer ), array_flip( array_merge( self::SPOT_KEYS, array( 'options' ) ) ) ) + $base['spots'];
		// "Auto" follows what ends up set: a colour the answer left out keeps the one the button already has, instead of dropping to auto.
		$config['design'] = $design + array(
			'text_auto'         => '' === ( $design['text'] ?? $base['design']['text'] ?? '' ),
			'color2_auto'       => '' === ( $design['color2'] ?? $base['design']['color2'] ?? '' ),
			'border_color_auto' => '' === ( $design['border_color'] ?? $base['design']['border_color'] ),
		) + $base['design'];
		// A drawn button's code. Script only from a user who may post unfiltered HTML; the CSS goes through the same checks as a typed one.
		$drawn = $pick( 'button', $answer );

		if ( is_string( $drawn['css'] ?? null ) && null === self::drawn_problem( $answer ) ) {
			$config['code']['button_css'] = CustomCode::clean( $drawn['css'] );
		}

		if ( is_string( $drawn['js'] ?? null ) && current_user_can( 'unfiltered_html' ) ) {
			$config['code']['button_js'] = CustomCode::clean( $drawn['js'] );
		}

		$config['look'] = $look + array(
			'pay_color_auto'   => '' === ( $look['pay_color'] ?? $base['look']['pay_color'] ?? '' ),
			'pay_color2_auto'  => '' === ( $look['pay_color2'] ?? $base['look']['pay_color2'] ?? '' ),
			'dark_accent_auto' => '' === ( $look['dark_accent'] ?? $base['look']['dark_accent'] ?? '' ),
		) + $base['look'];

		$page = $pick( 'checkout', $answer );

		if ( ! empty( $page['code'] ) && current_user_can( 'unfiltered_html' ) ) {
			// A drawn checkout is only switched on once it has a stylesheet that passed, since without one the popup has no styles at all.
			// Script goes with it as sent, so an empty one removes the old script; an answer with no CSS changes nothing.
			$page_css = is_string( $page['css'] ?? null ) && null === self::drawn_problem( $answer ) ? CustomCode::clean( $page['css'] ) : '';

			if ( '' !== $page_css ) {
				$config['code']['page_css'] = $page_css;
				$config['code']['page_js']  = is_string( $page['js'] ?? null ) ? CustomCode::clean( $page['js'] ) : $config['code']['page_js'];
				$config['look_mode']        = Button::LOOK_CODE;
			}
		} elseif ( array_key_exists( 'own', $page ) ) {
			$config['look_mode'] = ! empty( $page['own'] ) ? Button::LOOK_OWN : Button::LOOK_SITE;
		}

		$named = is_scalar( $answer['title'] ?? null ) ? mb_substr( sanitize_text_field( (string) $answer['title'] ), 0, 120 ) : '';

		return array(
			'title'  => '' !== $named ? $named : $title,
			'config' => Button::sanitize_config( self::readable( $config ) ),
		);
	}

	/**
	 * The value without its nulls, at every depth.
	 *
	 * @param array<mixed> $value An answer, or a part of one.
	 * @return array<mixed>
	 */
	private static function without_nulls( array $value ): array {
		$list = array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );

		foreach ( $value as $key => $item ) {
			if ( null === $item ) {
				unset( $value[ $key ] );
			} elseif ( is_array( $item ) ) {
				$value[ $key ] = self::without_nulls( $item );
			}
		}

		return $list ? array_values( $value ) : $value;
	}

	/**
	 * Fixes the two things a weaker model gets wrong most: text it can't be read, on the checkout card and on a filled button.
	 * Only a clear miss is touched, and only by swapping the text for dark or light, never the colours the merchant sees as the look.
	 *
	 * @param array<string, mixed> $config The config before it is cleaned.
	 * @return array<string, mixed>
	 */
	private static function readable( array $config ): array {
		$look   = is_array( $config['look'] ?? null ) ? $config['look'] : array();
		$design = is_array( $config['design'] ?? null ) ? $config['design'] : array();

		if ( Button::LOOK_OWN === ( $config['look_mode'] ?? '' ) && self::hex( $look['surface'] ?? null ) && self::hex( $look['ink'] ?? null ) && self::contrast( $look['surface'], $look['ink'] ) < 4.0 ) {
			$config['look']['ink'] = self::ink_for( $look['surface'] );
		}

		if ( ! empty( $design['source'] ) && ButtonDesign::SOURCE_CUSTOM === $design['source'] && in_array( $design['variant'] ?? '', self::FILLED, true ) && empty( $design['text_auto'] ) && self::hex( $design['color'] ?? null ) && self::hex( $design['text'] ?? null ) && self::contrast( $design['color'], $design['text'] ) < 3.0 ) {
			$config['design']['text'] = self::ink_for( $design['color'] );
		}

		return $config;
	}

	/**
	 * Whether a value is a six-digit hex colour.
	 *
	 * @param mixed $value Anything.
	 * @phpstan-assert-if-true string $value
	 */
	private static function hex( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/i', $value );
	}

	/**
	 * Dark ink on a light colour, white on a dark one.
	 *
	 * @param string $background A hex colour.
	 */
	private static function ink_for( string $background ): string {
		return self::luminance( $background ) > 0.4 ? '#1c1b22' : '#ffffff';
	}

	/**
	 * The WCAG contrast ratio of two colours, 1 to 21.
	 *
	 * @param string $a A hex colour.
	 * @param string $b Another.
	 */
	private static function contrast( string $a, string $b ): float {
		$one = self::luminance( $a );
		$two = self::luminance( $b );

		return ( max( $one, $two ) + 0.05 ) / ( min( $one, $two ) + 0.05 );
	}

	/**
	 * A colour's relative luminance.
	 *
	 * @param string $hex A six-digit hex colour.
	 */
	private static function luminance( string $hex ): float {
		$channels = array_map(
			static function ( string $part ): float {
				$value = hexdec( $part ) / 255;

				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			array( substr( $hex, 1, 2 ), substr( $hex, 3, 2 ), substr( $hex, 5, 2 ) )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
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
			$sources = array(
				ButtonDesign::SOURCE_SITE  => __( 'Button uses the default design', 'ifthenpay-pay-button' ),
				ButtonDesign::SOURCE_THEME => __( 'Button follows the theme', 'ifthenpay-pay-button' ),
			);

			$add( $sources[ $after['button']['source'] ] ?? __( 'New button design', 'ifthenpay-pay-button' ) );
		}

		$code_keys = array( 'code', 'css', 'js' );

		if ( ( $before['checkout']['code'] ?? false ) !== ( $after['checkout']['code'] ?? false ) || ( ! empty( $after['checkout']['code'] ) && array_intersect_key( $before['checkout'], array_flip( array( 'css', 'js' ) ) ) !== array_intersect_key( $after['checkout'], array_flip( array( 'css', 'js' ) ) ) ) ) {
			$add( ! empty( $after['checkout']['code'] ) ? __( 'New drawn checkout and pay page', 'ifthenpay-pay-button' ) : __( 'Checkout back to the ready-made look', 'ifthenpay-pay-button' ) );
		}

		$look_before = array_diff_key( $before['checkout'], array_flip( array_merge( array( 'pay_bg', 'pay_fx' ), $code_keys ) ) );
		$look_after  = array_diff_key( $after['checkout'], array_flip( array_merge( array( 'pay_bg', 'pay_fx' ), $code_keys ) ) );

		if ( $look_before !== $look_after && empty( $after['checkout']['code'] ) ) {
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
	 * What each option of a choice field carries, in the AI's shape: its price, its spots and its picture's ID (0 for none).
	 *
	 * @param array<string, mixed> $item A sanitized form item.
	 * @phpstan-param FormItem $item
	 * @return array<int, array{option: string, price: float, spots: int, image: int}>
	 */
	private static function option_details( array $item ): array {
		$out = array();

		foreach ( $item['options'] as $option ) {
			$out[] = array(
				'option' => $option,
				'price'  => $item['prices'][ $option ] ?? 0.0,
				'spots'  => $item['limits'][ $option ] ?? 0,
				'image'  => $item['images'][ $option ] ?? 0,
			);
		}

		return $out;
	}

	/**
	 * The AI's form with each item's option prices, spots and pictures turned back into the maps a button stores. An item that
	 * comes back without them keeps what it had. Pictures have to be real images the merchant may use; prices stay as they were
	 * when this site doesn't let the AI set them.
	 *
	 * @param array<int|string, mixed>         $form      The items the AI sent.
	 * @param array<int, array<string, mixed>> $before The form before this answer.
	 * @param bool                             $may_price Whether the AI may set prices.
	 * @phpstan-param array<int, FormItem> $before
	 * @return array<int|string, mixed>
	 */
	private static function with_option_details( array $form, array $before, bool $may_price ): array {
		$old = array_column( $before, null, 'id' );

		return array_map(
			static function ( $item ) use ( $old, $may_price ) {
				if ( ! is_array( $item ) || ! is_scalar( $item['id'] ?? null ) ) {
					return $item;
				}

				$was    = $old[ (string) $item['id'] ] ?? null;
				$keep   = array(
					'prices'         => $was['prices'] ?? array(),
					'limits'         => $was['limits'] ?? array(),
					'images'         => $was['images'] ?? array(),
					'pictures'       => $was['pictures'] ?? false,
					'picture_shape'  => $was['picture_shape'] ?? 'auto',
					'picture_layout' => $was['picture_layout'] ?? 'grid',
					'picture_cols'   => $was['picture_cols'] ?? 0,
					'picture_size'   => $was['picture_size'] ?? 'medium',
				);
				$result = $keep;

				if ( is_array( $item['option_details'] ?? null ) ) {
					$result = array(
						'prices'         => array(),
						'limits'         => array(),
						'images'         => array(),
						'pictures'       => $keep['pictures'],
						'picture_shape'  => $keep['picture_shape'],
						'picture_layout' => $keep['picture_layout'],
						'picture_cols'   => $keep['picture_cols'],
						'picture_size'   => $keep['picture_size'],
					);

					foreach ( $item['option_details'] as $detail ) {
						$label = is_array( $detail ) && is_scalar( $detail['option'] ?? null ) ? trim( (string) $detail['option'] ) : '';

						if ( '' === $label ) {
							continue;
						}

						$price = is_numeric( $detail['price'] ?? null ) ? (float) $detail['price'] : 0.0;
						$spots = is_numeric( $detail['spots'] ?? null ) ? (int) $detail['spots'] : 0;
						$image = is_numeric( $detail['image'] ?? null ) ? absint( $detail['image'] ) : 0;

						if ( $price > 0 ) {
							$result['prices'][ $label ] = $price;
						}

						if ( $spots > 0 ) {
							$result['limits'][ $label ] = $spots;
						}

						// Only a picture the merchant can see in their own library; a made-up ID drops off.
						if ( $image > 0 && wp_attachment_is_image( $image ) && current_user_can( 'read_post', $image ) ) {
							$result['images'][ $label ] = $image;
						}
					}

					if ( ! $may_price ) {
						$result['prices'] = $keep['prices'];
					}
				}

				if ( array_key_exists( 'pictures', $item ) ) {
					$result['pictures'] = ! empty( $item['pictures'] );
				}

				unset( $item['option_details'] );

				return array_merge( $item, $result );
			},
			$form
		);
	}

	/**
	 * The answer with the merchant's own prices put back: the amount and its range, and each option's price,
	 * matched by its text. A new option costs the button's amount.
	 *
	 * @param array<string, mixed> $answer What the AI gave.
	 * @param array<string, mixed> $base   The button before this answer.
	 * @phpstan-param ButtonConfig $base
	 * @return array<string, mixed>
	 */
	private static function without_prices( array $answer, array $base ): array {
		foreach ( array( 'amount_mode', 'amount', 'min', 'max', 'presets' ) as $key ) {
			unset( $answer[ $key ] );
		}

		if ( ! is_array( $answer['spots']['options'] ?? null ) ) {
			return $answer;
		}

		$kept = array_column( $base['spots']['options'], 'price', 'label' );

		foreach ( $answer['spots']['options'] as $index => $option ) {
			if ( is_array( $option ) ) {
				$label = is_scalar( $option['label'] ?? null ) ? (string) $option['label'] : '';
				$answer['spots']['options'][ $index ]['price'] = $kept[ $label ] ?? 0;
			}
		}

		return $answer;
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

		// The other choice fields' option prices, which add to the amount.
		foreach ( $config['form'] as $item ) {
			foreach ( $item['prices'] as $label => $price ) {
				/* translators: 1: a field and its option, e.g. "Extras › Gift wrap", 2: what it adds */
				$lines[] = sprintf( __( '%1$s: +%2$s', 'ifthenpay-pay-button' ), $item['label'] . ' › ' . $label, CheckoutService::format_money( $price ) );
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
