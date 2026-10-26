=== ifthenpay | Pay Button ===
Contributors: ifthenpay
Tags: payments, pay button, donations, multibanco, mb way
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take ifthenpay payments anywhere: pay buttons for any page, shareable pay links, and a link builder for plugins ifthenpay has no integration with.

== Description ==

ifthenpay | Pay Button lets you take ifthenpay payments on any WordPress site, with no shop, form or booking plugin needed. You create a pay button, set its amount and the details you need, and place it on any page with a shortcode or a block. Every button also has its own pay page, so you can share it as a link.

The payer fills in a short checkout, then pays on ifthenpay's secure hosted payment page with any method enabled on your account. ifthenpay confirms the payment to your site with a server-side callback, and the payer comes back to a "Paid" message, a page or a URL of your choice. No card or bank details ever touch your site.

Already using a form or booking plugin ifthenpay has no integration with? Point its "redirect after submit" at a button's pay link and fill in the amount, name and email with that plugin's own merge tags. A signed webhook tells the other plugin (or Zapier, Make or n8n) when the payment is paid.

In plain terms you get:

* Pay buttons for any page or post, as a shortcode or a block, plus a pay page per button to share as a link
* Fixed amounts, amounts the payer chooses (donations, tips) or amounts sent by another plugin
* Your own checkout questions, multi-step checkouts, and limited spots per button or per option (dates, sessions, sizes)
* A Payments screen with totals, filters and CSV export, receipts by email, and secure automatic payment confirmations (no card numbers stored)

All settings are made in Pay Button in your WordPress admin and in your ifthenpay Backoffice. The plugin is built so site owners can take payments without needing deep technical knowledge.

== Key Features ==

1. Pay buttons that work on any page: shortcode `[ifthenpay_button id="…"]`, an "ifthenpay Pay Button" block, and a pay page per button (`/pay/{button}/`)
2. Three amount modes: Fixed, Payer chooses (with suggested amounts and a minimum and maximum) and From the link
3. A checkout form builder: name, email, phone, tax number (NIF) and note, plus your own questions (text, number, date, dropdown, single or multiple choice, agreement, text blocks) and page breaks for multi-step checkouts, with seven page-progress styles (bars, named bars, one line, numbers, dots, ring or just words); any question or text block can show only for some answers (e.g. a dietary question just for the in-person day)
4. Spots: limit how many people can pay, in total or per option, each option with its own price; the payer can pick a quantity, and sold-out options are greyed out
5. Full design control: match your theme's buttons or design your own (filled, outline, soft, gradient, glass, 3D, neon, brutal or ghost, with your own border, a hover effect such as lift, grow, glow or fill, and an attention effect such as pulse, heartbeat, wiggle or a spark orbiting the edge), plus a site-wide (or per-button) look for the checkout, the pay page and the popups, with a live preview: a glow, gradient, image or looping video background with Crystals, Waves, Bokeh or Grain, a see-through frosted-glass card, and a dark mode that follows the payer's device; the checkout can open over the pay page's background instead of a dimmed page
6. Every payer-facing word can be rewritten in the Texts tab, in any language
7. After payment: a popup message, a WordPress page or a custom URL, with merge tags such as {name}, {amount} and {payment_id}
8. A bridge for any other plugin: a redirect link builder, signed links for developers, and a signed outgoing webhook (Zapier, Make, n8n); plus a direct request to your invoicing app (InvoiceXpress, Moloni, Vendus, TOConline…) the moment a payment is paid, written in that app's own format with merge tags
9. Support for multiple payment methods (Multibanco, MB WAY, Payshop, Credit Card, Google Pay, Apple Pay, Pix and more — whatever is enabled on your Gateway Key), synced live from your ifthenpay account, with in-admin "Request Activation" for methods not yet enabled
10. Secure redirect to ifthenpay's hosted payment page, in Portuguese, English, Spanish or French to match your site's language
11. Revenue, the share of payers who opened the checkout but didn't pay, and how full the spots are, right on the Buttons screen; a Payments screen with charts, totals by status, search, filters, columns, CSV export, manual status changes and payment emails, plus a Dashboard widget and an optional weekly summary email
12. Receipts for the payer and a notice for you, sent once ifthenpay confirms a payment
13. Security first: no card data stored, anti-phishing key on every callback, signed return and result links

== Requirements ==

* An active ifthenpay merchant account — [subscribe here](https://ifthenpay.com/aderir/) to obtain your credentials.
* Your ifthenpay Backoffice Key, and a Gateway Key for ifthenpay Pay Button (request it from ifthenpay support/helpdesk).
* The payment methods you want enabled on that Gateway Key (our helpdesk team will guide you).
* WordPress 6.4+ and PHP 7.4+. No other plugin is needed.
* HTTPS (SSL) enabled on your site.

== Installation ==

1. **Install:** Upload the plugin zip via `Plugins → Add New → Upload`, or install from WordPress.org and Activate.
2. **Credentials:** Ensure your ifthenpay account has an active Pay Button Gateway Key with the desired payment methods enabled.
3. **Step 1 — Connect:** Open Pay Button › Gateway Configuration and click Connect under "ifthenpay account" [1], paste your Backoffice Key [2] and click Connect [3] (screenshot 1).
4. **Step 2 — Gateway Key and methods:** Choose your Gateway Key, only needed if you have more than one [1]. Switch on the payment methods to offer and star the default one the ifthenpay page opens on [2]. For a method that isn't active on your contract yet, click Request Activation [3]. Click Save Settings [4] (screenshot 2).
5. **Step 3 — ifthenpay page:** The Description is what the payer reads on the ifthenpay page; click a tag to fill in each payment's details, e.g. {button} [1]. Expiry days is how long an unpaid payment link stays open before it's marked Expired [2]. Optionally switch on the weekly summary email, sent every Monday [3]. Click Save Settings [4] (screenshot 3). These settings apply to every button.
6. **Step 4 — Your own AI (optional):** In AI assistant, at the bottom of Gateway Configuration, click Connect an AI [1]. Pick Gemini, Claude or ChatGPT [2], paste your own API key, which is encrypted before it's saved and never shown again [3], then click Connect and choose the model [4] (screenshot 4).
7. **Step 5 — Create a button:** Open Pay Button › Buttons [1] and click New button, or Build with AI to describe it in a sentence [2]. The tiles show all-time Collected, Conversion, Abandoned checkouts and Average payment, with the last 30 days' trend [3]. Search, filter by type or status, sort, and switch between cards and a list [4]. Each card shows the button, what it collected and its spots, and its switch turns it on or off [5]. Its tools edit it, copy the shortcode or the pay link, open the pay page, duplicate or delete it [6] (screenshot 5).
8. **Step 6 — Title, pay link and amount:** On the Checkout tab, set the Title and Description the payer sees at the top of the checkout [1], and optionally a Hosted payment page description that overrides the one in Gateway Configuration for this button [2]. The Pay link is the address of the button's own pay page [3]. Switch on Take payments [4]. Choose the Amount: Fixed, Payer chooses (donations, tips, with suggested amounts) or From the link (another plugin sends the total) [5] (screenshot 6).
9. **Step 7 — Payer details and checkout form:** Set Name, Email, Phone, Tax number (NIF) and a Note to Off, Optional or Required; with an email, the payer gets a receipt [1]. The Checkout form holds your own questions in the order the payer sees them: move them up or down, delete them, or Add more below [2]. A page break splits a long form into steps, each with its own title [3] (screenshot 7).
10. **Step 8 — Edit a question:** Click a question to edit its label, type, options, help text, whether it's required, and full or half width [1]. "Show this" asks it only when an earlier answer is (or isn't) one of the options you tick, e.g. a T-shirt size only for Supporter tickets [2] (screenshot 8).
11. **Step 9 — Spots (optional):** Choose No limit, Limited spots, or Spots per option, where each option of one choice field gets its own spots and price [1]. Split by picks the Dropdown, Single choice or Multiple choice field whose options have spots [2]; each option gets its own spots and price [3]. Total spots is an optional cap across every option [4] (screenshot 9). "Payer picks how many" lets the payer choose a quantity, and "Say when few are left" shows "Only X left". An unpaid payment keeps its spot until its ifthenpay link expires.
12. **Step 10 — Methods and Place it:** Under Payment methods, leave them all unticked to offer every method switched on in Gateway Configuration, or tick only the ones this button takes [1]. Copy the shortcode and paste it on any page or post [2], or share the pay link, the button's own pay page, for emails, social posts or a QR code [3]. In the block editor, add the "ifthenpay Pay Button" block and pick the button [4] (screenshot 10). The shortcode also takes `label` and `align` (left, center or right).
13. **Step 11 — Checkout design:** Open the Checkout design tab [1]. Keep the site's look (Default Checkout › Design) or give this button its own look [2]. Start from a look, then change the colours, the card's opacity and glass blur, dark mode, corners and fields [3]. The preview follows every change: checkout, pay page and each popup, in light and dark [4] (screenshot 11).
14. **Step 12 — Page progress and pay page:** Page progress shows the steps of a long form in seven ways: bars, named bars, one line, numbers, dots, ring or just words [1]. Pay page sets what sits behind the checkout card: soft glow, gradient, plain, an image or a video (file, YouTube or Vimeo) [2]. Gradients come soft, aurora or diagonal, and an effect adds crystals, waves, bokeh or grain [3] (screenshot 12).
15. **Step 13 — Button design:** Open the Button design tab [1] and write the Button text [2]. Choose Match my theme, or Custom: start from a preset, then pick the style (filled, outline, soft, gradient, glass, 3D, neon, brutal or ghost), colours, corners, border, size, hover and attention effects, and icon [3]. Click Save button [4] (screenshot 13).
16. **Step 14 — After payment and invoicing:** Open the After payment tab [1]. When paid, use the Default Checkout setting, or a popup, a page or a URL just for this button [2]. Optionally, Send to your invoicing app sends each paid payment straight to InvoiceXpress, Moloni, Vendus, TOConline or any app with an API [3]: set the app's address, method, format, headers (e.g. your API key) and a body built from tags [4] (screenshot 14). If the app is down, it's tried again after 5 minutes, 30 minutes and 3 hours.

Optional extras:

* **Connect another plugin:** In the button's Connect tab [1], tick the values the other plugin sends and swap each {tag} for that plugin's own merge tag, then copy the Redirect link into its "redirect after submit" setting [2]. Add a Webhook URL to get a signed JSON event when a payment is paid, fails or is cancelled (Zapier, Make, n8n…); Send a test checks it [3] (screenshot 15).
* **Build or edit with AI:** Describe the button, or what to change, in your own words and send; Build with AI on the Buttons screen starts a new one, and Edit with AI in the editor changes this one. Each message is billed to your own AI account [1]. Or start from an idea [2]. The preview is the real pay page, redrawn after every answer, and never takes payments [3]. See it on Desktop or Phone, or at Full page [4] (screenshot 16). A button the AI builds goes live as soon as it's created, so check its prices first.
* **Default checkout design:** Open Pay Button › Default Checkout [1]. Start from a look, then tweak the colours, shape, fonts, pay page background and Custom CSS [2]. The preview follows every change: checkout, pay page and each popup [3]. Click Save Settings [4] (screenshot 17).
* **Messages after paying:** In the Messages tab [1], choose what happens when paid: a popup message, a WordPress page or a custom URL [2]. Write the title and message for each outcome (Paid, Pending, Failed, Cancelled), with tags like {name} or {payment_id} [3] (screenshot 18).
* **Texts:** In the Texts tab [1], rewrite any text the payer reads: buttons, labels, spots, the popup and the error messages, in any language. Blank keeps the original; keep the words in {braces} [2] (screenshot 19).
* **Links & emails:** In the Links & emails tab [1], the Link base is the start of every pay link, e.g. /pay/your-button/ [2]. Switch on the Receipt to the payer [3] and Tell me about every payment [4], then click Save Settings [5] (screenshot 20).

Following your payments: Pay Button › Payments [1] shows Performance over 7 days, 30 days or 12 months and Revenue by method [2], totals by status [3], search, filters, columns and CSV export [4], and New payment to create a payment link for a customer or record one you took another way [5] (screenshot 21). View opens a payment: its amount and status [1], the transaction and the submitted data [2], and actions to change the status, copy the payment link or send the payment email [3] (screenshot 22).

How a payment works: the payer clicks a pay button on your page (screenshot 23), or opens its pay page, and fills in the checkout (screenshot 24). Continue to payment takes them to ifthenpay's secure page, where they choose a method and pay (screenshot 25). Back on your site, they see the Paid popup, or your page or URL, once ifthenpay confirms the payment (screenshot 26). Screenshots 27 to 30 show some of the designs Checkout design can make.

== Frequently Asked Questions ==

= Do I need WooCommerce, a form plugin or any other plugin? =

No. Pay Button works on its own. If you already use a form or booking plugin, the Connect tab links it to a button.

= Does it support recurring payments? =

No. This version supports only one-time payments via pay-by-link.

= Are payment details stored? =

No. The plugin does not store card numbers or full bank details. It keeps the payment's reference, amount, status, method and the details the payer filled in at your checkout. Payers' data is included in WordPress's Tools › Export Personal Data and Erase Personal Data; erasing keeps the payment (amount, status, dates) as accounting history and removes who paid it.

= Which payment methods are supported? =

Any ifthenpay method attached to your Gateway Key (e.g. Multibanco, MB WAY, Payshop, Credit Card, Google Pay, Apple Pay, Pix, and more). A button can offer all of them or only some.

= How does the payment process work? =

The payer fills in the button's checkout and is sent to ifthenpay's secure hosted payment page. Once they pay, ifthenpay notifies your site with a server-side callback, the payment is marked Paid, and the payer sees your Paid message, page or URL. Multibanco and Payshop references show as Pending until they're paid.

= What happens if a payment fails or is cancelled? =

The payment is marked Failed or Cancelled, and the payer sees your Failed or Cancelled popup on the page they paid from. They can try again. If ifthenpay later confirms the payment, it still turns Paid and the popup updates. An unpaid payment is marked Expired after your Expiry days.

= Can the payer choose the amount? =

Yes. Set the amount to Payer chooses, with suggested amounts and an optional minimum and maximum. It's made for donations, tips and top-ups.

= Can I sell a limited number of places? =

Yes. Spots limits how many people can pay, in total or per option of a Dropdown, Single choice or Multiple choice field, each with its own price. A spot is held while its payment link is open, so you're never oversold.

= Can I use it with a form or booking plugin ifthenpay doesn't support? =

Yes. In the button's Connect tab, build a redirect link with that plugin's merge tags for the amount, name, email and reference, and set it as the plugin's "redirect after submit". The reference comes back in Payments, the receipt and the webhook, so you can match the payment to its entry.

= Can my developer create payment links in code? =

Yes. `iftp_btn_payment_url( $button_id, array( 'amount' => 49.90, 'reference' => 'ORDER-1042', 'email' => '…', 'expires' => time() + DAY_IN_SECONDS ) )` returns a signed link whose amount and reference can't be edited in the address bar.

= Can I change every word the payer sees? =

Yes. Default Checkout › Texts rewrites every label, button and message of the checkout, in any language. The popups after payment are in Default Checkout › Messages.

= Is there a sandbox? =

ifthenpay may provide test entities; if unavailable, use a low-value live test.

= How secure is the integration? =

Requests are encrypted over HTTPS, and no sensitive payment data is stored. ifthenpay's callback must carry your anti-phishing key, and the links that bring the payer back are signed, so nobody can mark a payment paid by guessing an address.

= Does it accept webhooks (callbacks) from ifthenpay? =

Yes. Once you choose a Gateway Key, the plugin registers its callback URL with ifthenpay (and registers it again if it ever drifts), and every callback is checked against your anti-phishing key.

= What happens to my data if I delete the plugin? =

Deleting the plugin (not just deactivating it) removes its settings, its buttons and its payments table. Export your payments to CSV first if you need them.

== External Services ==

This plugin integrates with the ifthenpay payment platform to process payments made through its pay buttons. ifthenpay is a third-party service that provides secure payment processing for local payment methods such as Multibanco, MB WAY, Payshop, and Pix, plus Credit Card and more, depending on what's enabled on your account.

**ifthenpay Backoffice & Integrations**

* What it is and what it is used for: The ifthenpay Backoffice is the merchant dashboard used to manage integrations and payment configurations. The plugin uses the ifthenpay API (api.ifthenpay.com) to read your Gateway Keys and payment methods, generate payment links and check transactions.
* What data is sent and when:
    * During setup: the Backoffice Key, to read your Gateway Keys and their payment methods. The list of payment methods is also fetched from ifthenpay.
    * During payment processing: the payment reference, the amount, the description, the enabled payment method accounts, the success/error/cancel return URLs, the language and the link's expiry date. The description is the one set in Gateway Configuration (or on the button); if you add payer tags such as {name} or {email} to it, those values are sent as part of the description. No other customer or checkout data is included.
    * During webhook registration: the anti-phishing key, the Gateway Key and this site's callback URL, so ifthenpay can notify the site directly when a payment is paid.
    * During callbacks (received from ifthenpay): the payment reference, the anti-phishing key, the amount, the payment method and the request ID.
    * While a payer waits on the result popup: the transaction ID, to ask ifthenpay for the payment's status.
    * During payment method activation requests: when an admin clicks Request Activation in Pay Button › Gateway Configuration, an email is sent to ifthenpay support (suporte@ifthenpay.com) containing the Backoffice Key, Gateway Key, the requested payment method, the admin's email address, site URL, site name, WordPress version and plugin version.
    * Payment method logos on your checkout and admin screens, and the ifthenpay logo in the plugin's emails, are images loaded from ifthenpay's servers when the page or email is viewed.
* End-User License Agreement (EULA): [https://ifthenpay.com/eula/](https://ifthenpay.com/eula/)
* Privacy Policy: [https://ifthenpay.com/politica-de-privacidade/](https://ifthenpay.com/politica-de-privacidade/)

**Your own webhook (optional)**

* What it is and what it is used for: If you enter a webhook URL on a button (e.g. Zapier, Make, n8n or your own system), the plugin tells it about that button's payments.
* What data is sent and when: when one of the button's payments is paid, fails or is cancelled (and when you click Send a test): the event, reference, status, amount, currency, quantity, method, the external reference, the button's ID and title, the payer's name, email, phone, tax number and note, the answers to your own checkout questions, and the created and updated times. Each request is signed with the button's secret in the X-Iftp-Signature header. The data goes only to the URL you enter, under that service's own terms.

**Your invoicing app (optional)**

* What it is and what it is used for: If you switch on "Send to your invoicing app" in a button's After payment tab (e.g. InvoiceXpress, Moloni, Vendus, TOConline or your own API), the plugin sends each of that button's paid payments to the address you enter, so the app can issue the invoice.
* What data is sent and when: when one of the button's payments is paid (with retries if the app doesn't answer, and when you click Send a test): only what you put in the request you write there, from the payment's merge tags: e.g. the payer's name, email, phone, tax number and note, the amount, quantity, method, payment ID, the button's title, the date and your own checkout answers, plus any headers you add (such as the app's API key). The data goes only to the address you enter, under that service's own terms.

**AI assistant: Google Gemini, Anthropic Claude or OpenAI (optional)**

* What it is and what it is used for: If you connect your own API key in Pay Button › Gateway Configuration › AI assistant, you can describe a button in a chat (Buttons › Build with AI, or Edit with AI in a button's editor) and the AI you picked builds or changes it. Nothing is sent until an administrator connects a key.
* What data is sent and when:
    * When you connect: the API key, to check it and list the models it can use. The key is sent only in a request header to its own provider, and is stored on your site encrypted with your wp-config.php secret keys.
    * Each time you send a chat message: your message, the earlier messages of that chat, the button as it is (its title, texts, form questions and options, prices, spots and design), your site's name and language, and the colours of your site's checkout look. No payments, payer details or other site data are ever sent.
    * Each message is billed to your own account with that provider, under its own terms.
* Google Gemini: [Gemini API Terms](https://ai.google.dev/gemini-api/terms), [Google Privacy Policy](https://policies.google.com/privacy)
* Anthropic Claude: [Commercial Terms](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy)
* OpenAI: [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/)

**YouTube and Vimeo (optional)**

* What it is and what it is used for: If you pick a YouTube or Vimeo link as the pay page's background video (Default Checkout › Design, or a button's Checkout design tab), the pay page and the checkout opened on it show that video in an embedded player: YouTube's privacy-enhanced youtube-nocookie.com player, or Vimeo's with Do Not Track on.
* What data is sent and when: when a payer opens that pay page, or a checkout or popup over that background, their browser loads the player from YouTube or Vimeo, which receives the payer's IP address, browser details and the page it's shown on, as with any embedded video. Nothing about the payment is sent. A video you upload to your own Media Library is served from your site and sends nothing anywhere.
* YouTube: [Terms of Service](https://www.youtube.com/t/terms), [Google Privacy Policy](https://policies.google.com/privacy)
* Vimeo: [Terms of Service](https://vimeo.com/terms), [Privacy Policy](https://vimeo.com/privacy)

The Payments screens show the payer's avatar through WordPress's own avatar function (Gravatar by default, per your site's Discussion settings).

All network requests are performed server-side over HTTPS, except the logo images above and an embedded YouTube or Vimeo background video. Sensitive credentials are stored securely and are not publicly exposed. No raw card or bank details are stored.

== Screenshots ==

1. (Admin Only) Step 1: connect your ifthenpay account with the Backoffice Key
2. (Admin Only) Step 2: choose the Gateway Key and the payment methods
3. (Admin Only) Step 3: set the ifthenpay page description, link expiry and weekly summary
4. (Admin Only) Step 4: optional — connect your own AI to build buttons from a chat
5. (Admin Only) Step 5: the Buttons screen — create a button, or build one with AI
6. (Admin Only) Step 6: the button's checkout — title, pay link and amount
7. (Admin Only) Step 7: ask for the payer's details and build the checkout form
8. (Admin Only) Step 8: add questions, and show them only for some answers
9. (Admin Only) Step 9: optional — limit the spots, in total or per option
10. (Admin Only) Step 10: pick the methods for this button, then place it on a page or share its pay link
11. (Admin Only) Step 11: give the button's checkout its own look
12. (Admin Only) Step 12: page progress style and the pay page background
13. (Admin Only) Step 13: design the button and save it
14. (Admin Only) Step 14: what happens after payment, and sending paid payments to your invoicing app
15. (Admin Only) Optional: connect a form or booking plugin ifthenpay has no integration with
16. (Admin Only) Optional: build or edit a button with AI
17. (Admin Only) Default Checkout › Design: the look of every checkout, pay page and popup
18. (Admin Only) Default Checkout › Messages: what the payer sees after paying
19. (Admin Only) Default Checkout › Texts: every word the payer reads, in your own words or language
20. (Admin Only) Default Checkout › Links & emails: pay link address and payment emails
21. (Admin Only) Payments: every payment, its status and the totals
22. (Admin Only) A payment's details
23. (Customers Experience) Pay buttons on a page
24. (Customers Experience) The checkout, here on the button's own pay page
25. (Customers Experience) ifthenpay's secure payment page
26. (Customers Experience) The Paid popup back on your site
27. (Design Example) Aurora gradient with waves, ring progress and priced options
28. (Design Example) A YouTube video behind the checkout
29. (Design Example) Dark mode, following the payer's device
30. (Design Example) Crystals on an aurora, a wide card with the logo above it

== Changelog ==

Releases that fix a security issue say so in their entry, prefixed with **Security:**.

= 1.0.0 =
* Initial public release: ifthenpay pay buttons (shortcode, block and a pay page per button) with fixed, payer-chosen or link-set amounts, a checkout form builder with multi-step pages and conditional questions, limited spots in total or per option, button and checkout design with a live preview, editable payer texts and messages, a redirect link builder, signed links and a signed outgoing webhook for other plugins, a direct request to your invoicing app when paid, revenue, abandonment and spots stats on the Buttons screen, webhook-confirmed payment status, receipts, the Payments screen with CSV export, a Dashboard widget, a weekly summary email, and automatic expiry of unpaid payments.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.

== Support ==

For assistance use the WordPress.org support forum: https://wordpress.org/support/plugin/ifthenpay-pay-button/

Pre-checks:

* Payment method enabled on Gateway Key AND mapped to Integration
* Running current recommended versions of WordPress & PHP

Commercial helpdesk available (no direct email required): https://helpdesk.ifthenpay.com/

* ifthenpay support: suporte@ifthenpay.com

== Security ==

Found a security issue? Please report it privately to suporte@ifthenpay.com with "Security" in the subject, rather than in the public support forum. Include the plugin version, the steps to reproduce, and what an attacker could do. We'll confirm we got your report and keep you posted until a fix ships. See SECURITY.md in the plugin folder for the full policy.
