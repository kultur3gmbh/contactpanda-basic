=== ContactPanda Basic ===
Contributors: kultur3gmbh
Tags: crm, contacts, companies, newsletter, woocommerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.9.104
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage people, companies, consent, newsletters and customer history directly in WordPress.

== Description ==

ContactPanda Basic provides an independent contact and company database for WordPress. It provides read-only Groundhogg previews and a migration assistant, presents WooCommerce customer and order summaries without changing source orders, and reads supported event and ticket systems without modifying their records.

Optional integrations are detected at runtime and remain hidden when their source plugin is unavailable. ContactPanda Basic remains fully usable without a paid ContactPanda extension.

== External services ==

ContactPanda Basic works without an external ContactPanda account. The following narrowly scoped services are used only for the described features:

* ContactPanda news: disabled by default. An administrator can explicitly allow the news service in General settings and withdraw permission there at any time. Only after this opt-in, opening the ContactPanda dashboard requests the public RSS feed from `https://pandacosmo.com/feed/`. The request sends the normal technical connection data, such as the site server's IP address, but no contacts, email lists, orders, or customer records. The service is operated by Kultur3 GmbH; privacy information is available at `https://pandacosmo.com/privacy-policy/`.
* EU VIES VAT validation: when an administrator or registrant explicitly validates a VAT ID, the country code and VAT number are sent to the European Commission VIES service at `https://ec.europa.eu/taxation_customs/vies/services/checkVatService`. No other ContactPanda profile data is sent. The European Commission privacy policy is available at `https://commission.europa.eu/privacy-policy-websites-managed-european-commission_en`.
* Optional salutation library: when an administrator explicitly imports the central salutation library, ContactPanda downloads a public JSON file from `https://raw.githubusercontent.com/kultur3gmbh/contactpanda-salutations/main/salutations.json`. No site or customer data is included in that request beyond normal technical connection data. GitHub's privacy statement is available at `https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement`.
* YouTube preview images: when an email template contains a YouTube video URL, its public preview image may be loaded from `https://i.ytimg.com/` while previewing or displaying that content. The visitor's browser sends normal technical connection data to Google. Google's privacy policy is available at `https://policies.google.com/privacy`.
* Optional Akismet spam protection: only after an administrator selects and saves Akismet as the central protection method, form submissions are checked at `https://<api-key>.rest.akismet.com/1.1/comment-check`. The request includes the site URL, IP address, browser user agent, referrer, form context, submitted name, email address and message (when present). The key can be provided by the active Akismet plugin or explicitly entered in ContactPanda. See `https://akismet.com/privacy/` and `https://akismet.com/tos/`.
* Optional Cloudflare Turnstile: only after an administrator selects and configures Turnstile, supported public forms load `https://challenges.cloudflare.com/turnstile/v0/api.js`. Cloudflare processes challenge/browser data; the server verifies the response token and IP address at `https://challenges.cloudflare.com/turnstile/v0/siteverify`. No ContactPanda contact database or mailing list is sent. See `https://www.cloudflare.com/privacypolicy/` and `https://www.cloudflare.com/terms/`.
* Optional Google reCAPTCHA v3: only after an administrator selects and configures reCAPTCHA, supported public forms load `https://www.google.com/recaptcha/api.js`. Google processes browser/challenge data; the server sends the response token, IP address and secret key to `https://www.google.com/recaptcha/api/siteverify` when a form is submitted. No contact database or mailing list is sent. See `https://policies.google.com/privacy` and `https://policies.google.com/terms`. Local honeypot, timing and rate-limit protection requires no external spam service.
* WhatsApp links: a deliberately configured WhatsApp button opens `https://wa.me/` with the destination number and, when configured, the prepared message. Data is sent only after a user follows that link. WhatsApp's privacy information is available at `https://www.whatsapp.com/legal/privacy-policy`.

== Changelog ==

= 0.9.104 =
* Veranstaltungssuche berücksichtigt Entwürfe und Sonderzeichen; Kompatibilität mit ACF Better Search verbessert.

= 0.9.103 =
* AJAX-Speicherung von Feldgruppen und gemeinsame Kontakt-Historie für Quick.

= 0.9.102 =
* Full-contact duplicate review with bounded processing and clear empty/error states; read-only Groundhogg source; aligned newsletter fields and update icon.

= 0.9.101 =
* Newsletter layouts, dismissible popup and footer; bounded contact API for ContactPanda Quick; PandaCosmo service links.

= 1.0.0 =
* First 1.0 release of the free ContactPanda CRM edition.

= 0.9.32 =
* Hardened request entry points with explicit nonce checks and verified capability boundaries.
* Bound database identifiers, dynamic list filters, dates and sort columns with wpdb placeholders.
* Cached schema metadata per request, avoiding repeated schema discovery and unnecessary newsletter DDL.
* Validated editor JSON and import payloads; retained rich text sanitation and safe public confirmation tokens.
* Added regression checks for unauthorized requests, SQL argument ordering, malformed payloads and schema-cache invalidation.
* Audited remaining custom-table, integration and token-link checker exceptions individually.

= 0.9.31 =
* Require explicit consent for optional remote news and document external spam services.
* Verify reCAPTCHA tokens once and generate fresh tokens on form submission.
* Improve translation safety, import upload validation and import review styles.

= 0.9.30 =
* Fixed import tags and stable shared drag-and-drop controls.

= 0.9.28 =
* Added protected new-only repeat migrations from Groundhogg and extension contracts for Plus Excel transfers.
* Import safeguards retain trashed records and remember future permanent deletions using salted identifiers.
* Adds a direct evaluation link and color-coded recipient interactions for every mailing.
* Improves recipient status localization and report clarity.


Earlier release notes are included in the project changelog archive.
