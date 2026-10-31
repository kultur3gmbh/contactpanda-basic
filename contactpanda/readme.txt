=== ContactPanda Basic ===
Contributors: kultur3gmbh
Tags: crm, contacts, companies, newsletter, woocommerce
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A user-friendly contact and company CRM for WordPress with a free, reduced newsletter workflow, a WooCommerce customer overview, optional Groundhogg compatibility, and stable extension APIs for the ContactPanda product family.

== Description ==

ContactPanda Basic provides an independent contact and company database for WordPress. It can also use Groundhogg as its leading contact source, presents WooCommerce customer and order summaries without changing source orders, and reads supported event and ticket systems without modifying their records.

Optional integrations are detected at runtime and remain hidden when their source plugin is unavailable. ContactPanda Basic remains fully usable without a paid ContactPanda extension.

== Changelog ==

= 0.9.0 =
* Provides the release candidate for the public ContactPanda Basic 1.0 line while preserving the extension APIs required by current Plus and Business releases.
* Retires ContactPanda Pro Legacy from protected family update registration without deleting installed plugins or historical data.
* Includes the corrected event-attendee identities, quick filters, and WooCommerce order summaries validated in 0.8.85.

= 0.8.85 =
* Corrects event-attendee counts and quick filters in the people list, including merged email identities.
* Keeps WooCommerce order totals consistent between the people list and person details by retaining cancelled and failed historical orders.
* Restores the labelled event-source compatibility contract used by supported extensions and repairs the complete validation suite.

= 0.8.84 =
* Adds reusable newsletter templates with duplication, preview, search, and a visual template gallery.
* Expands event blocks with manual or dynamic selection, time period, category, featured-only, sorting, count, and image presentation controls.
* Makes protected self-hosted updates use a verified local package when the license server and WordPress run on the same site.

= 0.8.83 =
* Adds visible live progress for The Newsletter Plugin migration.
* Keeps editable migrated templates separate from immutable historical mailing archives.

= 0.8.82 =
* Adds a repeatable WordPress-user migration that links people by email and completes only missing profile values.
* Adds a safe WooCommerce company-data adoption action and clearer migration progress and completion reporting.
* Preserves transparent system-email colors, centers the standard footer, and standardizes compact administration pagination.

= 0.8.81 =
* Normalizes current and historical product names so existing paid licenses unlock the same modules reliably.
* Improves Event Tickets attendee discovery without modifying source event or ticket records.

= 0.8.80 =
* Detects current The Events Calendar and Event Tickets installations even when the legacy attendee repository function is unavailable.
* Preserves the read-only event-ticket metadata fallback for customer and event links.
* Extends Newsletter Plugin migration reporting for reusable email sources and historical mailings.

= 0.8.79 =
* Added centralized user and role permissions for contacts, marketing, analytics, support, projects, and settings.
* Expanded the preference center for channels, topics, and correspondence language without bypassing double opt-in.
* Improved the shared permission contracts used by Plus and Business.

= 0.8.78 =

* Coordinates the family hotfix that keeps legacy Pro and the merged Business package safe during staged updates.

= 0.8.77 =

* Finalizes the Basic, Plus, Business, and future Members product structure.
* Moves the former Pro CRM and support ownership to ContactPanda Business without deleting existing data.

= 0.8.75 =
* Finalizes the ContactPanda Basic product, repository, package, and updater naming contract while retaining the stable WordPress.org plugin identifier.
* Coordinates the complete ContactPanda family release at version 0.8.75.

= 0.8.74 =
* Removes the persistent public-release metadata cache so WordPress update checks see newly published ContactPanda family versions immediately.
* Keeps release metadata cached only for the current request and preserves compatibility checks for cached WordPress update offers.

= 0.8.73 =
* Adds the shared product and plan license center for individual keys and MasterKeys.
* Shows installed versions, update and support terms, and licensed website usage without storing raw keys.
* Disconnects invalidated activation tokens after a server-side key rotation while preserving the existing product policy.

= 0.8.72 =
* Coordinates the ContactPanda family dependency and release contract for the Pro document-template handoff to PandaWoo Pro.

= 0.8.71 =
* Standardizes the public repository and visible product name as ContactPanda Basic while preserving the existing plugin folder, data, and extension contracts.

= 0.8.70 =
* Repairs public GitHub update discovery by accepting GitHub's trusted version redirect before the release asset download.

= 0.8.69 =
* Adds a visual header and footer composer for central system-email templates while keeping the automatic message body unchanged.
* Optionally frames compatible WordPress system emails and avoids double-framing complete HTML and WooCommerce messages.
* Rebuilds email galleries as ordered, individually editable image rows.

= 0.8.68 =
* Adds a centrally configurable visual frame for transactional system emails, shared by the ContactPanda family.
* Makes the public Basic updater independent of anonymous GitHub API limits while retaining integrity verification.
* Routes confirmations, registration notices and account messages through the shared system-email template.

= 0.8.67 =
* Adds complete, filterable and paginated newsletter recipient reports without the previous 500-recipient display limit.
* Fixes multi-product selection, improves product image quality and keeps color controls inside their parent panel.

= 0.8.66 =
* Adds safe repeatable Groundhogg automation updates and recognizes WooCommerce new-order triggers.
* Improves email-template actions, completed-mailing reports, and the newsletter list presentation.
* Refines the trash workflow with compact filters and explicit confirmation only for permanent deletion.

= 0.8.65 =
* Recognizes Groundhogg delay, confirmation, WordPress-user, role, notification, and link-click steps from their stored metadata.
* Migrates Groundhogg forms and unknown custom fields with stable source identifiers and links imported form triggers to automation drafts.
* Improves newsletter result summaries and adds safe edit, preview, and delete controls for email templates.

= 0.8.64 =
* Separates Groundhogg people, email, form, and automation migrations into independent analyze-and-import steps.
* Simplifies newsletter template usage to counts and mailing recipient results to sent and failed totals.
* Shows the completed delivery timestamp below the mailing delivery time.

= 0.8.63 =
* Adds searchable template status and date views, read-only sent templates, and direct editable-copy handoff.
* Preserves Groundhogg source timestamps separately from the repeatable import timestamp.
* Adds a public Panda newsletter archive block and an MCP tool for creating editable email drafts.
* Removes duplicate company information from email defaults in favor of the central company profile.

= 0.8.62 =
* Separates Groundhogg people, email, form, and automation migrations so each area can be reviewed and run independently.
* Adds explicit order and event participation filters to the people search.
* Adds read-only newsletter result cards and exact recipient send, open, and click times.

= 0.8.61 =

* Preserves Groundhogg custom-field definitions and public metadata during repeatable migrations.
* Resolves migrated custom fields, email addresses, and unsubscribe URLs in newsletter placeholders.
* Keeps source-system records unchanged while preparing editable email migrations in Plus.

= 0.8.60 =

* Adds normalized contact channels and external identities without rewriting WordPress, WooCommerce, or source-system records.
* Makes duplicate merging traceable through a recoverable merge history and preserves all linked identities.
* Consolidates shared company details in Basic and removes the obsolete duplicate editor from Pro.

= 0.8.59 =
* Rebuilds the coordinated family packages from committed sources and verifies their internal versions before publication.
* Provides the matching Basic dependency contract for Plus, Pro, and Business 0.8.59.

= 0.8.58 =
* Adds shared company, payment, tax, and brand details under ContactPanda Basic settings for use by the entire product family.
* Keeps legacy Pro company-profile values as a non-destructive fallback and never changes WooCommerce source settings.
* Supplies the coordinated dependency and protected-update contract for Plus, Pro, and Business 0.8.58.

= 0.8.57 =
* Adds campaign-level A/B subject lines and reusable UTM tracking parameters.
* Publishes newsletter open and link-click events for automations and reports.
* Extends the newsletter schema without changing existing campaigns, recipients, consent records, or source data.

= 0.8.56 =
* Coordinates the family dependency and protected-update contract for Plus, Pro, and Business 0.8.56.

= 0.8.55 =
* Consolidates the customer-account navigation so extensions cannot create duplicate sections.
* Moves logout below the account content and hides membership sections that are unavailable to the current customer.
* Supplies the coordinated dependency and protected-update contract for Plus, Pro, and Business 0.8.55.

= 0.8.54 =
* Adds a dedicated Migration area with source tabs shown only for detected systems.
* Adds a non-destructive FluentCRM contact preview and migration foundation with stable source identifiers.
* Keeps Import / Export separate and provides extension points for additional migration adapters.

= 0.8.53 =
* Separates Migration from Import / Export and preserves non-destructive migration previews.
* Adds stricter coordinated family dependencies and extends the theme preview with button rendering.

= 0.8.52 =
* Adds button previews to both ContactPanda and active-theme appearance choices so inherited button styling is visible before saving.
* Supplies the coordinated family dependency and protected-update contract for Plus, Pro, and Business 0.8.52.
* Preserves contacts, companies, consent history, newsletters, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.51 =
* Adds a read-only Groundhogg flow compatibility analysis with clear directly supported, review-required, and unsupported steps.
* Lets ContactPanda Plus create inactive automation drafts from re-read Groundhogg funnels without changing source records or activating incomplete migrations.
* Refines the compact overview with readable release news and a direct, prefilled feedback route while preserving standalone and Groundhogg data modes.

= 0.8.50 =
* Removes the stale secondary release-metadata cache so coordinated Plus, Pro, and Business updates become visible as soon as WordPress refreshes plugin updates.
* Keeps request-local deduplication and WordPress update transients without delaying a newly published family release.
* Preserves contacts, companies, consent history, newsletters, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.48 =
* Adds the bundled ContactPanda product visual to the dashboard and lets active family extensions provide their matching local visual without remote dependencies.
* Provides the shared dashboard contract used by Plus while keeping Basic fully usable on its own.
* Adds a provider-neutral extension contract for named tags and purpose-specific pending, confirmed, and revoked consent states, including Panda Seasons compatibility.
* Preserves contacts, consent history, newsletters, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.47 =
* Keeps the shared color picker fully visible inside narrow inspector panels and wraps its controls only when the required minimum width is unavailable.
* Aligns the hue and transparency sliders and prevents the picker panel from being clipped by email-composer containers.
* Preserves contacts, consent history, newsletters, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.46 =
* Replaces scattered color grids with one compact family-wide color picker for newsletters, invoice templates, and Business project colors.
* Adds accessible hue and transparency controls, theme and template colors behind the globe button, and reliable HEX, RGB, and RGBA persistence.
* Keeps contacts, consent history, newsletters, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.45 =
* Aligns Basic with the independently rendered and lazily loaded Pro Support Desk and the common 0.8.45 family dependency contract.
* Refreshes the shared protected-update metadata so every installed family component can discover the coordinated release.

= 0.8.44 =
* Aligns ContactPanda Basic with the repaired Pro Support Desk and the common 0.8.44 family dependency contract.
* Keeps contacts, companies, consent history, newsletter data, WooCommerce orders, standalone mode, and Groundhogg mode unchanged.

= 0.8.42 =
* Adds compact inline editing with accessible confirm and cancel icons throughout person lists and keeps long-text editing spacious.
* Adds reusable hero and social-media blocks to the visual email composer, including responsive previews and configurable overlay styling.
* Consolidates the shared editing and newsletter-block presentation rules without changing contacts, consent history, orders, or source-system records.

= 0.8.41 =
* Adds the consolidated ContactPanda dashboard, WordPress dashboard news, family upgrade information, and the agreed Basic/Plus menu order.
* Improves the independent customer profile presentation and exposes a provider-neutral extension contract for separate newsletter consent.
* Keeps standalone and Groundhogg modes, existing contacts, consent history, WooCommerce orders, and extension APIs unchanged.

= 0.8.40 =
* Establishes the final product boundaries for Basic, Plus, Pro, and Business while preserving existing contacts, consent records, newsletters, WooCommerce orders, and source-system identifiers.
* Keeps WordPress identity, the customer preference portal, the shared MCP transport, and the reduced newsletter workflow independent from WooCommerce and commercial extensions.
* Adds the shared administration interaction layer used by family extensions and documents the non-destructive customer-account lifecycle.

= 0.8.30 =
* Adds the central, license-independent MCP server for people, companies, tags, newsletter preparation, and every active ContactPanda family extension.
* Lets Plus, Pro, and Business register their own licensed domain tools without creating competing MCP endpoints or requiring Plus as a technical dependency.
* Keeps the reduced newsletter and subscriber migration in free ContactPanda while moving events and advanced delivery functions behind the complete Plus package.
* Preserves standalone and Groundhogg data, consent records, newsletter history, WooCommerce orders, and source-system records without a database migration.

= 0.8.20 =
* Keeps the reduced newsletter workflow permanently available in free ContactPanda and defines canonical Plus entitlements for advanced newsletters, forms, automations, integrations, and migrations.
* Adds a seven-day offline tolerance for temporary license-server outages without silently removing an existing entitlement.
* Unifies current product names, dependency information, translations, and protected-update metadata across the 0.8.20 family.
* Preserves standalone and Groundhogg modes as well as all existing contacts, consent records, newsletter history, WooCommerce orders, and source-system data.

= 0.8.15 =
* Establishes the provider-neutral WordPress identity layer and central customer portal without making newsletter, Seasons, or profile access depend on WooCommerce.
* Centralizes protected family update registration and cache refreshes for Plus, Pro, and Business while preserving the public Basic update channel.
* Removes obsolete runtime duplication and documents the current ownership boundaries without changing imported contacts, orders, newsletter history, or source-system records.
* Keeps standalone and Groundhogg data-source modes compatible and prepares the complete family for the shared 0.8.15 dependency contract.

= 0.8.11 =
* Arranges the standard newsletter signup as first/last name followed by salutation/email and lets its submit button inherit the active theme style.
* Rebuilds plugin and protected-product update caches immediately after an installation and no longer caches temporary negative GitHub release checks.
* Coordinates the common 0.8.11 dependency and update contract for Basic, Plus, Pro, and Business.

= 0.8.10 =
* Rebuilds the complete ContactPanda product family with matching internal versions, ZIP contents, and update metadata.
* Preserves the corrected Plus, Pro, and Business package entitlement mapping introduced in 0.8.9.

= 0.8.9 =
* Allows a package update when a valid license grants any module belonging to Plus, Pro, or Business.
* Clears both ContactPanda and WordPress update caches after license changes, completed updates, and the native Check again action.
* Keeps package families independent while preserving standalone mode, Groundhogg mode, and the existing database schema.

= 0.8.8 =
* Coordinates ContactPanda Basic with the shared 0.8.8 product-family release.
* Preserves the current database schema, customer data, standalone mode, and Groundhogg compatibility.

= 0.8.1 =
* Adds a stable, optional extension point for additional tabs in the person detail view.
* Keeps ContactPanda Basic unchanged when no extension is active and preserves standalone as well as Groundhogg data modes.

= 0.8.0 =
* Establishes the shared 0.8 extension contract for independently installable Plus, Pro, Business, and future Members products.
* Keeps people, companies, consent, WooCommerce summaries, and the licensed Basic newsletter as the common non-destructive data foundation.
* Preserves standalone and Groundhogg data modes while optional products feature-detect the stable ContactPanda extension API.

= 0.7.8 =
* Adds a compact ContactPanda dashboard with direct totals for people, companies, email templates, mailings, campaigns, saved searches, and blocked email addresses.
* Keeps filtering, saved searches, quick search, and quick filters together in one clear toolbar and preserves the complete filtered list for person-to-person navigation.
* Starts a newsletter mailing directly from selected people, including an explicit option to select every matching person across all result pages.
* Improves the mailing overview with selected-recipient totals, grouped delivered/opened/clicked statistics, and clearer immediate-delivery wording.
* Condenses Marketing & Consent details and adds audited administrator controls for permanent email blocks without overriding protected bounce, spam, complaint, or unsubscribe states.
* Moves previous/next navigation directly above the person details and shows the current position within the complete result set.

= 0.7.7 =
* Moves newsletter mailing creation and recipient details from modal overlays to dedicated administration pages.
* Separates saving, immediate delivery, and scheduled delivery with clear labels, recipient-aware confirmations, and lossless in-page validation of administrative exceptions.
* Replaces mailing preview text controls with consistent square device icons and aligns the add-filter icon.
* Adds a branded, no-index unsubscribe confirmation with site logo, site name, full subscriber identity, explicit cancellation, and a final confirmation page.
* Allows confirmed double opt-in and documented administrator actions to restore a voluntary unsubscribe while retaining consent history and never clearing bounce, spam, complaint, or block protections.

= 0.7.6 =
* Uses the selected email template subject automatically when preparing a newsletter mailing.
* Adds campaign creation directly from the mailing workflow and supports filtering newsletter mailings by one or more campaigns.
* Adds recipient sources for saved searches, registered customers, and all contacts, with live eligible-recipient counts and permanent suppression protection.
* Adds an administrator-only, explicitly confirmed and audited option to include recipients without documented marketing consent while never including unsubscribed, blocked, complained, invalid, or suppressed addresses.
* Lets email templates be returned to draft status or published and updated explicitly.
* Unifies email color controls, exposes colors from the active theme and email template, and completes German newsletter and campaign interface translations.

= 0.7.5 =
* Optimizes newsletter content for mobile devices with compact outer spacing, a frameless content area, full-width video buttons, and hidden YouTube/Vimeo badges.
* Places single-image and gallery captions below the image at full width on mobile while retaining their configured typography and colors.
* Adds a searchable registered-customer recipient source that permanently excludes unsubscribed, blocked, complained, undeliverable, and globally suppressed addresses.
* Adds an administrator-only, explicitly confirmed and audited exception for mailings without documented marketing consent without changing the underlying consent state.

= 0.7.4 =
* Adds nested two- and three-column email layouts whose columns accept all standard content blocks by drag and drop.
* Adds dedicated phone, email, and WhatsApp buttons and opens linked videos in a new browser tab.
* Adds responsive one-to-three-column gallery settings for desktop, tablet, and mobile, drag-and-drop image ordering, and configurable captions for gallery and single images.
* Adds optional YouTube and Vimeo provider badges and shows the preview at the configured email width with compact device icons.

= 0.7.3 =
* Adds email-safe video links with preview images and a visual image gallery block with one to three columns, grid or masonry layout, fixed or original proportions, and up to six images.
* Refreshes the ContactPanda administrator menu icon from the official panda artwork.

= 0.7.2 =
* Refreshes short-lived protected package links immediately before download, routes bulk updates to the correct plugin, verifies checksums, and clears update caches after installation.

= 0.7.1 =
* Consolidates the current Basic administration, newsletter form integrations, and protected-update release.

= 0.7.0 =
* Keeps list searches and filters visible in standalone and Groundhogg mode and stabilizes person and company layouts in Safari.
* Persists Screen Options columns, groups order count and total, and returns to the current list after moving an individual record to trash.
* Adds a confirmed, timestamped final newsletter unsubscribe workflow and address-specific consent history.
* Fixes licensed updater cache invalidation so an installed version is not offered again.

= 0.6.5 =
* Prevents incomplete or stale translation catalogs from removing administrator controls and structural page wrappers.
* Restores quick search, filters, tabs, and stable People and Companies detail layouts in ContactPanda and Groundhogg modes, including Safari.
* Replaces the fragile fixed people-table layout and separates Orders from Order total for reliable column alignment.
* Adds a provider-neutral person-creation contract for separately installed Pro modules without granting marketing consent.

= 0.6.4 =
* Restores the always-visible quick search and filter rows in both ContactPanda and Groundhogg data-source modes.
* Aligns Screen Options with the actual people-table column order.
* Moves Registration & accounts back to the main settings navigation and removes empty navigation entries.
* Adds grouped license presentation for ContactPanda, Plus, and Pro entitlements.

= 0.6.3 =
* Corrected and stabilized the People and Companies list columns, including responsive email presentation.
* Added native Screen Options for optional People and Companies columns.
* Restored the quick search and filter controls in Groundhogg data-source mode.
* Repaired the responsive People and Companies detail layouts.
* Kept automatic activities separate from contact notes, including historical Groundhogg entries.
* Moved Licenses below General and kept Miscellaneous as the final settings tab.
* Added the ContactPanda website icon to the WordPress admin menu.

= 0.6.2 =
* Keeps the People table visible and horizontally scrollable in Safari and on smaller screens.
* Gives the email column a readable minimum width and truncates long addresses without breaking them across many lines.
* Loads the ContactPanda design and interactions on Import / Export so CSV and vCard dropzones are displayed and usable.
* Preserves standalone and Groundhogg data-source behavior without changing imported or external records.

= 0.6.1 =
* Restores clearly visible drag-and-drop areas for CSV and vCard imports while retaining file selection and preview workflows.
* Streamlines People list searches and bulk actions, aligns row controls, and adds explicit confirmation before direct deletion.
* Presents company relationships compactly and edits role and primary-company settings in an accessible dialog.
* Keeps notes and automatic activities in separate timelines, including a safe repair of previously misclassified entries.
* Improves administrator labels, translations, controls, and layout without changing WooCommerce orders or external source records.

= 0.6.0 =
* Adds a Newsletter license for ContactPanda Basic without requiring ContactPanda Plus or a separate newsletter plugin.
* Keeps an activated newsletter module permanently functional after license expiry; only updates and support depend on the active license period.
* Moves the shared email-template editor, saved audiences, controlled mailing queue, consent checks, unsubscribe protection, and statistics into Basic.
* Reuses existing ContactPanda newsletter tables so Basic and Plus never create competing newsletter data.
* Keeps unlicensed Basic installations fully functional as the free CRM and lets Plus grant the shared newsletter entitlement automatically.

= 0.5.0 =
* Reorganizes integrations into dedicated WooCommerce, Groundhogg, messaging, and email-delivery areas and places the active data source with Groundhogg.
* Promotes Import and Export to a separate administration area with entity-specific entry points and an auditable export history.
* Adds language-aware salutation libraries for German, English, French, Spanish, and Italian with inline editing and pagination.
* Improves duplicate review with pagination and name search while keeping Groundhogg-managed data and source systems unchanged.
* Keeps optional event integrations source-selectable and provides stable extension events for event registrations and ContactPanda Plus automations.

= 0.4.5 =
* Lets administrators assign several companies to one person and mark exactly one relationship as the primary company.
* Adds direct email and telephone actions to every saved contact channel and aligns row email actions consistently.
* Allows duplicate candidates to be deselected inside the merge review before choosing final values and primary channels.
* Restricts the Trash screen to records already moved there; moving active records to Trash remains in the People and Companies lists.
* Keeps the ContactPanda Plus menu order stable by placing Trash after optional Plus modules.

= 0.4.3 =
* Adds stable tag-added events for ContactPanda Plus flow triggers, including genuine additions performed through bulk actions.
* Adds channel-specific consent storage for future SMS and WhatsApp gateway integrations without changing email marketing consent.
* Keeps messaging providers, automation, and the visual flow composer exclusively in ContactPanda Plus.

= 0.4.2 =
* Provides the compatible Basic foundation and stable extension contracts for the visual, versioned flow composer in ContactPanda Plus.
* Keeps flow administration, automation, and email delivery exclusively in Plus; ContactPanda Basic remains fully usable without it.
* Preserves standalone and Groundhogg data-source modes as well as the read-only event integrations introduced in 0.4.0.

= 0.4.1 =
* Adds stable extension events for newly created contacts and confirmed marketing consent so ContactPanda Plus can run consent-aware flows.
* Keeps all flow administration and email automation exclusively in ContactPanda Plus.
* Retains the read-only event integrations and event-source selection introduced in 0.4.0.

= 0.4.0 =
* Restores read-only event bookings and attendance in the People list and person details without requiring ContactPanda Plus.
* Adds read-only Amelia appointments and event bookings matched through customer email addresses.
* Requires a single site-wide event source when Amelia and The Events Calendar/Event Tickets are active, preventing mixed results.
* Keeps source systems unchanged and reserves advanced event segments and future flows for ContactPanda Plus.

= 0.3.7 =
* Prevents the People list from loading the complete WooCommerce order history when calculating customer totals.
* Uses bounded customer-filtered order queries compatible with classic WooCommerce order storage and HPOS.
* Reduces Groundhogg list payloads and safely ignores refund records that are not customer orders.

= 0.3.6 =
* Clarifies ContactPanda and Groundhogg data-source behavior and keeps standalone-only tools out of Groundhogg frontend mode.
* Makes WooCommerce imports fill missing ContactPanda values without overwriting existing contact data.
* Refines tag and custom-field administration and improves Event Tickets compatibility for Groundhogg contacts.
* Improves diagnostics and safe validation for the optional central salutation library.

= 0.3.5 =
* Corrects the Groundhogg migration request so that WordPress validates its result before returning to the migration report.
* Keeps administrators on the migration screen when the server returns an error instead of opening that response as a page.
* Preserves the non-destructive, repeatable migration and the live progress display.

= 0.3.4 =
* Adds explicit central salutation-library controls with safe opt-in and retained local data when disabled.
* Adds direct list editing for people, companies, and tags.
* Improves duplicate merging with a modal editor and selectable email and phone values.
* Improves person master-data layout, empty salutations, and compact email/phone add controls.

= 0.3.3 =
* Adds a clear administrator setting for verified automatic Basic updates from the public GitHub release channel.
* Improves WooCommerce imports by completing missing guest names from older orders and cautiously recognizable email addresses without changing WooCommerce data.
* Refines contact channel editing, marketing labels, company actions, Groundhogg migration guidance, integrations, and the central salutation-library workflow.

= 0.3.2 =
* Adds configurable custom fields for people and companies with typed values, searching, CSV import/export, duplicate merging, and Groundhogg metadata support.
* Separates manually entered and form-derived notes from automatically recorded CRM activities in person and company details.
* Exposes custom person fields to ContactPanda Plus form mappings and saved audience segments while keeping Basic fully usable on its own.

= 0.3.1 =
* Activates the public GitHub release channel for verified ContactPanda Basic updates in WordPress.
* Publishes matching ZIP and checksum metadata while retaining explicit WordPress auto-update opt-in.
* Keeps ContactPanda Plus and ContactPanda Checkout on their independent update and version channels.

= 0.3.0 =
* Renames the plugin and all new internal identifiers to ContactPanda, including clean-install database tables using the WordPress table prefix followed by cp_.
* Improves duplicate review with selectable subsets, editable final values, retained secondary contact details, and exactly one primary email address and telephone number.
* Moves Tags into Miscellaneous parameters and improves compact multiple-phone editing.
* Adds auditable CSV and vCard import runs, affected-record filters, conflict-safe rollback, and stable UUID groundwork for future synchronized apps.
* Adds direct, explicitly confirmed single-record deletion alongside the existing bulk lifecycle controls.

= 0.18.0 =
* Replaces the People overview/import tabs with direct Duplicate search and Import actions that open the corresponding Settings areas.
* Makes person and company values directly clickable for inline editing and allows salutations and tags to be maintained without leaving the detail view.
* Adds typed multiple email addresses and telephone numbers with an explicit primary contact detail while preserving the existing primary fields used by WooCommerce, Groundhogg, and ContactPanda Plus.
* Retains every email address and telephone number from vCard imports and duplicate merges without modifying source-system records.

= 0.17.15 =
* Adds Notes as the first person-detail tab, with typed notes, optional titles, and an editable date and time.
* Adds dedicated Import subtabs and actions to People and Companies while retaining previewed, non-destructive CSV and vCard workflows.
* Adds accessible drag-and-drop file areas for CSV and vCard imports.
* Shows the most recent central salutation-library synchronization in the salutation settings.

= 0.17.2 =
* Reorganizes settings into General, Integrations, Miscellaneous parameters, Forms, Newsletter, and Miscellaneous areas while preserving legacy settings links.
* Adds a theme-compatible built-in registration page when no WordPress registration page is selected.
* Groups transitive duplicate candidates and opens all records directly in the editable, non-destructive merge comparison.
* Adds a read-only overview for The Newsletter Plugin and central administrator-only ContactPanda Plus guidance without changing source-plugin data.
* Moves migration, duplicate review, administrator-notice controls, and optional CSS extensions into the consolidated Miscellaneous area.
* Renames the salutation bulk action to Reconcile now and reports imported Groundhogg broadcast-recipient assignments when available.

= 0.17.1 =
* Moves duplicate review and merging into a dedicated Settings tab and corrects links to person details.
* Allows administrators to complete or correct the final person or company fields before confirming a duplicate merge.
* Adds previewed, non-destructive vCard/VCF imports for Apple Contacts and other vCard sources, with duplicate handling and optional tags.

= 0.17.0 =
* Adds previewed CSV imports for people and companies with flexible field mapping, duplicate handling, and direct Groundhogg imports when Groundhogg is the active data source.
* Adds a guided duplicate merge for local people and companies with a selected primary record, per-field conflict choices, relationship preservation, an activity log, and recoverable source fragments in Trash.
* Makes salutation-library entries editable and adds an explicitly optional central library that never uploads customer data or overwrites local decisions.
* Adds an administrator preference for dismissing non-critical license and upgrade reminders while preserving errors, security warnings, and WordPress core update notices.
* Corrects vertical alignment of the Export and Print icons.

= 0.16.6 =
* Adds a reusable double-opt-in confirmation notice with the submitted email address, spam-folder reminder, resend action, and protected email correction.
* Places the optional company field above the customer name for both billing and shipping addresses in My Account.
* Replaces the VAT validation action with a compact green confirmation check after successful validation.
* Adds an inline company assignment to existing person records and reuses or creates a minimal company from freely entered customer company names without changing WooCommerce or Groundhogg source data.

= 0.16.5 =
* Presents private and business customer registration as accessible tabs instead of separate buttons.
* Keeps the registration panel open and in view when visitors switch between registration types.
* Preserves standalone ContactPanda and Groundhogg data-source modes without changing existing contact data.

= 0.16.2 =
* Provides an additional test release while preserving standalone ContactPanda and Groundhogg data-source modes.
* Refines the shared administration controls used by optional form integrations and improves active My Account navigation spacing.
* Retains non-destructive, repeatable migrations, protected deletion workflows, and the stable extension contracts used by ContactPanda Plus.

= 0.16.1 =
* Keeps company-list edit, view, and trash controls on the same line as the company name.

= 0.16.0 =
* Automatically applies learned salutation assignments when known first names are entered, while preserving every explicit selection and extending the library from confirmed assignments.
* Groups the company-list edit, view, and trash controls into one compact and accessible action set.
* Adds responsive shared administration styles for the ContactPanda Plus form-field mapper without making Basic depend on Plus.

= 0.15.5 =
* Synchronized ContactPanda Basic with ContactPanda Plus and ContactPanda Checkout 0.15.5 while keeping cart and checkout presentation outside the Basic plugin.

= 0.15.4 =
* Synchronized ContactPanda Basic with ContactPanda Plus 0.15.4 while keeping all cart and checkout presentation exclusively in Plus.

= 0.15.3 =
* Synchronized ContactPanda Basic with ContactPanda Plus 0.15.3 while keeping cart and checkout presentation exclusively in Plus.

= 0.15.1 =
* Corrected the trash glyph alignment in the compact person-list action group.

= 0.15.0 =
* Groups the edit, view, and trash controls in the people list into one compact action set.

= 0.14.8 =
* Added stable extension contracts that let ContactPanda Plus provide optional checkout consent while preserving standalone ContactPanda and Groundhogg data modes.
* Kept the free WooCommerce integration focused on customers, addresses, orders, invoices, and customer totals; cart and checkout presentation remain Plus features.
* Replaced the visible Trash column label and row text links in people and company lists with compact accessible trash-icon buttons.

= 0.14.6 =
* Added a secure GitHub release updater for ContactPanda Basic and the shared updater contract used by ContactPanda Plus.
* Added release-manifest, checksum, package-root, version, WordPress, PHP, and Basic compatibility validation before installation.
* Added administrator-controlled manual checks and opt-in automatic updates without changing ContactPanda customer data or settings.

= 0.14.5 =
* Added an administrator-only Trash for standalone ContactPanda people and companies with individual and bulk actions.
* Added safe restoration while preserving ContactPanda relationships and stable record identifiers.
* Added explicitly confirmed permanent deletion of local ContactPanda records with transactional rollback; WordPress users, orders, invoices, events, Groundhogg records, and external source data remain untouched.
* Prevented repeat imports from silently recreating people or companies while their ContactPanda records remain in Trash.

= 0.14.0 =
* Added separate registration setting areas for general customers, business customers, and newsletter signup.
* Added persistent back, previous, and next navigation from person details to the active people-list view.
* Replaced the destructive reset checkbox with an explicit warning dialog and kept migrations administrator-only and non-destructive toward source systems.
* The Newsletter Plugin migration is shown only when its installation is detected and now supports a preview before import.
* Generalized person communication history from Groundhogg broadcasts to source-labelled mailings supplied by optional modules.

= 0.13.2 =
* Added a dedicated migration center for Groundhogg, WooCommerce, and The Newsletter Plugin imports.
* Separated standard and business customer registrations and added a standalone newsletter signup with double opt-in.
* Added a safe duplicate-candidate preview without automatic data merging.

= 0.13.1 =
* Corrected Groundhogg contact values in filtered people lists and made double-opt-in emails richly editable.

= 0.13.0 =
* Corrected a PHP namespace syntax error in the complete Groundhogg contact loader that made Basic 0.12.4 unusable.
* Retains the full-data search and identity-mapping improvements prepared for 0.12.4.

= 0.12.4 =
* Advanced searches now load the complete Groundhogg contact set independently of list pagination.
* Prepared shared full-dataset identity mapping for reliable WooCommerce purchase and Event Tickets searches.

= 0.12.3 =
* Company lists now show their assigned people, while person master data and address presentation have been tightened and clarified.
* Improved German labels, alignment, and list presentation across the contact and company administration.
* Database upgrades now finish before the first ContactPanda table query and verify required tables and fields before marking an upgrade complete.

= 0.12.2 =
* Reordered person master data, improved address display, and expanded the salutation library with safe bulk assignment.
* Improved compatibility of the salutation library in both ContactPanda and Groundhogg data modes.

= 0.12.0 =
* Added salutations with a locally learned, editable and CSV-mergeable first-name dictionary; suggestions are never applied automatically.
* Added optional tag descriptions and reorganized person master data, tags, consent, menu order, address display, and people-list number alignment.
* Added a safe two-step WooCommerce customer import for standalone installations without Groundhogg; email is the unique merge key and orders remain untouched.
* Country selectors now show Austria, Germany, and Switzerland first, followed by a separator and the remaining countries alphabetically.
* ContactPanda database upgrades now run automatically for existing standalone installations.

= 0.11.5 =
* Added company assignment while creating a person, data-source-aware company diagnostics, and direct company creation.
* Added selectable export fields, print-only company-name shortening, and a combined sortable orders heading.

= 0.11.4 =
* Added secure CSV export for selected people or every person matching the current search and filters.
* Added a print-list dialog with selected/all scope and individually selectable columns.
* Exposed customer and WordPress role editing clearly to administrators while retaining permission safeguards.
* Groundhogg role and system tags are hidden from ContactPanda tag selectors without deleting source data.

= 0.11.3 =
* Added the ContactPanda Plus advanced-filter state to the people list, sorting links, quick filters, and pagination without changing the selected data source.
* Kept the people list as the single working screen for advanced contact searches.

= 0.11.2 =
* Added a structured contact address that is stored in the selected ContactPanda or Groundhogg data source independently of a WordPress/WooCommerce account.
* The address shortcut in person master data now opens the contact address editor; an explicit checkbox can copy it to the WooCommerce billing profile without changing existing orders or invoices.
* New ContactPanda registrations and Groundhogg migrations retain the structured primary address in the ContactPanda database.
* Vertically centered the people-list selection checkboxes.

= 0.11.1 =
* Renamed the former test database throughout the interface to ContactPanda database.
* Fresh installations without Groundhogg now create and select the ContactPanda database automatically.
* Added explicit Groundhogg frontend and ContactPanda database modes; Groundhogg remains responsible for broadcasts, funnels, and automations in its mode.
* Groundhogg migration tools are hidden when Groundhogg is unavailable and remain read-only toward Groundhogg.
* Replaced the one-time theme-style action with a visible ContactPanda/theme style switch that shows the active choice.
* Right-aligned monetary totals in order and invoice tables.
* Added a migration extension point used by ContactPanda Plus for protected Groundhogg email and broadcast archives.
* CSV import and export are intentionally not included in this release.

= 0.11.0 =
* Added public, read-only extension APIs for safe segment evaluation and newsletter recipients.
* Added a protected unsubscribe bridge for both ContactPanda standalone data and Groundhogg contacts.
* Added the saved-segment selector to the people list while keeping filtering, sorting, and pagination compatible.
* Protected hard-bounce, spam, complaint, and blocked statuses from the ordinary manual consent override.
* Kept Basic translations in the independent `contactpanda` text domain and language catalog.

= 0.10.0 =
* Split ContactPanda into the free ContactPanda Basic plugin and the optional ContactPanda Plus extension package.
* ContactPanda Basic retains people, companies, addresses, tags, consent, registration, Groundhogg compatibility, and the WooCommerce customer and order overview.
* Newsletter templates and the visual composer moved to ContactPanda Plus without changing their existing database table or settings.
* The Events Calendar and Event Tickets Plus adapter moved behind a ContactPanda Plus interface; Basic no longer contains third-party attendee queries.
* Added extensible settings tabs and event data filters so future modules can be added without editing the Basic plugin.
* Disabling ContactPanda Plus hides its modules but never deletes contacts, orders, event data, templates, or settings.

= 0.9.2 =
* Text blocks now provide a formatting toolbar directly above the selected block and can be edited immediately inside the email canvas.
* Image blocks expose alignment and width controls directly on the block and in the sidebar; widths can use pixels or percent.
* Added an optional WooCommerce product block for two or four products selected from latest, category, producer/brand or product attribute, featured, sale, or manual sources.
* Product blocks query current WooCommerce product images, names, prices, and links without duplicating product data in ContactPanda.
* When WooCommerce is unavailable, the product block is hidden from the palette while existing product blocks remain intact and display a clear notice.
* The people list displays email addresses as plain text with a separate mailto icon and suppresses Groundhogg's global email detector throughout ContactPanda.

= 0.9.1 =
* Rebuilt the newsletter template editor as a three-part visual composer with draggable content blocks, an email canvas, and a contextual settings sidebar.
* Rich-text blocks now use the WordPress WYSIWYG editor, while image blocks select files directly from the WordPress media library.
* Blocks can be inserted, reordered by drag and drop, duplicated, removed, aligned, and edited without leaving the visual email canvas.
* Added a dedicated Newsletter settings tab for sender details, company and legal data, logo, colors, typography, email width, and a fixed footer.
* The company footer is locked in the template editor and automatically includes configured legal links and the unsubscribe link.
* WooCommerce email colors, sender details, header image, and footer text can be copied into ContactPanda without creating a permanent WooCommerce dependency.
* Existing 0.9.0 templates remain readable and receive the extended block properties when next saved.
* Hidden shipping-address fields are disabled and never remain required; copied billing values are cleared when a customer switches to a genuinely separate shipping address.

= 0.9.0 =
* Groundhogg tags are now read page by page without the former fixed limit; regular tags are no longer hidden merely because their names match WordPress roles.
* The repeatable migration maps every readable tag and assignment, omits only genuine internal Groundhogg status tags, and records source/target counts plus migration warnings.
* Tag management now follows the WordPress taxonomy layout with an add/edit panel, searchable table, row actions, bulk deletion, assignment counts, and pagination.
* Added an independent ContactPanda newsletter-template data model using the `cp_newsletter_templates` table; it does not depend on the selected contact data source.
* Added newsletter template management with drafts, duplication, archiving, search, and pagination.
* Added a responsive block editor for headings, text, images, buttons, dividers, and spacing, including ordering and removal controls.
* Added subject, preview text, sender name, reply-to address, personalization placeholders, and an automatic unsubscribe placeholder.
* Added desktop/mobile preview with a real test-contact representation and test delivery through the configured WordPress mail system.
* WooCommerce address AJAX saving now re-reads and compares every persisted address field before showing a success message.
* The active My Account menu item now prioritizes the theme's primary/hover accent color instead of a potentially white palette variable.

= 0.8.6 =
* Korrektur: Rechnungs- und Lieferadressen werden zuverlässig über WooCommerce und dessen zugrunde liegende Benutzerfelder gespeichert; eine unveränderte, bereits bestätigte UID löst keine unnötige erneute VIES-Abfrage aus.
* Ein eigener Menüpunkt „Tags“ steht direkt vor „Einstellungen“ bereit und arbeitet mit der aktuell gewählten Datenquelle.
* Die Tag-Verwaltung bietet Suche, Neuanlage, Umbenennen, Zuordnungszahlen und geschütztes Löschen samt bestehender Zuordnungen.
* Die Adresse in den Personen-Stammdaten ist über ein Bearbeiten-Symbol zugänglich und öffnet die strukturierte WooCommerce-Rechnungsadresse, ohne Daten zu duplizieren.
* Beim Verknüpfen eines WordPress-Kontos wird je nach Datenquelle korrekt „ContactPanda-Person“ oder „Groundhogg-Kontakt“ angezeigt.
* Im Kundenkonto steht die optionale Firma vor dem Namen der Rechnungsadresse und die UID-Prüfung vor der abweichenden Lieferadresse.
* Fremde doppelte UID-Felder werden im ContactPanda-Adressbereich gezielt ausgeblendet.
* Der aktive Menüpunkt unter „Mein Konto“ übernimmt die allgemeine Akzentfarbe des aktiven Themes.
* Die bestehende cp_activities-Struktur bleibt die Grundlage für die als nächsten Ausbau geplanten Notizen und weiteren Aktivitäten.

= 0.8.5 =
* „Mein Profil“ und WooCommerce-Rechnungs-/Lieferadressen speichern per AJAX mit Rückmeldung im Formular.
* Die Rechnungsadresse enthält ein einziges ContactPanda-UID-Feld; angegebene UID-Nummern werden vor dem Speichern verpflichtend über EU VIES geprüft und positiv markiert.
* Die Personendetailansicht zeigt die Adresse in den Stammdaten, eine reine E-Mail-Anzeige mit separatem Mail-Symbol und den Double-Opt-in-Status.
* Die Tabs lauten und folgen nun: Bestellungen, Rechnungs- und Lieferadresse, Events (wenn verfügbar), Broadcasts und Aktivitäten.
* Tag-Zuweisung, Entfernung und Neuanlage besitzen für Groundhogg eine direkte, kompatible API-Rückfallebene.
* Telefonnummern verwenden auch im Registrierungsformular getrennte europäische Ländervorwahl und einheitliche österreichische Abstände.
* Doppelte UID-Felder anderer Erweiterungen und der entbehrliche Dashboard-Text „ÜBERSICHTS-BEREICH“ werden nur in den ContactPanda-Ansichten ausgeblendet.

= 0.8.4 =
* Das kompakte Newsletterformular enthält nun Anrede, Vorname, Nachname, E-Mail-Adresse, Datenschutzbestätigung und ausdrückliche Newsletter-Einwilligung.
* Beschriftungen, Einwilligungstext, Schaltfläche sowie Erfolgs- und Fehlermeldungen des Newsletterformulars können in den Einstellungen bearbeitet werden.
* Bereits wirksam angemeldete Adressen erhalten keine weitere Double-Opt-in-E-Mail; die erneute Anmeldung wird nachvollziehbar protokolliert.
* ContactPanda Plus kann das kompakte Formular über eine stabile Erweiterungsschnittstelle durch ein veröffentlichtes, wiederverwendbares Formular ersetzen.
* Der leere Tag-Bereich in der Personendetailansicht wurde korrigiert; Hinweis und Eingabefeld überlagern sich nicht mehr.
* Die WooCommerce-Seite „Mein Konto“ zeigt Gästen zunächst nur die Anmeldung und öffnet das vollständige ContactPanda-Registrierungsformular erst über „Jetzt registrieren“.
* Das Kundenmenü beginnt mit Dashboard und „Mein Profil“, gefolgt von Adressen, Zahlungsarten, zusätzlichen Erweiterungsbereichen und Abmelden.
* Das Dashboard zeigt Bestellanzahl, Bestellsumme, bis zu fünf letzte Bestellungen und – bei vorhandener Event-Tickets-Integration – die Zahl tatsächlich besuchter Veranstaltungen.
* „Mein Profil“ ergänzt vor- und nachgestellten Titel, Telefonnummer und eine optionale Firma; die WooCommerce-Passwortänderung bleibt erhalten.
* Unter „Allgemeines“ wird das aktive Theme angezeigt. Ein geschützter Button aktiviert die Übernahme kompatibler Typografie-, Farb-, Formular-, Button-, Rahmen- und Rundungsstile.
* Das Dashboard ist kompakter und enthält wieder einen erklärenden Text mit direkten Links zu Profil, Adressen, Zahlungsarten und Passwortverwaltung.
* „Mein Profil“ besitzt eine feste Überschrift und ordnet Vor-/Nachname, vor-/nachgestellte Titel, die europäische Telefonnummer sowie die persönliche Adresse in Zweierreihen an.
* Die Telefonnummer verwendet eine getrennte europäische Ländervorwahl, erkennt eingegebene internationale Vorwahlen und formatiert österreichische Mobil- und Ortsvorwahlen.
* Die Passwortänderung wurde aus dem Profil entfernt und als eigener Menüpunkt umgesetzt.
* Die Rechnungsadresse kann auf Wunsch aus der Profiladresse übernommen werden. Eine gesonderte Lieferadresse bleibt verborgen, bis sie ausdrücklich als abweichend markiert wird.

= 0.8.3 =
* Protected extension update checks no longer retain a negative “no update available” response, so a newly published release can be discovered on the next WordPress refresh.
* “Check again”, license refreshes, and completed plugin updates now clear both ContactPanda metadata and WordPress' combined plugin-update cache.
* Der Firmenbereich im Registrierungsformular erscheint zunächst als einfache Zeile „Firmendaten anlegen“ mit großem Plus-/Minus-Symbol und öffnet die Felder erst auf Klick.
* Die gesonderte Lieferadresse verwendet denselben Aufbau; ohne Öffnen des Bereichs gilt weiterhin die Rechnungsadresse.
* Die gewünschte Kundenrolle wird als Dropdown angezeigt und weiterhin erst nach Double-Opt-in und administrativer Freigabe zugewiesen.
* Administratoren können die vorbereiteten cp_-Tabellen nun ausdrücklich als ContactPanda-Testdatenbank anlegen und aktivieren.
* Im Testdatenbankmodus werden Personen, Firmen, Tags, Zustimmungen und Aktivitäten in ContactPanda gelesen und gespeichert; Groundhogg bleibt unverändert.
* Kontakte, Firmen, Firmenbeziehungen und Tags können zu Testzwecken aus Groundhogg übernommen werden. Wiederholte Importe aktualisieren zugeordnete Kontakte und Firmen, statt sie anhand ihrer Groundhogg-ID erneut anzulegen.
* Die Einstellungsseite zeigt Tabellenstände und ein Protokoll des letzten Imports.
* Eine zweifach bestätigte Rücksetzfunktion leert ausschließlich die ContactPanda-eigenen cp_-Tabellen. Danach kann die Groundhogg-Testmigration erneut ausgeführt werden.

= 0.8.2 =
* Die Einstellungen sind in die Tabs „Allgemeines“ und „Kunden-Registrierung“ gegliedert; weitere Bereiche können später ergänzt werden.
* Unter „Allgemeines“ wird Groundhogg als führende Datenquelle angezeigt, sofern es aktiv ist. Eine Deaktivierung führt niemals zu einem automatischen Wechsel der Datenquelle.
* Die künftige Standalone-Datenstruktur mit dem Präfix cp_ ist vorbereitet, bleibt jedoch bis zur Fertigstellung der Datenzugriffsschicht und des Migrationsassistenten gesperrt.
* ContactPanda legt in dieser Version keine Standalone-Kundentabellen automatisch an. Bestehende Tabellen werden vor einer späteren expliziten Einrichtung oder Aktualisierung geprüft.
* Erkannte Integrationen werden samt Status „aktiv“ beziehungsweise „installiert, inaktiv“ angezeigt; ihre Daten werden bei einer ContactPanda-Deinstallation ausdrücklich nicht verändert.
* Kundendaten bleiben bei einer Deinstallation standardmäßig erhalten. Die Löschung ausschließlich ContactPanda-eigener Daten muss vorab ausdrücklich aktiviert und bestätigt werden.
* Firmen- und Lieferadressbereiche verwenden Plus und Minus statt eines Dreiecks; die Lieferadresse bleibt geschlossen, bis eine abweichende Adresse gewählt wird.

= 0.8.1 =
* Customers can choose their own unique WordPress username during registration.
* Company details are optional and shown in a collapsed section; form section headings now use the active theme's h3 styling inside each panel.
* Administrators can select which non-privileged WordPress roles customers may request. A requested role is never assigned automatically and requires both email confirmation and administrator approval.
* Pending registrations show the requested role and safely prevent approval if the role is no longer allowed or the email address is unconfirmed.
* Every entered VAT ID is validated through EU VIES on the form and again securely on submission; successful verification is stored on the new account.
* The optional shipping address remains collapsed until the customer explicitly indicates that it differs from the billing address.
* Administrators can edit the submission message, double-opt-in subject and message, confirmation message, redirect page and redirect delay.
* Double-opt-in messages support the placeholders {first_name}, {site_name} and {confirmation_link}; a missing confirmation link is appended automatically.

= 0.8.0 =
* Added a dedicated Settings page for the configurable business-customer registration workflow.
* Added the public [contactpanda_registration] shortcode with account, contact, company, billing, shipping, VAT ID and consent fields.
* Added separate email confirmation, optional marketing double opt-in and administrator approval states.
* Added a protected pending-registration list with approve, reject and resend-confirmation actions.
* Approved registrations receive only the administrator-configured non-administrator WordPress role; visitors can never choose roles, B2B groups, discounts or tax privileges.
* Registration data is mapped to the WordPress account and the existing WooCommerce, German Market and B2B Market profile fields without changing historical orders or invoices.
* Added configurable privacy, terms, success-page and notification settings plus integration diagnostics.
* The selected ContactPanda registration page can optionally replace the public standard WordPress registration while preserving the administrative login.

= 0.7.2 =
* List controls use a light grey default appearance and a dark grey hover state; green and red status indicators keep their semantic colours.
* All table cells are vertically centred.
* Email confirmation and marketing status are grouped closely in one column.
* Tag suggestions are restricted to Groundhogg tags and a selected tag is saved immediately without an additional Add button.
* WordPress roles use a separate field and an independent suggestion list containing roles only.
* Permanent tag deletion was removed from the person detail view; removing a tag from an individual contact remains available.
* Tags now have their own unbranded contact section; Groundhogg's automatically mirrored WordPress-role and opt-in-status tags are hidden there so roles and user-managed tags remain visibly separate.

= 0.7.1 =
* Stift und Auge stehen bei Personen in einer eigenen Aktionsspalte direkt nach Vor- und Nachname; beide besitzen einen mittelgrauen Hintergrund und weiße Symbole.
* Eine zusätzliche mittelgraue Statusanzeige vor dem Marketable-Symbol zeigt den aktuellen Groundhogg-Bestätigungsstatus der E-Mail-Adresse als Häkchen oder Querstrich.
* Das PDF-Symbol der Bestellliste erscheint rahmenlos direkt neben dem Auge in derselben Aktionsspalte.
* Die Broadcast-Liste ist kompakter und wird mit höchstens 20 Einträgen pro Seite sowie Vor-/Zurück-Navigation angezeigt.
* Groundhogg-Tags lassen sich in einem gemeinsamen Suchfeld mit Vorschlägen finden, neu erstellen, zuweisen und direkt als Tag-Chip von der Person entfernen.
* Das dauerhafte Löschen eines Groundhogg-Tags bleibt als getrennte, ausdrücklich zu bestätigende Aktion erhalten.
* WordPress-Rollen werden für Administratoren ebenfalls als durchsuchbare Mehrfachauswahl mit direkt entfernbaren Rollen-Chips verwaltet; die bestehenden Schutzregeln bleiben aktiv.

= 0.7.0 =
* Pluginname, Hauptmenü, technischer Plugin-Ordner, Hauptdatei, Seitenschlüssel, Textdomain und interne Präfixe wurden vollständig auf ContactPanda umgestellt.
* ContactPanda wird im ersten Block des WordPress-Adminmenüs unmittelbar nach Avada angezeigt.
* Ist Avada nicht aktiv, erscheint ContactPanda ersatzweise am Ende des ersten Adminmenüblocks.
* In der Personenliste ersetzen ein Stift- und ein Auge-Symbol die bisherigen Textlinks „Bearbeiten“ und „Ansehen“; zugängliche Beschriftungen und Hinweise bleiben erhalten.

= 0.6.6 =
* Das Veranstaltungs-Pop-up besitzt nun zwei Spalten: links kompakte Termins-, Orts- und Ticketdaten, rechts Bild, Titel, Kurz- und Langinfo.
* Beginn und Ende werden getrennt angezeigt; Veranstaltungsadressen werden aus strukturierten Feldern aufgebaut, sodass kein HTML-Code mehr als Text erscheint.
* Verkaufte und verfügbare Tickets werden gemeinsam mit der prozentuellen Auslastung dargestellt; unbegrenzte Kontingente werden eindeutig bezeichnet.
* Augen-Symbole erscheinen ohne Button-Rahmen und stehen in der Veranstaltungsliste direkt nach dem Titel; ihre Spaltenüberschrift ist nur für Screenreader vorhanden.
* Bestelltabellen verwenden die Reihenfolge Bestellnummer, Datum, Rechnungsnummer, Aktionen, Status, Zahlart und Summe.
* Unter der Summenspalte wird die Gesamtsumme der angezeigten Bestellungen ausgegeben; unterschiedliche Währungen werden getrennt summiert.
* In der Personenliste stehen „Bearbeiten“ und „Anzeigen“ nun unmittelbar unter dem Nachnamen.

= 0.6.5 =
* ContactPanda und erster Unterpunkt öffnen jetzt standardmäßig die Personenverwaltung; Firmen stehen – sofern Groundhogg Companies aktiv ist – an zweiter Stelle.
* Bestellungen zeigen Rechnungsnummer und Zahlart sowie kompakte Aktionssymbole.
* Das Auge öffnet Bestellpositionen, Adressen, Status, Summen und Zahlungsart in einem WooCommerce-orientierten Pop-up.
* Das Dokumentsymbol übernimmt die vorhandene German-Market-Adminaktion und öffnet das echte Rechnungs-PDF im Pop-up; der fehleranfällige Kundenkonto-Link wurde entfernt.
* Veranstaltungszeilen besitzen eine Vorschau mit Datum, Ort, Kurzinfo, Bild sowie gebuchten und verfügbaren Tickets.
* WordPress-Rollen werden ausschließlich Administratoren angezeigt und können als Mehrfachzuordnung sicher bearbeitet werden.
* Administratoren können ein WordPress-Konto mit sicherem Zufallspasswort und optionaler Einladung anlegen oder ein bereits unter derselben E-Mail vorhandenes Konto kontrolliert verknüpfen.
* Rollen- und Kontoverknüpfungsänderungen werden am Groundhogg-Kontakt protokolliert; eine Marketingzustimmung wird niemals automatisch gesetzt.

= 0.6.0 =
* Die Zahl der besuchten Veranstaltungen kann in der Personenliste auf- und absteigend sortiert werden.
* Vorname und Nachname werden wieder in getrennten Spalten angezeigt; „Bearbeiten“ und „Anzeigen“ stehen gemeinsam darunter über beide Namensspalten.
* German-Market-Rechnungen werden in einem Pop-up mit zusätzlicher Ausweichmöglichkeit für ein neues Browserfenster geöffnet.
* Neue Personen können direkt aus der Übersicht als unbestätigte Groundhogg-Kontakte angelegt werden; vorhandene E-Mail-Adressen werden nicht überschrieben.
* Groundhogg-Tags können bei einer Person zugewiesen, entfernt sowie neu erstellt werden.
* Tags können nach ausdrücklicher Warnung dauerhaft gelöscht werden; Groundhogg-Status-Tags sind vor dem Löschen geschützt.
* Neuer Tab „Broadcasts“ mit Versandzeitpunkt, Öffnungs- und Klickstatus sowie kontaktbezogener E-Mail-Vorschau im Pop-up.
* Die Feldabstände im linken Stammdatenblock wurden verkleinert.

= 0.5.0 =
* Auswahlkästchen und eine Massenbearbeitung für die sichtbaren Personen ergänzt.
* „Marketable aktivieren“ kann für bis zu 100 ausgewählte Kontakte gemeinsam ausgeführt werden.
* Vor der Änderung müssen Herkunft, Datum und die ausdrückliche Zustimmung für jede ausgewählte Person bestätigt werden.
* Bereits marketable Kontakte bleiben unverändert; Abmeldungen, unzustellbare Adressen, Spam-, Beschwerde- und Sperrstatus werden automatisch geschützt und ausgelassen.
* Jede erfolgreiche Änderung wird mit Datum, Herkunft, Bearbeiter und optionaler Notiz am Groundhogg-Kontakt protokolliert.

= 0.4.9 =
* Die Spaltenüberschriften „Bestellungen“, „Bestellsumme“ und „Besuchte Veranstaltungen“ wurden zu „Best.“, „Summe“ und „Events“ verkürzt.
* Vor- und Nachname erscheinen gemeinsam und ohne Zeilenumbruch; die Aktionen „Bearbeiten“ und „Anzeigen“ stehen zusammen darunter.
* Der zusätzliche Filter „Event-Besucher“ zeigt ausschließlich Personen mit mindestens einer tatsächlich eingecheckten Veranstaltung.

= 0.4.8 =
* Optionale Integration von The Events Calendar und Event Tickets Plus ergänzt.
* Die Personenliste zeigt die Anzahl eindeutig besuchter Veranstaltungen anhand tatsächlich erfasster Check-ins.
* Mehrere Tickets derselben Person für dieselbe Veranstaltung werden nur einmal gezählt.
* Die Personendetailansicht erhält einen Tab „Veranstaltungen“ mit Termin, Buchungsstatus und Verknüpfung zur WooCommerce-Bestellung.
* Gebuchte und tatsächlich besuchte Veranstaltungen werden klar unterschieden.
* Fehlen die Veranstaltungs-Plugins, werden Spalte und Tab automatisch ausgeblendet.

= 0.4.7 =
* Groundhogg Companies ist für die Personenverwaltung keine zwingende Voraussetzung mehr.
* Fehlt die Companies-Erweiterung, werden Firmenmenü, Firmenspalte, Firmensortierung und Firmenblock automatisch deaktiviert.
* Personen, Marketingzustimmungen, WooCommerce-Adressen, Bestellungen, Umsätze, Rechnungslinks und Aktivitäten bleiben weiterhin verfügbar.
* Firmen-Schreibzugriffe werden bei fehlender Companies-Erweiterung sicher abgewiesen.

= 0.4.6 =
* Filter „Alle“ und „Besteller“ sowie die Sortierung der Personenliste repariert.
* Groundhogg-Kontakte werden für Filter und Sortierung verlässlich in unterstützten Teilmengen geladen.
* Bestellungen werden gemeinsam und seitenweise über die offizielle WooCommerce-Abfrage ausgewertet, statt eine vollständige Einzelabfrage je Person auszuführen.
* Zuordnung zuerst über die verknüpfte WooCommerce-Kundennummer und zusätzlich über Groundhogg-, Benutzer- und Rechnungs-E-Mail.
* Mehrfach gefundene Bestellungen werden anhand ihrer Bestell-ID entdoppelt; Rückerstattungen werden von der Bestellsumme abgezogen.
* Die sichtbare Überschrift der Marketingstatus-Spalte wurde entfernt; eine Bezeichnung für Screenreader bleibt erhalten.

= 0.4.5 =
* Personen können neben der Namenssuche mit „Alle“ oder „Besteller“ gefiltert werden.
* Vorname, Nachname, Firma und Bestellsumme sind auf- und absteigend sortierbar.
* Der Marketingstatus erscheint in der Personenliste kompakt als grüner Daumen hoch beziehungsweise roter Daumen runter; der genaue Status bleibt als barrierearme Beschriftung erhalten.
* Die Firmenübersicht besitzt eine Schnellsuche nach Firmennamen.
* Auch bei Firmen ersetzen „Bearbeiten“ und „Anzeigen“ den bisherigen Öffnen-Button.
* „Anzeigen“ öffnet eine schreibgeschützte Firmenansicht.

= 0.4.2 =
* Marketing und Zustimmung in der Personenansicht in die linke Spalte vor den Firmenblock verschoben.
* WooCommerce-Adressen, Bestellungen und Rechnungen sowie Aktivitäten in einem gemeinsamen, barrierearm bedienbaren Tab-Block zusammengefasst.
* Personenübersicht um Bestellanzahl und Bestellsumme ergänzt; stornierte, fehlgeschlagene und Checkout-Entwürfe werden nicht mitgezählt.
* Der bisherige Öffnen-Button wurde durch die WordPress-typischen Zeilenaktionen „Bearbeiten“ und „Anzeigen“ ersetzt.
* „Anzeigen“ öffnet eine schreibgeschützte Personenansicht; über „Bearbeiten“ kann in den Bearbeitungsmodus gewechselt werden.

= 0.4.1 =
* Telefonnummern bei Firmen, Personen und WooCommerce-Rechnungsadressen in europäische Ländervorwahl und nationale Nummer aufgeteilt.
* Österreich ist vorausgewählt; eingegebene internationale Vorwahlen werden automatisch erkannt.
* Österreichische Mobilfunk- und Ortsvorwahlen werden zur Anzeige lesbar abgesetzt, während ein einheitlicher Wert gespeichert bleibt.
* WooCommerce-Rechnungs- und Lieferadressen können im Personenkonto angelegt und bearbeitet werden.
* Adressänderungen betreffen ausschließlich das WooCommerce-Kundenprofil und niemals bestehende Bestellungen oder Rechnungen.

= 0.4.0 =
* Personenübersicht mit Suche, Seitennavigation, Firmenzuordnung und Groundhogg-Marketingstatus ergänzt.
* Personen-Detailansicht mit bearbeitbaren Stammdaten, Firmenlink, UID, Bestellungen, Rechnungen, Adressen, Tags und Kontaktnotizen ergänzt.
* Marketingzustimmung kann nach ausdrücklicher Bestätigung manuell erteilt oder widerrufen werden.
* Die Änderung setzt den bestehenden Groundhogg-Opt-in-Status und wird mit Datum, Herkunft, Bearbeiter und optionaler Notiz protokolliert.
* Erfolgreich über EU-VIES geprüfte UID-Nummern erhalten ein dauerhaftes grünes Häkchen.

= 0.3.0 =
* UID-Feld mit den vorhandenen Benutzerfeldern von German Market (`billing_vat`) und B2B Market (`b2b_uid`) verknüpft.
* Beide vorhandenen Felder werden beim Speichern synchron gehalten; es wird keine dritte UID-Kopie angelegt.
* Manuelle UID-Prüfung über den offiziellen EU-VIES-Dienst ergänzt.

= 0.2.0 =
* Firmenname, Website, Telefon, Branche, Firmenadresse und Beschreibung können direkt bearbeitet werden.
* Sichere Speicherung über die offizielle Groundhogg-Companies-Ability mit Berechtigungs- und Nonce-Prüfung.
* Rechnungsdaten bleiben zum Schutz historischer Belege schreibgeschützt.

= 0.1.1 =
* Firmen, Metadaten und Ansprechpartner werden über die offizielle WordPress-Abilities-Schnittstelle von Groundhogg Companies 3.5.0 gelesen.
* Bestellungen mehrerer Ansprechpartner werden zusammengeführt.
* Firmennotizen erscheinen im Aktivitätenbereich.

== Sicherheit und Datenhaltung ==

* Im Groundhogg-Modus werden keine eigenen Kundentabellen angelegt; die vorbereiteten cp_-Tabellen entstehen erst durch eine spätere ausdrücklich gestartete Standalone-Einrichtung oder Migration.
* Keine Änderung an Groundhogg-Flows.
* Schreibzugriff ausschließlich auf freigegebene Firmen- und Personenfelder, den Groundhogg-Opt-in-Status sowie vorhandene UID-Felder.
* Bestellungen werden über die WooCommerce-API gelesen.
* Rechnungen werden über die bestehende German-Market-Adminaktion geöffnet.
* Historische Rechnungen werden weder neu erzeugt noch verändert.
* Veranstaltungsdaten und Check-ins werden ausschließlich gelesen und nicht kopiert oder verändert.

== Installation ==

Die ZIP-Datei über Plugins > Installieren > Plugin hochladen einspielen und zunächst in einer kontrollierten Umgebung prüfen.

