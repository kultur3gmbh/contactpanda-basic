#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Release packages must be built from a Git repository." >&2
  exit 1
fi

if ! git diff --quiet -- . || ! git diff --cached --quiet -- .; then
  echo "Commit all tracked changes before building a release." >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "The zip command is required." >&2
  exit 1
fi

basic_version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' contactpanda/contactpanda.php)"
basic_requires="$(awk -F': ' '/^ \* Requires at least:/{print $2; exit}' contactpanda/contactpanda.php)"
basic_requires_php="$(awk -F': ' '/^ \* Requires PHP:/{print $2; exit}' contactpanda/contactpanda.php)"
basic_readme_tested="$(awk -F': ' '/^Tested up to:/{print $2; exit}' contactpanda/readme.txt)"
basic_tested="${CONTACTPANDA_TESTED_WP:-$basic_readme_tested}"
if [[ -z "$basic_version" || -z "$basic_requires" || -z "$basic_requires_php" || -z "$basic_tested" ]]; then
  echo "Could not read plugin versions." >&2
  exit 1
fi

output_dir="${PANDA_OUTPUT_DIR:-/Users/matthias/Documents/Codex/Outputs}"
mkdir -p "$output_dir"
output_dir="$(cd "$output_dir" && pwd)"
basic_zip="$output_dir/contactpanda-basic-${basic_version}.zip"
wporg_zip="$output_dir/contactpanda-basic-${basic_version}-wordpress-org.zip"
git archive --format=zip --prefix=contactpanda/ HEAD:contactpanda > "$wporg_zip"
python3 - "$wporg_zip" <<'PYCODE'
from pathlib import Path
import io, sys, zipfile
path = Path(sys.argv[1])
with zipfile.ZipFile(io.BytesIO(path.read_bytes())) as source:
    files = {name: source.read(name) for name in source.namelist() if Path(name).name != '.DS_Store'}
with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED) as target:
    for name, data in files.items():
        target.writestr(name, data)
PYCODE

build_dir="$(mktemp -d)"
trap 'rm -rf "$build_dir"' EXIT
unzip -q "$wporg_zip" -d "$build_dir"
cp tools/github/class-contactpanda-github-updater.php "$build_dir/contactpanda/includes/"
(
  cd "$build_dir"
  zip -qr "$build_dir/contactpanda-built.zip" contactpanda
)
mv "$build_dir/contactpanda-built.zip" "$basic_zip"
# WordPress.org language packs are distributed separately; keep catalogs in GitHub ZIPs.
python3 - "$wporg_zip" <<'PYCODE'
from pathlib import Path
import io, sys, zipfile
path = Path(sys.argv[1])
with zipfile.ZipFile(io.BytesIO(path.read_bytes())) as source:
    files = {name: source.read(name) for name in source.namelist() if not name.endswith((".po", ".mo"))}
with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as target:
    for name, data in files.items():
        target.writestr(name, data)
PYCODE
unzip -tq "$basic_zip" >/dev/null
archive_version="$(unzip -p "$basic_zip" contactpanda/contactpanda.php | awk -F': ' '/^ \* Version:/{print $2; exit}')"
if [[ "$archive_version" != "$basic_version" ]]; then
  echo "Release ZIP version mismatch: expected $basic_version, found $archive_version." >&2
  exit 1
fi

if command -v shasum >/dev/null 2>&1; then
  basic_sha256="$(shasum -a 256 "$basic_zip" | awk '{print $1}')"
elif command -v sha256sum >/dev/null 2>&1; then
  basic_sha256="$(sha256sum "$basic_zip" | awk '{print $1}')"
else
  echo "A SHA-256 utility is required." >&2
  exit 1
fi

printf '{\n  "slug": "contactpanda",\n  "version": "%s",\n  "requires": "%s",\n  "requires_php": "%s",\n  "tested": "%s",\n  "sha256": "%s"\n}\n' \
  "$basic_version" "$basic_requires" "$basic_requires_php" "$basic_tested" "$basic_sha256" \
  > "$output_dir/contactpanda-update.json"

echo "Created $basic_zip, $wporg_zip, and dist/contactpanda-update.json"

