#!/usr/bin/env bash
# Integration test on the official PrestaShop image, one version at a time.
#
#   tests/integration/run.sh 8          (1.7.8, 8 or 9)
#
# Starts MariaDB, the simulated Pathly API (as api.pathlyhq.com, with a test
# certificate trusted by the PrestaShop container only) and PrestaShop, then
# installs build/pathly, runs tests/integration/scenario.php, uninstalls, and
# removes everything. Needs docker and a built module (tools/build.sh).
set -euo pipefail
export MSYS_NO_PATHCONV=1

ps_tag="${1:-8}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
work="$root/tests/integration/.work"
net="pathly-it-$$"
db="pathly-it-db-$$"
api="pathly-it-api-$$"
shop="pathly-it-shop-$$"

cleanup() {
  docker rm -f "$shop" "$api" "$db" >/dev/null 2>&1 || true
  docker network rm "$net" >/dev/null 2>&1 || true
}
[ "${PATHLY_IT_KEEP:-0}" = 1 ] || trap cleanup EXIT

[ -f "$root/build/pathly/pathly.php" ] || { echo "Build the module first: tools/build.sh"; exit 1; }
rm -rf "$work" && mkdir -p "$work" && chmod 777 "$work"
mount_dir="$work"
if command -v cygpath >/dev/null 2>&1; then mount_dir="$(cygpath -w "$work")"; fi
cp "$root/tests/integration/mock-api.php" "$root/tests/integration/scenario.php" "$work/"
# Test certificate, made with the openssl of the PrestaShop image.
docker run --rm -v "$mount_dir:/work" --entrypoint openssl "prestashop/prestashop:$ps_tag" \
  req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=api.pathlyhq.com" \
  -addext "subjectAltName=DNS:api.pathlyhq.com" -keyout /work/key.pem -out /work/cert.pem 2>/dev/null

docker network create "$net" >/dev/null
docker run -d --name "$db" --network "$net" -e MARIADB_ROOT_PASSWORD=admin -e MARIADB_DATABASE=prestashop mariadb:10.11 >/dev/null
docker run -d --name "$api" --network "$net" --network-alias api.pathlyhq.com -v "$mount_dir:/work" php:8.3-cli php /work/mock-api.php >/dev/null
docker run -d --name "$shop" --network "$net" -v "$mount_dir:/work" \
  -e DB_SERVER="$db" -e DB_PASSWD=admin -e DB_NAME=prestashop -e PS_INSTALL_AUTO=1 \
  -e PS_DOMAIN=localhost -e PS_FOLDER_ADMIN=admin-dev -e PS_LANGUAGE=fr -e PS_COUNTRY=FR \
  -e ADMIN_MAIL=demo@prestashop.com -e ADMIN_PASSWD=prestashop_demo \
  "prestashop/prestashop:$ps_tag" >/dev/null

echo "Waiting for PrestaShop $ps_tag to install..."
for i in $(seq 1 120); do
  if docker exec "$shop" sh -c 'curl -s -o /dev/null -w "%{http_code}" http://localhost/ | grep -qE "200|301|302"' 2>/dev/null; then
    break
  fi
  [ "$i" = 120 ] && { docker logs --tail 50 "$shop"; echo "PrestaShop did not start"; exit 1; }
  # The PrestaShop 9 image stops right after a successful install (it removes
  # an install folder the installer already removed): start it again once.
  if [ "$(docker inspect -f '{{.State.Running}}' "$shop")" = false ] && [ -z "${restarted:-}" ] \
    && docker logs "$shop" 2>&1 | grep -q "Installation successful"; then
    restarted=1
    docker start "$shop" >/dev/null
  fi
  sleep 5
done

docker exec "$shop" sh -c 'cp /work/cert.pem /usr/local/share/ca-certificates/pathly-test.crt && update-ca-certificates >/dev/null 2>&1'
module_dir="$root/build/pathly"
if command -v cygpath >/dev/null 2>&1; then module_dir="$(cygpath -w "$module_dir")"; fi
docker cp "$module_dir" "$shop:/var/www/html/modules/pathly"
docker exec "$shop" chown -R www-data:www-data /var/www/html/modules/pathly
run() { docker exec -u www-data -w /var/www/html "$shop" "$@"; }

run php bin/console prestashop:module install pathly
run php /work/scenario.php configure
run php /work/scenario.php after-request
run php bin/console prestashop:module uninstall pathly
run php /work/scenario.php clean
echo "Integration test on PrestaShop $ps_tag: PASS"
