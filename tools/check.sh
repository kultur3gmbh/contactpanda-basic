#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

status=0
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! php -l "$file" >/dev/null; then
      status=1
    fi
  done < <(find contactpanda tests -type f -name '*.php' -print0)
  php -l tools/github/class-contactpanda-github-updater.php >/dev/null || status=1
  php tests/request-guards.php || status=1
  php tests/db-query-safety.php || status=1
  php tests/external-services.php || status=1
  php tests/import-review.php || status=1
  php tests/trash.php || status=1
  php tests/public-updater.php || status=1
  php tests/woocommerce-import.php || status=1
	php tests/order-summary-query.php || status=1
  php tests/groundhogg-migration-source.php || status=1
  php tests/marketing-status-icons.php || status=1
	php tests/events.php || status=1
	php tests/events-current-api.php || status=1
	php tests/events-unavailable.php || status=1
	php tests/flow-hooks.php || status=1
	php tests/release-050.php || status=1
	php tests/release-060.php || status=1
	php tests/admin-ui-corrections.php || status=1
	php tests/release-078.php || status=1
	php tests/person-detail-extensions.php || status=1
	php tests/newsletter-form.php || status=1
	php tests/newsletter-personalization.php || status=1
	php tests/customer-portal.php || status=1
	php tests/migration-sources.php || status=1
	php tests/identity-store.php || status=1
	php tests/release-063.php || status=1
	php tests/release-064.php || status=1
elif command -v python3 >/dev/null 2>&1; then
  echo "PHP CLI is unavailable; running the offline structural fallback."
  python3 tools/check_php_structure.py contactpanda tests || status=1
	echo "SKIPPED: PHP trash, WooCommerce import and order summary, Groundhogg migration, marketing status icon, event-adapter, release contract, and administrator UI behavior tests require PHP CLI."
else
  echo "PHP or Python 3 is required for source validation." >&2
  status=1
fi

if command -v python3 >/dev/null 2>&1; then
	python3 tests/compile-mo.py || status=1
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! node --check "$file" >/dev/null; then
      status=1
    fi
  done < <(find contactpanda -type f -name '*.js' -print0)
fi

basic_header="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contactpanda/contactpanda.php)"
basic_constant="$(sed -n "s/^define( 'CONTACTPANDA_VERSION', '\([^']*\)' );/\1/p" contactpanda/contactpanda.php | head -1)"
basic_stable="$(awk -F': ' '/^Stable tag:/{print $2; exit}' contactpanda/readme.txt)"

if [[ -z "$basic_header" || "$basic_header" != "$basic_constant" || "$basic_header" != "$basic_stable" ]]; then
  echo "ContactPanda Basic header, version constant, and stable tag do not match." >&2
  status=1
fi

if ! grep -q "Text Domain: contactpanda$" contactpanda/contactpanda.php; then
  echo "ContactPanda Basic text domain is missing or unexpected." >&2
  status=1
fi

for translation_file in \
  contactpanda/languages/contactpanda-de_DE.po \
  contactpanda/languages/contactpanda-de_DE.mo \
  contactpanda/languages/contactpanda-de_DE_formal.po \
  contactpanda/languages/contactpanda-de_DE_formal.mo; do
  if [[ ! -f "$translation_file" ]]; then
    echo "Missing German translation file: $translation_file" >&2
    status=1
  fi
done

if [[ -e contactpanda/includes/class-contactpanda-woocommerce.php || -e contactpanda/assets/contactpanda-checkout.css ]]; then
  echo "WooCommerce cart and checkout presentation belongs to ContactPanda Plus, not Basic." >&2
  status=1
fi

if grep -Rq "api.github.com/repos\|pre_set_site_transient_update_plugins\|ContactPanda_License\|ContactPanda_Licensed_Updater" contactpanda --include='*.php'; then
	echo "The WordPress.org Basic package must not contain an external updater or paid licensing client." >&2
	status=1
fi

if [[ -e contactpanda/assets/contactpanda-system-emails.js ]] || grep -Rq "contactpanda_save_system_email_template\|contactpanda_test_system_email_template" contactpanda --include='*.php'; then
	echo "The paid visual system-email composer belongs to ContactPanda Plus, not Basic." >&2
	status=1
fi

for portal_contract in \
	"class-contactpanda-identity.php" \
	"class-contactpanda-customer-portal.php" \
	"contactpanda_customer_portal_tabs" \
	"contactpanda_portal_profile_saved" \
	"contactpanda_portal', '1'" \
	"render_virtual_portal"; do
	if ! grep -Rqs "$portal_contract" contactpanda; then
		echo "Missing customer portal contract: $portal_contract" >&2
		status=1
	fi
done

for registration_contract in \
	"add_shortcode( 'contactpanda_registration'" \
	"public static function public_registration_url" \
	"contactpanda/customer_registration_created" \
	"contactpanda/business_registration_created"; do
	if ! grep -Fqs "$registration_contract" contactpanda/includes/class-contactpanda-registration.php; then
		echo "Missing Basic registration contract: $registration_contract" >&2
		status=1
	fi
done

if ! grep -A2 -F "function customer_registration_enabled()" contactpanda/includes/class-contactpanda-registration.php | grep -Fq "return true;"; then
	echo "Customer and business registration must remain available in ContactPanda Basic." >&2
	status=1
fi

for vat_contract in \
	"function contactpanda_get_person_vat_id" \
	"function contactpanda_get_company_vat_id" \
	"function contactpanda_get_user_vat_id" \
	"contactpanda_person_vat_id" \
	"contactpanda_company_vat_id" \
	"contactpanda_vat_id_updated" \
	"migrate_legacy_vat_ids"; do
	if ! grep -Rqs "$vat_contract" contactpanda; then
		echo "Missing canonical VAT ownership contract: $vat_contract" >&2
		status=1
	fi
done

if grep -A80 -F "public static function sync_registration" contactpanda/includes/class-contactpanda-store.php | grep -Fq "create_company( array( 'name' => ''"; then
	echo "A VAT ID must never create an empty company record." >&2
	status=1
fi

if [[ "$status" -ne 0 ]]; then
  exit "$status"
fi

echo "ContactPanda Basic checks passed."


