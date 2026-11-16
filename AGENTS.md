# ContactPanda Basic repository instructions

## Scope

This repository contains the free ContactPanda Basic WordPress plugin in `contactpanda/`.

Do not edit `work/`, `outputs/`, or generated release ZIP files. Those paths are intentionally excluded from version control.

## Product invariants

- Do not change historical WooCommerce orders, German Market invoices, or source-system records during imports.
- Groundhogg mode and standalone ContactPanda mode must both remain supported.
- Migrations must be non-destructive, repeatable, and deduplicated by stable source identifiers.
- Destructive operations require administrator capability checks, nonces, explicit confirmation, and a recoverable path where practical.
- Do not embed customer data, site credentials, API keys, email lists, or production database dumps in the repository or tests.
- Use sanitized fixtures for tests.

## Compatibility

- Keep ContactPanda Basic fully usable without ContactPanda Plus.
- Preserve stable extension points used by supported ContactPanda Plus versions.
- Optional integrations must feature-detect their dependencies and disappear cleanly when unavailable.
- Never assume the default `wp_` table prefix.

## Internationalization and presentation

## Shared button and icon rules

- Every visible action button uses the centrally defined, semantically matching icon to the left of its label; plain text buttons are reserved for subordinate text links.
- Primary and active actions use background #5c5c5c with white label and icon. Secondary actions use a white or neutral-grey background, grey label and icon, and a visible grey border.
- Use the same family-wide icon for the same action: create = plus, save draft = save/edit, publish = megaphone, validate/test = tool, preview = eye, back = left arrow, delete = red trash, duplicate = two documents.
- Icon and label stay on one line, vertically centred, with a consistent gap. Related buttons and adjacent fields have equal heights and aligned baselines.
- Hover, keyboard focus, loading, disabled, active, and destructive states must remain clearly visible and accessible.
- New-record and back-to-overview/list/template buttons sit at the top-right edge of the content area, with their bottom edge aligned to the introductory subtitle on the left—not beside the page title or floating in empty space.
- These rules apply to every list, detail screen, form, setting, editor, dialog, and automation; do not add local exceptions.


- Source strings are English and use the `contactpanda` text domain.
- Update the POT and German PO/MO files when user-facing strings change.
- Keep CSS in the existing dedicated stylesheets; avoid large inline style blocks.
- Reuse the ContactPanda admin design system and accessible WordPress controls.

## Verification

Before handing off a change:

1. Run `./tools/check.sh`.
2. Test database upgrades from the previous released schema when schema code changes.
3. Test both standalone and Groundhogg data-source modes for affected features.
4. Test with optional dependencies absent as well as active.
5. Build release ZIPs only when the user explicitly requests a release or version creation.

## Versioning and live sites

- Do not change plugin version numbers without explicit user approval.
- Do not install or modify a live WordPress site without explicit user approval for that deployment.
- Release artifacts are generated, not committed.

