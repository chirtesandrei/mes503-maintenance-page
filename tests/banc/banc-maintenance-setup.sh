#!/usr/bin/env bash
#
# Monte un WordPress jetable pour le banc d'essai des plugins de maintenance.
#
# Le banc n'est pas détruit en sortie : il sert à plusieurs passes successives.
# `banc-maintenance-teardown.sh` le démonte.
#
# Écrit les coordonnées dans /tmp/mesplugins-banc/env pour que les autres
# scripts les relisent sans les redemander.
#
set -euo pipefail

BANC="/tmp/mesplugins-banc"
WP="$BANC/wp"
PORT="${PORT:-18321}"

rm -rf "$BANC"
mkdir -p "$WP"

db_suffix="$(date +%H%M%S)${RANDOM}"
db_name="mpbanc_${db_suffix:0:8}"
db_user="mpb_${db_suffix:0:10}"
db_password="$(openssl rand -hex 18)"
admin_password="$(openssl rand -hex 18)"

sudo mariadb --batch --skip-column-names <<SQL
DROP DATABASE IF EXISTS \`${db_name}\`;
CREATE DATABASE \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
FLUSH PRIVILEGES;
SQL

cd "$WP"
wp core download --quiet
wp config create --quiet --dbname="$db_name" --dbuser="$db_user" --dbpass="$db_password" --dbhost=localhost
wp core install --quiet \
	--url="http://127.0.0.1:${PORT}" \
	--title="Banc maintenance" \
	--admin_user=banc_admin \
	--admin_password="$admin_password" \
	--admin_email=banc@example.invalid \
	--skip-email

# Permaliens jolis : c'est la configuration de la majorité des sites réels.
# La variante « permaliens simples » est testée séparément, parce que c'est
# précisément là que le défaut de robots.txt apparaît.
wp rewrite structure '/%postname%/' --hard --quiet || true

cat > "$BANC/env" <<ENV
BANC=$BANC
WP=$WP
PORT=$PORT
DB_NAME=$db_name
DB_USER=$db_user
ADMIN_USER=banc_admin
ADMIN_PASSWORD=$admin_password
BASE=http://127.0.0.1:$PORT
ENV

printf 'BANC_PRET wp=%s port=%s version=%s php=%s\n' \
	"$WP" "$PORT" "$(wp core version)" "$(php -r 'echo PHP_VERSION;')"
