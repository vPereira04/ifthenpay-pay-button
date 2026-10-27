<a name="top"></a>

# ifthenpay | Pay Button

Take ifthenpay payments anywhere: pay buttons for any page, shareable pay links, and a link builder for plugins ifthenpay has no integration with.

---

## Table of Contents

- [Description](#description)
- [Key Features](#key-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Frequently Asked Questions](#frequently-asked-questions)
- [External Services](#external-services)
- [Screenshots](#screenshots)
- [Examples](#examples)
- [Support](#support)
- [Security](#security)
- [Changelog](#changelog)

### Description

ifthenpay | Pay Button lets you take ifthenpay payments on any WordPress site, with no shop, form or booking plugin needed. You create a pay button, set its amount and the details you need, and place it on any page with a shortcode or a block. Every button also has its own pay page, so you can share it as a link.

The payer fills in a short checkout, then pays on ifthenpay's secure hosted payment page with any method enabled on your account. ifthenpay confirms the payment to your site with a server-side callback, and the payer comes back to a "Paid" message, a page or a URL of your choice. No card or bank details ever touch your site.

Already using a form or booking plugin ifthenpay has no integration with? Point its "redirect after submit" at a button's pay link and fill in the amount, name and email with that plugin's own merge tags. A signed webhook tells the other plugin (or Zapier, Make or n8n) when the payment is paid.

**In plain terms you get:**

- Pay buttons for any page or post, as a shortcode or a block, plus a pay page per button to share as a link
- Fixed amounts, amounts the payer chooses (donations, tips) or amounts sent by another plugin
- Your own checkout questions, multi-step checkouts, and limited spots per button or per option (dates, sessions, sizes)
- A Payments screen with totals, filters and CSV export, receipts by email, and secure automatic payment confirmations (no card numbers stored)

All settings are made in **Pay Button** in your WordPress admin and in your ifthenpay Backoffice. The plugin is built so site owners can take payments without needing deep technical knowledge.

### Key Features

1. Pay buttons that work on any page: shortcode `[ifthenpay_button id="…"]`, an **ifthenpay Pay Button** block, and a pay page per button (`/pay/{button}/`)
2. Three amount modes: **Fixed**, **Payer chooses** (with suggested amounts and a minimum and maximum) and **From the link**
3. A checkout form builder: name, email, phone, tax number (NIF) and note, plus your own questions (text, number, date, dropdown, single or multiple choice, agreement, text blocks) and page breaks for multi-step checkouts, with seven page-progress styles (bars, named bars, one line, numbers, dots, ring or just words); any question or text block can show only for some answers (e.g. a dietary question just for the in-person day)
4. **Spots**: limit how many people can pay, in total or per option, each option with its own price; the payer can pick a quantity, and sold-out options are greyed out
5. Full design control: match your theme's buttons or design your own (filled, outline, soft, gradient, glass, 3D, neon, brutal or ghost, with your own border, a hover effect such as lift, grow, glow or fill, and an attention effect such as pulse, heartbeat, wiggle or a spark orbiting the edge), plus a site-wide (or per-button) look for the checkout, the pay page and the popups, with a live preview: a glow, gradient, image or looping video background with Crystals, Waves, Bokeh or Grain, a see-through frosted-glass card, and a dark mode that follows the payer's device; the checkout can open over the pay page's background instead of a dimmed page
6. Every payer-facing word can be rewritten in the **Texts** tab, in any language
7. After payment: a popup message, a WordPress page or a custom URL, with merge tags such as `{name}`, `{amount}` and `{payment_id}`
8. A bridge for any other plugin: a redirect link builder, signed links for developers, and a signed outgoing webhook (Zapier, Make, n8n); plus a direct request to your invoicing app (InvoiceXpress, Moloni, Vendus, TOConline…) the moment a payment is paid, written in that app's own format with merge tags
9. Support for multiple payment methods (Multibanco, MB WAY, Payshop, Credit Card, Google Pay, Apple Pay, Pix and more — whatever is enabled on your Gateway Key), synced live from your ifthenpay account, with in-admin **Request Activation** for methods not yet enabled
10. Secure redirect to ifthenpay's hosted payment page, in Portuguese, English, Spanish or French to match your site's language
11. Revenue, the share of payers who opened the checkout but didn't pay, and how full the spots are, right on the Buttons screen; a Payments screen with charts, totals by status, search, filters, columns, CSV export, manual status changes and payment emails, plus a Dashboard widget and an optional weekly summary email
12. Receipts for the payer and a notice for you, sent once ifthenpay confirms a payment
13. Security first: no card data stored, anti-phishing key on every callback, signed return and result links

### Requirements

- An active ifthenpay merchant account — [subscribe here](https://ifthenpay.com/aderir/) to obtain your credentials.
- Your ifthenpay **Backoffice Key**, and a **Gateway Key** for ifthenpay Pay Button (request it from ifthenpay support/helpdesk).
- The payment methods you want enabled on that Gateway Key (our helpdesk team will guide you).
- WordPress 6.4+ and PHP 7.4+.
- HTTPS (SSL) enabled on your site.

No other plugin is needed.

### Installation

1. **Install:** Upload the plugin zip via `Plugins → Add New → Upload`, or install from WordPress.org and Activate.
2. **Credentials:** Ensure your ifthenpay account has an active Pay Button Gateway Key with the desired payment methods enabled.

#### Step 1 — Connect your ifthenpay account

1. Open **Pay Button › Gateway Configuration** and click **Connect** under **ifthenpay account**.
2. Paste your **Backoffice Key**.
3. Click **Connect**.

(screenshot 1)

#### Step 2 — Choose the Gateway Key and the payment methods

1. Choose your **Gateway Key** (only needed if you have more than one).
2. Switch on the **payment methods** to offer and star the **default** one the ifthenpay page opens on.
3. For a method that isn't active on your contract yet, click **Request Activation**.
4. Click **Save Settings**.

(screenshot 2)

#### Step 3 — Set the ifthenpay page description, link expiry and weekly summary

1. **Description**: what the payer reads on the ifthenpay page. Click a **tag** to fill in each payment's details, e.g. `{button}`.
2. **Expiry days**: how long an unpaid payment link stays open before it's marked Expired.
3. Optional: a **weekly summary** email every Monday.
4. Click **Save Settings**.

(screenshot 3)

These settings apply to every button. Further down, **Display** sets how the plugin's admin screens and Dashboard widget look.

#### Step 4 — Optional: connect your own AI

1. Optional: in **AI assistant**, at the bottom of Gateway Configuration, click **Connect an AI**.
2. Pick **Gemini**, **Claude** or **ChatGPT**.
3. Paste your own **API key**. It's encrypted before it's saved and never shown again.
4. Click **Connect**, then choose the **model**.

(screenshot 4)

Gemini, Claude and ChatGPT are supported. The key is only used to build buttons, and only by administrators.

#### Step 5 — Create a pay button

1. Open **Pay Button › Buttons**.
2. Click **New button**, or **Build with AI** to describe it in a sentence.
3. All-time **Collected**, **Conversion**, **Abandoned checkouts** and **Average payment**, with the last 30 days' trend.
4. Search, filter by type or status, sort, and switch between **cards** and a **list**.
5. Each card shows the button, what it collected and its spots. The switch turns it **on or off**.
6. **Edit**, copy the **shortcode** or the **pay link**, open the **pay page**, **duplicate** or **delete**.

(screenshot 5)

#### Step 6 — Set the button's checkout: title, pay link and amount

1. On the **Checkout** tab, the **Title** and **Description** are what the payer sees at the top of the checkout.
2. **Hosted payment page description** (optional): overrides the one in Gateway Configuration for this button.
3. **Pay link**: the address of the button's own pay page.
4. Switch on **Take payments**.
5. **Amount**: **Fixed**, **Payer chooses** (donations, tips, with suggested amounts) or **From the link** (another plugin sends the total).

(screenshot 6)

#### Step 7 — Ask for the payer's details and build the checkout form

1. **Payer details**: set **Name**, **Email**, **Phone**, **Tax number (NIF)** and a **Note** to Off, Optional or Required. With an email, the payer gets a receipt.
2. **Checkout form**: your own questions, in the order the payer sees them. Move them up or down, delete them, or **Add** more below.
3. A **page break** splits a long form into steps, each with its own title.

(screenshot 7)

#### Step 8 — Edit a question, and show it only for some answers

1. Click a question to edit it: label, type, options, help text, required, and full or half width.
2. **Show this**: only when an earlier answer is (or isn't) one of the options you tick. Here the T-shirt size is only asked of Supporter tickets.

(screenshot 8)

#### Step 9 — Optional: limit the spots, in total or per option

1. **Spots**: **No limit**, **Limited spots**, or **Spots per option** (each option of one choice field gets its own spots and price).
2. **Split by**: the Dropdown, Single choice or Multiple choice field whose options have spots.
3. Each option gets its own **spots** and **price**.
4. **Total spots** (optional): a cap across every option.

(screenshot 9)

Below these, **Payer picks how many** lets the payer choose a quantity (capped by **Most in one payment**), and **Say when few are left** shows "Only X left" in the checkout. An unpaid payment keeps its spot until its ifthenpay link expires.

#### Step 10 — Pick the button's methods, then place it on a page or share its pay link

1. **Payment methods**: leave them all unticked to offer every method switched on in Gateway Configuration, or tick only the ones this button takes.
2. Copy the **shortcode** and paste it on any page or post.
3. Or share the **pay link**: the button's own pay page, for emails, social posts or a QR code.
4. In the block editor, add the **ifthenpay Pay Button** block and pick this button.

(screenshot 10)

The shortcode also takes `label` (the button text) and `align` (`left`, `center` or `right`), e.g. `[ifthenpay_button id="123" label="Donate" align="center"]`.

#### Step 11 — Give the button's checkout its own look

1. Open the **Checkout design** tab.
2. Keep **The site's look** (Default Design › Checkout) or give this button **Its own look**.
3. **Start from a look**, then change the colours, the card's opacity and glass blur, dark mode, corners and fields.
4. The **preview** follows every change: checkout, pay page and each popup, in light and dark.

(screenshot 11)

#### Step 12 — Choose the page progress and the pay page background

1. **Page progress**: seven ways to show the steps of a long form: bars, named bars, one line, numbers, dots, ring or just words.
2. **Pay page**: what sits behind the checkout card. **Soft glow**, **Gradient**, **Plain**, an **Image** or a **Video** (file, YouTube or Vimeo).
3. Gradients come **soft**, **aurora** or **diagonal**. Below, add an **effect**: crystals, waves, bokeh or grain.

(screenshot 12)

Further down, **Popups and footer** styles the popups after paying and the payment logos under the checkout.

#### Step 13 — Design the button and save it

1. Open the **Button design** tab.
2. Write the **Button text**.
3. **Default design** (the one set in **Default Design › Button**, shared by every button that uses it), **Match my theme**, or **Custom**: start from a preset, then pick the style (filled, outline, soft, gradient, glass, 3D, neon, brutal or ghost), colours, corners, border, size, a hover effect, an attention effect and an icon. Like a custom one? **Save and make it the default**.
4. Click **Save button**.

(screenshot 13)

#### Step 14 — Choose what happens after payment, and send paid payments to your invoicing app

1. Open the **After payment** tab.
2. **When paid**: use the Default Design setting, or a popup, a page or a URL just for this button.
3. **Send to your invoicing app** (optional): each paid payment goes straight to InvoiceXpress, Moloni, Vendus, TOConline or any app with an API.
4. The app's **address**, method, format, headers (e.g. your API key) and a **body** built from tags.

(screenshot 14)

The invoicing request only goes out for paid payments. If the app is down, it's tried again after 5 minutes, 30 minutes and 3 hours. **Send a test** sends the form as it is, before you save.

#### Optional — Connect a form or booking plugin ifthenpay has no integration with

1. Open the **Connect** tab.
2. Tick the values the other plugin sends and swap each `{tag}` for that plugin's own merge tag. Copy the **Redirect link** into its "redirect after submit" setting.
3. **Webhook** (optional): we POST a signed JSON event when a payment is paid, fails or is cancelled (Zapier, Make, n8n…). **Send a test** checks it.

(screenshot 15)

With **Go straight to ifthenpay** on, a link that already fills in everything required skips the checkout. **Verify the webhook signature** shows how to check the `X-Iftp-Signature` header, and **For developers: signed links** shows `iftp_btn_payment_url()`, which builds a link whose amount and reference can't be edited.

#### Optional — Build or edit a button with AI

1. Describe the button, or what to change, in your own words, and send. **Build with AI** on the Buttons screen starts a new one; **Edit with AI** in the editor changes this one. Each message is billed to your own AI account.
2. Or start from an **idea**.
3. The **preview** is the real pay page, redrawn after every answer. It never takes payments.
4. See it on **Desktop** or **Phone**, or open it at **Full page**.

(screenshot 16)

A button the AI builds goes live as soon as it's created, so check its prices before you share it.

#### Optional — Design every checkout, pay page, popup and button

1. Open **Pay Button › Default Design**.
2. **Start from a look** (each one sets the pay page background too), then tweak the colours, shape, fonts, pay page background and Custom CSS below.
3. The **preview** follows every change: checkout, pay page and each popup.
4. Click **Save Settings**.

(screenshot 17)

The **Button** tab sets the default button design: every button set to **Default design** wears it, so many buttons across a site look the same and change together.

A button set to **The site's look** in its **Checkout design** tab uses this one.

#### Optional — Choose what the payer sees after paying

1. Open the **Messages** tab.
2. **When paid**: show a **popup message**, send the payer to a **WordPress page** or to a **custom URL**.
3. Write the **title** and **message** for each outcome (Paid, Pending, Failed, Cancelled), with tags like `{name}` or `{payment_id}`.

(screenshot 18)

#### Optional — Rewrite the payer's texts, in any language

1. Open the **Texts** tab.
2. Rewrite any text: buttons, labels, spots, the popup and the error messages. Blank keeps the original. Keep the words in `{braces}`.

(screenshot 19)

#### Optional — Pay link address and payment emails

1. Open the **Links & emails** tab.
2. **Link base**: the start of every pay link, e.g. `/pay/your-button/`.
3. **Receipt to the payer**, once ifthenpay confirms the payment.
4. **Tell me about every payment**, sent to the address below.
5. Click **Save Settings**.

(screenshot 20)

#### Following your payments

1. Open **Pay Button › Payments**.
2. **Performance** over 7 days, 30 days or 12 months, and **Revenue by method**.
3. Totals by status: **Paid**, **Pending**, **Failed** and **Expired**.
4. Search, **Filters**, **Columns** and **Export** to CSV.
5. **New payment**: create a payment link for a customer, or record one you took another way.

(screenshot 21)

Click **View** on a payment to open its details:

1. The amount and **status** (Paid once ifthenpay confirms it), with the method and the customer.
2. **Transaction**: reference, ifthenpay request and transaction IDs, the button, and the page it was paid from. **Submitted data** below lists everything the payer filled in.
3. **Actions**: change the status by hand. A pending payment also gets **Copy payment link** and **Send payment email**.

(screenshot 22)

#### How a payment works

The payer clicks a pay button on your page (screenshot 23), or opens its pay page, and fills in the checkout (screenshot 24). **Continue to payment** takes them to ifthenpay's secure page, where they choose a method and pay (screenshot 25). Back on your site, they see the Paid popup, or your page or URL, once ifthenpay confirms the payment (screenshot 26).

Screenshots 27 to 30 show some of the designs you can make with **Checkout design**: a gradient with waves, a video background, dark mode and crystals.

### Frequently Asked Questions

<details>
<summary><strong>Do I need WooCommerce, a form plugin or any other plugin?</strong></summary>
No. Pay Button works on its own. If you already use a form or booking plugin, the Connect tab links it to a button.
</details>

<details>
<summary><strong>Does it support recurring payments?</strong></summary>
No. This version supports only one-time payments via pay-by-link.
</details>

<details>
<summary><strong>Are payment details stored?</strong></summary>
No. The plugin does not store card numbers or full bank details. It keeps the payment's reference, amount, status, method and the details the payer filled in at your checkout. Payers' data is included in WordPress's **Tools › Export Personal Data** and **Erase Personal Data**; erasing keeps the payment (amount, status, dates) as accounting history and removes who paid it.
</details>

<details>
<summary><strong>Which payment methods are supported?</strong></summary>
Any ifthenpay method attached to your Gateway Key (e.g. Multibanco, MB WAY, Payshop, Credit Card, Google Pay, Apple Pay, Pix, and more). A button can offer all of them or only some.
</details>

<details>
<summary><strong>How does the payment process work?</strong></summary>
The payer fills in the button's checkout and is sent to ifthenpay's secure hosted payment page. Once they pay, ifthenpay notifies your site with a server-side callback, the payment is marked Paid, and the payer sees your Paid message, page or URL. Multibanco and Payshop references show as Pending until they're paid.
</details>

<details>
<summary><strong>What happens if a payment fails or is cancelled?</strong></summary>
The payment is marked Failed or Cancelled, and the payer sees your Failed or Cancelled popup on the page they paid from. They can try again. If ifthenpay later confirms the payment, it still turns Paid and the popup updates. An unpaid payment is marked Expired after your Expiry days.
</details>

<details>
<summary><strong>Can the payer choose the amount?</strong></summary>
Yes. Set the amount to **Payer chooses**, with suggested amounts and an optional minimum and maximum. It's made for donations, tips and top-ups.
</details>

<details>
<summary><strong>Can I sell a limited number of places?</strong></summary>
Yes. **Spots** limits how many people can pay, in total or per option of a Dropdown, Single choice or Multiple choice field, each with its own price. A spot is held while its payment link is open, so you're never oversold.
</details>

<details>
<summary><strong>Can I use it with a form or booking plugin ifthenpay doesn't support?</strong></summary>
Yes. In the button's **Connect** tab, build a redirect link with that plugin's merge tags for the amount, name, email and reference, and set it as the plugin's "redirect after submit". The reference comes back in Payments, the receipt and the webhook, so you can match the payment to its entry.
</details>

<details>
<summary><strong>Can my developer create payment links in code?</strong></summary>
Yes. `iftp_btn_payment_url( $button_id, array( 'amount' => 49.90, 'reference' => 'ORDER-1042', 'email' => '…', 'expires' => time() + DAY_IN_SECONDS ) )` returns a signed link whose amount and reference can't be edited in the address bar.
</details>

<details>
<summary><strong>Can I change every word the payer sees?</strong></summary>
Yes. **Default Design › Texts** rewrites every label, button and message of the checkout, in any language. The popups after payment are in **Default Design › Messages**.
</details>

<details>
<summary><strong>Is there a sandbox?</strong></summary>
ifthenpay may provide test entities; if unavailable, use a low-value live test.
</details>

<details>
<summary><strong>How secure is the integration?</strong></summary>
Requests are encrypted over HTTPS, and no sensitive payment data is stored. ifthenpay's callback must carry your anti-phishing key, and the links that bring the payer back are signed, so nobody can mark a payment paid by guessing an address.
</details>

<details>
<summary><strong>Does it accept webhooks (callbacks) from ifthenpay?</strong></summary>
Yes. Once you choose a Gateway Key, the plugin registers its callback URL with ifthenpay (and registers it again if it ever drifts), and every callback is checked against your anti-phishing key.
</details>

<details>
<summary><strong>What happens to my data if I delete the plugin?</strong></summary>
Deleting the plugin (not just deactivating it) removes its settings, its buttons and its payments table. Export your payments to CSV first if you need them.
</details>

### External Services

This plugin integrates with the ifthenpay payment platform to process payments made through its pay buttons. ifthenpay is a third-party service that provides secure payment processing for local payment methods such as Multibanco, MB WAY, Payshop, and Pix, plus Credit Card and more, depending on what's enabled on your account.

- **ifthenpay Backoffice & Integrations**
  - **What it is and what it is used for**: The ifthenpay Backoffice is the merchant dashboard used to manage integrations and payment configurations. The plugin uses the ifthenpay API (`api.ifthenpay.com`) to read your Gateway Keys and payment methods, generate payment links and check transactions.
  - **What data is sent and when**:
    - During setup: the Backoffice Key, to read your Gateway Keys and their payment methods. The list of payment methods is also fetched from ifthenpay.
    - During payment processing: the payment reference, the amount, the description, the enabled payment method accounts, the success/error/cancel return URLs, the language and the link's expiry date. The description is the one set in Gateway Configuration (or on the button); if you add payer tags such as `{name}` or `{email}` to it, those values are sent as part of the description. No other customer or checkout data is included.
    - During webhook registration: the anti-phishing key, the Gateway Key and this site's callback URL, so ifthenpay can notify the site directly when a payment is paid.
    - During callbacks (received from ifthenpay): the payment reference, the anti-phishing key, the amount, the payment method and the request ID.
    - While a payer waits on the result popup: the transaction ID, to ask ifthenpay for the payment's status.
    - During payment method activation requests: when an admin clicks **Request Activation** in Pay Button › Gateway Configuration, an email is sent to ifthenpay support (suporte@ifthenpay.com) containing the Backoffice Key, Gateway Key, the requested payment method, the admin's email address, site URL, site name, WordPress version and plugin version.
    - Payment method logos on your checkout and admin screens, and the ifthenpay logo in the plugin's emails, are images loaded from ifthenpay's servers when the page or email is viewed.
  - **End-User License Agreement (EULA)**: [EULA](https://ifthenpay.com/eula/)
  - **Privacy Policy**: [Privacy Policy](https://ifthenpay.com/politica-de-privacidade/)

- **Your own webhook (optional)**
  - **What it is and what it is used for**: If you enter a webhook URL on a button (e.g. Zapier, Make, n8n or your own system), the plugin tells it about that button's payments.
  - **What data is sent and when**: when one of the button's payments is paid, fails or is cancelled (and when you click **Send a test**): the event, reference, status, amount, currency, quantity, method, the external reference, the button's ID and title, the payer's name, email, phone, tax number and note, the answers to your own checkout questions, and the created and updated times. Each request is signed with the button's secret in the `X-Iftp-Signature` header. The data goes only to the URL you enter, under that service's own terms.

- **Your invoicing app (optional)**
  - **What it is and what it is used for**: If you switch on **Send to your invoicing app** in a button's After payment tab (e.g. InvoiceXpress, Moloni, Vendus, TOConline or your own API), the plugin sends each of that button's paid payments to the address you enter, so the app can issue the invoice.
  - **What data is sent and when**: when one of the button's payments is paid (with retries if the app doesn't answer, and when you click **Send a test**): only what you put in the request you write there, from the payment's merge tags: e.g. the payer's name, email, phone, tax number and note, the amount, quantity, method, payment ID, the button's title, the date and your own checkout answers, plus any headers you add (such as the app's API key). The data goes only to the address you enter, under that service's own terms.

- **AI assistant: Google Gemini, Anthropic Claude or OpenAI (optional)**
  - **What it is and what it is used for**: If you connect your own API key in **Pay Button › Gateway Configuration › AI assistant**, you can describe a button in a chat (Buttons › Build with AI, or Edit with AI in a button's editor) and the AI you picked builds or changes it. Nothing is sent until an administrator connects a key.
  - **What data is sent and when**:
    - When you connect: the API key, to check it and list the models it can use. The key is sent only in a request header to its own provider, and is stored on your site encrypted with your wp-config.php secret keys.
    - Each time you send a chat message: your message, the earlier messages of that chat, the button as it is (its title, texts, form questions and options, prices, spots and design), your site's name and language, and the colours of your site's checkout look. No payments, payer details or other site data are ever sent.
    - Each message is billed to your own account with that provider, under its own terms.
  - **Google Gemini**: [Gemini API Terms](https://ai.google.dev/gemini-api/terms), [Google Privacy Policy](https://policies.google.com/privacy)
  - **Anthropic Claude**: [Commercial Terms](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy)
  - **OpenAI**: [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/)
- **YouTube and Vimeo (optional)**
  - **What it is and what it is used for**: If you pick a YouTube or Vimeo link as the pay page's background video (Default Design › Checkout, or a button's Checkout design tab), the pay page and the checkout opened on it show that video in an embedded player: YouTube's privacy-enhanced `youtube-nocookie.com` player, or Vimeo's with Do Not Track on.
  - **What data is sent and when**: when a payer opens that pay page, or a checkout or popup over that background, their browser loads the player from YouTube or Vimeo, which receives the payer's IP address, browser details and the page it's shown on, as with any embedded video. Nothing about the payment is sent. A video you upload to your own Media Library is served from your site and sends nothing anywhere.
  - **YouTube**: [Terms of Service](https://www.youtube.com/t/terms), [Google Privacy Policy](https://policies.google.com/privacy)
  - **Vimeo**: [Terms of Service](https://vimeo.com/terms), [Privacy Policy](https://vimeo.com/privacy)

The Payments screens show the payer's avatar through WordPress's own avatar function (Gravatar by default, per your site's Discussion settings).

All network requests are performed server-side over HTTPS, except the logo images above. Sensitive credentials are stored securely and are not publicly exposed. No raw card or bank details are stored.

### Screenshots

Below are screenshots demonstrating key features and interfaces of the plugin:

1. **(Admin Only) Step 1: connect your ifthenpay account with the Backoffice Key**
   ![Connect](.wordpress-org/screenshot-1.png)
2. **(Admin Only) Step 2: choose the Gateway Key and the payment methods**
   ![Gateway Key and payment methods](.wordpress-org/screenshot-2.png)
3. **(Admin Only) Step 3: set the ifthenpay page description, link expiry and weekly summary**
   ![Hosted payment page settings](.wordpress-org/screenshot-3.png)
4. **(Admin Only) Step 4: optional — connect your own AI to build buttons from a chat**
   ![Connect an AI](.wordpress-org/screenshot-4.png)
5. **(Admin Only) Step 5: the Buttons screen — create a button, or build one with AI**
   ![Buttons](.wordpress-org/screenshot-5.png)
6. **(Admin Only) Step 6: the button's checkout — title, pay link and amount**
   ![Checkout basics and amount](.wordpress-org/screenshot-6.png)
7. **(Admin Only) Step 7: ask for the payer's details and build the checkout form**
   ![Payer details and checkout form](.wordpress-org/screenshot-7.png)
8. **(Admin Only) Step 8: add questions, and show them only for some answers**
   ![Conditional question](.wordpress-org/screenshot-8.png)
9. **(Admin Only) Step 9: optional — limit the spots, in total or per option**
   ![Spots](.wordpress-org/screenshot-9.png)
10. **(Admin Only) Step 10: pick the methods for this button, then place it on a page or share its pay link**
    ![Payment methods and Place it](.wordpress-org/screenshot-10.png)
11. **(Admin Only) Step 11: give the button's checkout its own look**
    ![Checkout design](.wordpress-org/screenshot-11.png)
12. **(Admin Only) Step 12: page progress style and the pay page background**
    ![Page progress and pay page](.wordpress-org/screenshot-12.png)
13. **(Admin Only) Step 13: design the button and save it**
    ![Button design](.wordpress-org/screenshot-13.png)
14. **(Admin Only) Step 14: what happens after payment, and sending paid payments to your invoicing app**
    ![After payment and invoicing](.wordpress-org/screenshot-14.png)
15. **(Admin Only) Optional: connect a form or booking plugin ifthenpay has no integration with**
    ![Connect another plugin](.wordpress-org/screenshot-15.png)
16. **(Admin Only) Optional: build or edit a button with AI**
    ![Build with AI](.wordpress-org/screenshot-16.png)
17. **(Admin Only) Default Design › Checkout: the look of every checkout, pay page and popup**
    ![Default Design, Checkout tab](.wordpress-org/screenshot-17.png)
18. **(Admin Only) Default Design › Messages: what the payer sees after paying**
    ![Checkout messages](.wordpress-org/screenshot-18.png)
19. **(Admin Only) Default Design › Texts: every word the payer reads, in your own words or language**
    ![Checkout texts](.wordpress-org/screenshot-19.png)
20. **(Admin Only) Default Design › Links & emails: pay link address and payment emails**
    ![Links and emails](.wordpress-org/screenshot-20.png)
21. **(Admin Only) Payments: every payment, its status and the totals**
    ![Payments](.wordpress-org/screenshot-21.png)
22. **(Admin Only) A payment's details**
    ![Payment details](.wordpress-org/screenshot-22.png)
23. **(Customers Experience) Pay buttons on a page**
    ![Pay buttons on a page](.wordpress-org/screenshot-23.png)
24. **(Customers Experience) The checkout, here on the button's own pay page**
    ![Checkout](.wordpress-org/screenshot-24.png)
25. **(Customers Experience) ifthenpay's secure payment page**
    ![ifthenpay payment page](.wordpress-org/screenshot-25.png)
26. **(Customers Experience) The Paid popup back on your site**
    ![Paid popup](.wordpress-org/screenshot-26.png)
27. **(Design Example) Aurora gradient with waves, ring progress and priced options**
    ![Aurora gradient with waves](.wordpress-org/screenshot-27.png)
28. **(Design Example) A YouTube video behind the checkout**
    ![Video background](.wordpress-org/screenshot-28.png)
29. **(Design Example) Dark mode, following the payer's device**
    ![Dark mode](.wordpress-org/screenshot-29.png)
30. **(Design Example) Crystals on an aurora, a wide card with the logo above it**
    ![Crystals and a wide card](.wordpress-org/screenshot-30.png)

### Examples

What you can do with the design options. Every button can keep the site-wide look from **Default Design › Checkout** or have its own in the button's **Checkout design** and **Button design** tabs.

1. **Every button with its own design.** Pay Button › Buttons shows each button as it looks on your site, with what it collected and its spots.
   ![Buttons with their own designs](.wordpress-org/examples/example-1.png)
2. **Pay buttons on a page.** Gradient, colour, size, icon and width per button. The blue one is hovered, so it lifts.
   ![Pay buttons on a page](.wordpress-org/examples/example-2.png)
3. **Aurora gradient with waves.** A ring shows the form's progress, and the priced options set the amount.
   ![Aurora gradient with waves](.wordpress-org/examples/example-3.png)
4. **A long form split into pages.** Page breaks turn the form into steps, here with a bar per page and a Back button.
   ![A form in steps](.wordpress-org/examples/example-4.png)
5. **Diagonal gradient with crystals.** A light look with a single progress line and a percentage.
   ![Diagonal gradient with crystals](.wordpress-org/examples/example-5.png)
6. **A video behind the checkout.** Paste a YouTube or Vimeo link, or pick a video from the Media Library. You can loop all of it or just a part.
   ![Video background](.wordpress-org/examples/example-6.png)
7. **Blurred video and a glass card.** The video is blurred, and with card opacity under 100% the card turns to frosted glass. With spots per option, each option shows how many are left.
   ![Blurred video and a glass card](.wordpress-org/examples/example-7.png)
8. **A wide card with your logo above it.** Crystals on an aurora, with a gold accent.
   ![Wide card and logo](.wordpress-org/examples/example-8.png)
9. **Dark mode.** With "Follow the payer's device", payers whose phone or computer is set to dark get your dark colours.
   ![Dark mode](.wordpress-org/examples/example-9.png)
10. **On a phone.** The pay page and the checkout fit any screen.
    ![On a phone](.wordpress-org/examples/example-10.png)
11. **A video background on a phone.**
    ![Video background on a phone](.wordpress-org/examples/example-11.png)
12. **The Paid popup, in the button's own look.** The Pending, Failed and Cancelled popups match it too.
    ![Paid popup](.wordpress-org/examples/example-12.png)
13. **(Admin Only) Start from a look, then tweak it.** The colours, the outcome colours and the glass card, with a live preview of the checkout, the pay page and each popup.
    ![Checkout design](.wordpress-org/examples/example-13.png)
14. **(Admin Only) Seven page progress styles, and the pay page background.** Soft glow, gradient, plain, image or video, with crystals, waves, bokeh or grain on top.
    ![Page progress and pay page](.wordpress-org/examples/example-14.png)
15. **(Admin Only) Button design.** Start from a preset, then choose the style (filled, outline, soft, gradient, glass, 3D, neon, brutal or ghost), the colours, the corners, the border, the size, a hover effect (lift, grow, shine, glow, fill or nudge), an attention effect (pulse, ripple, heartbeat, wiggle, bounce, shimmer, breathe or orbit) and an icon.
    ![Button design](.wordpress-org/examples/example-15.png)

### Support

For assistance use the [WordPress.org support forum](https://wordpress.org/support/plugin/ifthenpay-pay-button/):

Pre-checks:

- Payment method enabled on Gateway Key AND mapped to Integration
- Running current recommended versions of WordPress & PHP

Commercial helpdesk available (no direct email required): [helpdesk.ifthenpay.com](https://helpdesk.ifthenpay.com/)

- **ifthenpay support**: [suporte@ifthenpay.com](mailto:suporte@ifthenpay.com)

### Security

Found a security issue? Please report it privately to [suporte@ifthenpay.com](mailto:suporte@ifthenpay.com) with "Security" in the subject, rather than in the public support forum. See [SECURITY.md](SECURITY.md) for the full policy.

### Changelog

Releases that fix a security issue say so in their entry, prefixed with **Security:**.

#### 1.0.0

- Initial public release: ifthenpay pay buttons (shortcode, block and a pay page per button) with fixed, payer-chosen or link-set amounts, a checkout form builder with multi-step pages and conditional questions, limited spots in total or per option, button and checkout design with a live preview, editable payer texts and messages, a redirect link builder, signed links and a signed outgoing webhook for other plugins, a direct request to your invoicing app when paid, revenue, abandonment and spots stats on the Buttons screen, webhook-confirmed payment status, receipts, the Payments screen with CSV export, a Dashboard widget, a weekly summary email, and automatic expiry of unpaid payments.
