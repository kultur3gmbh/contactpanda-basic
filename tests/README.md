# Tests

Use sanitized fixtures only. Production customer data must never be copied here. Run `./tools/check.sh` before handoff.

With PHP CLI, the check runs `tests/trash.php` for both record types, soft deletion and restoration, preserved relationships and source tables, permanent deletion restricted to Trash, administrator/mode/confirmation guards, malformed requests, transactional rollback, non-default table prefixes, and retained event/activity records.

The focused WooCommerce import and customer-filtered order-summary tests, Groundhogg migration-source test, and marketing-status-icon tests verify the protected integration and list-display branches with sanitized fixtures.

`release-050.php` protects the 0.5.0 administration contracts for Import/Export, Groundhogg data-source selection, and multilingual salutation libraries.

`release-060.php` protects the durable Basic newsletter entitlement, shared newsletter schema, module bootstrap, and update-period separation introduced in 0.6.0.

The vCard parser behavior is tested in the ContactPanda Plus repository, which owns the complete vCard import and export implementation. `wordpress-update-integration.php` is deliberately blocked unless it receives a disposable local WordPress installation containing a `.contactpanda-isolated-test` marker; see `docs/isolated-update-test.md`.

Without PHP, the script runs its structural fallback and explicitly reports the skipped behavior tests. Structural validation is not PHP execution or a WordPress integration test. The next test milestone remains a disposable WordPress environment covering installation, standalone and Groundhogg data-source modes, and optional dependencies both absent and active.

