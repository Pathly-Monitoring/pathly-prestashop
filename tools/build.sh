#!/usr/bin/env bash
# Builds build/pathly-<version>.zip, the archive installed in PrestaShop
# (Modules > Upload a module) and sent to PrestaShop Addons.
# Needs: composer, zip (or python3).
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(sed -n "s/.*\$this->version = '\([0-9.]*\)'.*/\1/p" "$root/pathly.php")"
out="$root/build"
dir="$out/pathly"

rm -rf "$dir" "$out"/pathly-*.zip "$out/pathly.zip"
mkdir -p "$dir"
cp -r "$root"/{pathly.php,logo.png,composer.json,LICENSE.md,src,views,translations,docs} "$dir/"
cp "$root/tools/index.php.txt" "$dir/index.php"
(cd "$dir" && ${COMPOSER:-composer} install --no-dev --optimize-autoloader --no-interaction --quiet)
rm -f "$dir/composer.lock"

# PrestaShop asks for an index.php in every folder: it blocks the listing of the files.
find "$dir" -mindepth 1 -type d | while read -r folder; do
  [ -f "$folder/index.php" ] || cp "$root/tools/index.php.txt" "$folder/index.php"
done

cd "$out"
if command -v zip >/dev/null 2>&1; then
  zip -qr "pathly-$version.zip" pathly
else
  python3 -c "import shutil; shutil.make_archive('pathly-$version', 'zip', '.', 'pathly')"
fi
# Same archive under a fixed name: PrestaShop Addons wants the module name,
# and https://github.com/Pathly-Monitoring/pathly-prestashop/releases/latest/download/pathly.zip stays stable.
cp "pathly-$version.zip" pathly.zip
echo "$out/pathly-$version.zip"
