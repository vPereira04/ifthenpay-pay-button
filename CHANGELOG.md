# Changelog

All notable changes to ifthenpay | Pay Button are listed here. Releases that fix a security issue say so in their entry, prefixed with **Security:**.

## [1.0.0] — Unreleased

Initial public release.

### Changed

- Payment links always stay open for Gateway Configuration › **Expiry days**; the per-button "Link stays open (days)" setting is gone.
- The "Checking your payment" popup wears the paying button's own look from the start, instead of the site's look until the status came back.
- The **Default Checkout** screen is now **Default Design**, with Checkout, Button, Messages, Texts and Links & emails tabs. Its preview stays in view while the page scrolls.
- The looks to **start from** set the pay page background too (colour, gradient, effect and the card's glass), not only the colours and shape.

### Added

- Pay buttons for any page: the `[ifthenpay_button]` shortcode (`id`, `label`, `align`), the **ifthenpay Pay Button** block, and a pay page per button at `/pay/{button}/` (the base is configurable).
- Amount modes: **Fixed**, **Payer chooses** (suggested amounts, minimum and maximum) and **From the link**.
- Checkout form builder: name, email, phone, tax number (NIF) and note as Off, Optional or Required, plus custom questions (short text, paragraph, email, phone, number, date, dropdown, single choice, multiple choice, agreement, text block) and page breaks with a step bar.
- **Spots**: No limit, Limited spots, or Spots per option with a price per option, an optional total, a quantity picker, "Only X left" and sold-out states. An unpaid payment holds its spot until its ifthenpay link expires.
- Button design: match the theme's buttons or a custom style (presets, colours, corners, border, size, icon, width, shadow, payment logos under the button). Custom styles are Filled, Outline, Soft, **Gradient** (with a second colour, or one picked for you), **Glass**, **3D**, **Neon**, **Brutal** and **Ghost**, picked from tiles that show each one in your colours. Presets: ifthenpay, Midnight, Outline, Soft, Ruby, Bold, Sunset, Glass, Candy, Neon, Brutal and Dashed.
- **Default button design** (Default Design › Button): buttons set to **Default design**, as new buttons are, all wear it and change together. **Save and make it the default** on a button's own design makes it the default.
- Seven more checkout looks to start from: **Ocean**, **Sunset**, **Prism**, **Blossom**, **Neon**, **Noir** and **Latte**. The swatches show each look's background.
- Button **border**: width (0–6 px), line (solid, dashed, dotted or double) and its own colour, or the style's.
- Button **hover effects**: Lift, Grow, Shine, Glow, Fill (the colour sweeps in) or Nudge (the arrow slides, other icons wiggle).
- Button **attention effects** that loop to catch the eye and rest while the button is hovered: Pulse, Ripple, Heartbeat, Wiggle, Bounce, Shimmer, Breathe or Orbit (a spark running round the edge). Payers who turn motion off get a still button. A button saved with the old Pulse effect keeps pulsing.
- Checkout look, site-wide or per button: colours, shape, fonts, pay page background (glow, gradient, plain, image or **video**, with effects), popups and Custom CSS, with a live preview.
- Pay page **video** background: an MP4 or WebM from the Media Library, or a YouTube or Vimeo link, looping muted behind the card, with darken and blur. It loops whole, or only a part of it (From and To, e.g. 0:12 to 0:45). The background image shows while it loads and to payers who turn motion off; a checkout opened on it only loads the video when it opens.
- Two more generated pay page effects next to Crystals and Grain: **Waves** (contour lines with lit and shaded bands) and **Bokeh** (soft lights in the accent and second colour). Both are seamless SVG tiles, drawn the same in the preview.
- **Page progress** for forms with pages, picked from cards that draw each one: Bars, Named bars (each page's title, the current one widest), One line (with a %), Numbers (circles whose rim fills, then tick), Dots (the current page stretches and fills), Ring (a slice per page around the page number) or Just words. Each shows how much of the current page is answered.
- **Checkout card opacity** and **Glass blur**: under 100% the checkout card, the pay page card and the popups turn to frosted glass over the background.
- **Dark mode**: Off or Follow the payer's device, with its own background, text and accent colours. The Design preview has a light/dark (sun/moon) switch to see both before saving.
- **Texts**: every payer-facing string can be rewritten, in any language. **Messages**: the Paid, Pending, Failed and Cancelled popups, or a WordPress page or custom URL when paid, with merge tags.
- **AI assistant**: connect your own Gemini, Claude or ChatGPT key (encrypted with the site's secret keys, under a storage name that says nothing), then **Build with AI** or **Edit with AI** in a chat. The studio shows the real pay page as it's built, on desktop or a phone or in a full-page tab, lists every price the AI set, and has Undo and Start over. Nothing is saved until you save in the editor, and a new AI-built button starts switched off until you turn on Take payments. A busy or retired model falls back to another one the key has.
- Bridge for other plugins: a redirect link builder with merge tags, "Go straight to ifthenpay", signed links through `iftp_btn_payment_url()`, and a signed outgoing webhook (`X-Iftp-Signature`) with retries and a test ping.
- Gateway Configuration: Backoffice Key connect/disconnect, Gateway Key choice, payment methods table with a default method and **Request Activation**, hosted payment page description with merge tags, expiry days and the weekly summary email.
- ifthenpay callback with anti-phishing key check on every status, signed return and result links, and automatic expiry of unpaid payments.
- Payments screen: performance chart, totals by status, search, filters, columns, CSV export, single payment view with timeline, manual status change, copy payment link and payment email.
- Payer receipt and owner notice once ifthenpay confirms a payment; Dashboard widget; WordPress personal data export and erase.
- Method logos switch to ifthenpay's dark variants on dark backgrounds, with no box around them.
- Conditional questions: any checkout question or text block can show only when an earlier choice or agreement is (or isn't) answered a certain way. Hidden questions are neither required nor stored.
- **Open the checkout on this background** (on by default): clicking a pay button opens the checkout, and the popups after paying, over the pay page's background instead of a dimmed page.
- **Send to your invoicing app** in a button's After payment tab: each paid payment goes straight to InvoiceXpress, Moloni, Vendus, TOConline or any API, as JSON or form fields written with merge tags (plus `{amount_raw}`, `{unit_price}`, `{nif_final}` and `{date_iso}`), with custom headers, retries, a test that shows the app's reply, and a log of the last deliveries.
- Buttons screen stats: money collected with the last 30 days drawn under it, the share of opened checkouts that weren't paid (per button and overall), and how full the spots are.
