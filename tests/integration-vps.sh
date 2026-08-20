#!/usr/bin/env bash
set -euo pipefail

archive="${1:-/tmp/mes503-maintenance-page-0.1.1.zip}"
test -f "$archive"

qa_root="$(mktemp -d /tmp/mesplugins-wp-qa.XXXXXX)"
db_suffix="$(date +%H%M%S)${RANDOM}"
db_name="mpqa_${db_suffix:0:9}"
db_user="mpu_${db_suffix:0:10}"
db_password="$(openssl rand -hex 18)"
admin_password="$(openssl rand -hex 18)"
server_pid=""
port="$((18080 + RANDOM % 700))"
stage="initialisation"

cleanup() {
	status=$?
	if [[ -n "$server_pid" ]]; then
		kill "$server_pid" 2>/dev/null || true
		wait "$server_pid" 2>/dev/null || true
	fi

	sudo mariadb --batch --skip-column-names <<SQL >/dev/null 2>&1 || true
DROP DATABASE IF EXISTS \`${db_name}\`;
DROP USER IF EXISTS '${db_user}'@'localhost';
SQL

	case "$qa_root" in
		/tmp/mesplugins-wp-qa.*) rm -rf -- "$qa_root" ;;
		*) printf 'Refusing cleanup outside QA root: %s\n' "$qa_root" >&2 ;;
	esac

	if [[ "$status" -ne 0 ]]; then
		printf 'QA_FAIL stage=%s status=%s\n' "$stage" "$status" >&2
	fi
}
trap cleanup EXIT

stage="database"
sudo mariadb --batch --skip-column-names <<SQL
CREATE DATABASE \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${db_user}'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
FLUSH PRIVILEGES;
SQL

stage="wordpress-install"
cd "$qa_root"
wp core download --quiet
wp config create --quiet --dbname="$db_name" --dbuser="$db_user" --dbpass="$db_password" --dbhost=localhost
wp core install --quiet \
	--url="http://127.0.0.1:${port}" \
	--title="MesPlugins QA" \
	--admin_user=qa_admin \
	--admin_password="$admin_password" \
	--admin_email=qa@example.invalid \
	--skip-email

stage="plugin-install"
wp plugin install "$archive" --activate --quiet
plugin_main="$(find wp-content/plugins -mindepth 2 -maxdepth 2 -type f -name 'mes503-maintenance-page.php' -print -quit)"
if [[ -z "$plugin_main" ]]; then
	find wp-content/plugins -maxdepth 3 -type f -print >&2
	wp plugin list --format=table >&2 || true
fi
test -n "$plugin_main"
plugin_dir="$(dirname "$plugin_main")"
plugin_slug="$(basename "$plugin_dir")"
test "$(wp plugin get "$plugin_slug" --field=status)" = "active"

stage="php-lint"
find "$plugin_dir" -type f -name '*.php' -print0 \
	| xargs -0 -n1 php -l \
	| tee "$qa_root/php-lint.log"
! grep -qv 'No syntax errors detected' "$qa_root/php-lint.log"

stage="plugin-check"
wp plugin install plugin-check --activate --quiet
set +e
wp plugin check "$plugin_dir" --format=json >"$qa_root/plugin-check.json"
plugin_check_status=$?
set -e

stage="maintenance-enable"
wp option patch update mpmm_options enabled 1 --quiet
wp option patch update mpmm_options heading 'Maintenance QA vérifiée' --quiet
wp option patch update mpmm_options accent_color '#123456' --quiet

wp server --host=127.0.0.1 --port="$port" >"$qa_root/wp-server.log" 2>&1 &
server_pid=$!

stage="server-start"
for _ in $(seq 1 30); do
	if curl -s -o /dev/null "http://127.0.0.1:${port}/"; then
		break
	fi
	sleep 0.25
done

stage="maintenance-response"
home_code="$(curl -sS -D "$qa_root/headers.txt" -o "$qa_root/home.html" -w '%{http_code}' "http://127.0.0.1:${port}/")"
test "$home_code" = "503"
grep -Eiq '^Retry-After: [0-9]+' "$qa_root/headers.txt"
grep -Eiq '^X-Robots-Tag: noindex, nofollow, noarchive' "$qa_root/headers.txt"
grep -Fq 'Maintenance QA vérifiée' "$qa_root/home.html"
grep -Fq 'name="robots" content="noindex, nofollow, noarchive"' "$qa_root/home.html"
grep -Fq "id='mpmm-maintenance-css'" "$qa_root/home.html" || \
	grep -Fq 'id="mpmm-maintenance-css"' "$qa_root/home.html"
grep -Fq -- '--mpmm-accent:#123456' "$qa_root/home.html"
stylesheet_url="$(sed -nE "s/.*href=['\"]([^'\"]*public\/css\/maintenance\.css[^'\"]*)['\"].*/\1/p" "$qa_root/home.html" | head -n1 | sed 's/&amp;/\&/g')"
test -n "$stylesheet_url"
stylesheet_code="$(curl -sS -o "$qa_root/maintenance.css" -w '%{http_code}' "$stylesheet_url")"
test "$stylesheet_code" = "200"
grep -Fq '.mpmm-card' "$qa_root/maintenance.css"

stage="robots-txt"
# Régression 0.1.1 : robots.txt ne doit jamais recevoir la page de maintenance.
# Un 5xx prolongé sur ce fichier fait cesser l'exploration de tout le site.
robots_code="$(curl -sS -D "$qa_root/robots-headers.txt" -o "$qa_root/robots.txt" -w '%{http_code}' "http://127.0.0.1:${port}/robots.txt")"
robots_location="$(sed -nE 's/^Location:[[:space:]]*(.*)\r$/\1/ip' "$qa_root/robots-headers.txt" | tail -n1)"
if [[ "$robots_code" = "503" ]]; then
	echo 'robots.txt received the maintenance 503 response.' >&2
	exit 1
fi
robots_final_code="$(curl -sS -L -o "$qa_root/robots.txt" -w '%{http_code}' "http://127.0.0.1:${port}/robots.txt")"
if [[ "$robots_final_code" != "200" ]]; then
	printf 'robots.txt ended with %s while maintenance mode was active (initial status %s).\n' "$robots_final_code" "$robots_code" >&2
	cat "$qa_root/robots-headers.txt" >&2
	cat "$qa_root/robots.txt" >&2
	exit 1
fi
! grep -Fq 'mpmm-card' "$qa_root/robots.txt"

# `wp server` sous WordPress 7.0 redirige /robots.txt vers /robots.txt/ avec
# les permaliens simples et sert alors un corps vide. Le point d'entrée robots
# du cœur reste testable directement et doit conserver son contenu normal.
robots_query_code="$(curl -sS -o "$qa_root/robots-query.txt" -w '%{http_code}' "http://127.0.0.1:${port}/?robots=1")"
test "$robots_query_code" = "200"
grep -Eiq '^User-agent:' "$qa_root/robots-query.txt"
! grep -Fq 'mpmm-card' "$qa_root/robots-query.txt"

stage="wordpressorg-translations"
if find "$plugin_dir/languages" -maxdepth 1 -type f \( -name '*.po' -o -name '*.mo' \) -print -quit | grep -q .; then
	echo 'A PO or MO translation catalog was included in the distributed plugin.' >&2
	exit 1
fi
wp language core install fr_FR --quiet
wp site switch-language fr_FR --quiet
wp eval 'echo __( "Maintenance mode", "mes503-maintenance-page" ), "\n";' \
	| grep -Fxq 'Maintenance mode'
wp site switch-language en_US --quiet

stage="login-bypass"
login_code="$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${port}/wp-login.php")"
if [[ "$login_code" != "200" ]]; then
	printf 'Unexpected wp-login.php status: %s\n' "$login_code" >&2
	exit 1
fi

stage="admin-bypass"
wp eval 'wp_set_current_user( 1 ); ob_start(); do_action( "template_redirect" ); ob_end_clean(); echo "ADMIN_BYPASS_OK\n";' \
	| grep -Fxq 'ADMIN_BYPASS_OK'

stage="maintenance-disable"
if ! wp option patch update mpmm_options enabled 0; then
	echo 'Unable to disable maintenance mode through the stored option.' >&2
	wp option get mpmm_options --format=json >&2 || true
	exit 1
fi
disabled_code="$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:${port}/")"
if [[ "$disabled_code" != "200" ]]; then
	printf 'Home returned %s after maintenance was disabled.\n' "$disabled_code" >&2
	wp option get mpmm_options --format=json >&2 || true
	exit 1
fi

stage="uninstall"
wp plugin uninstall "$plugin_slug" --deactivate --quiet
if wp option get mpmm_options >/dev/null 2>&1; then
	echo 'The plugin option still exists after uninstall.' >&2
	exit 1
fi

stage="complete"
printf 'PLUGIN_CHECK_STATUS=%s\n' "$plugin_check_status"
cat "$qa_root/plugin-check.json"
printf '\nINTEGRATION_OK wordpress=%s php=%s maintenance=503 login=200 disabled=200 uninstall=clean\n' \
	"$(wp core version)" "$(php -r 'echo PHP_VERSION;')"
