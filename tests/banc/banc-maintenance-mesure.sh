#!/usr/bin/env bash
#
# Mesure ce que chaque plugin de maintenance répond réellement.
#
# On ne lit pas les pages de vente : on active le plugin, on interroge le site
# comme un visiteur anonyme, et on relève le code HTTP et les en-têtes. Quatre
# adresses comptent :
#
#   /              la page publique — doit répondre 503
#   /robots.txt    ne doit JAMAIS répondre 5xx : un 5xx prolongé sur ce seul
#                  fichier fait cesser l'exploration du site entier
#   /favicon.ico   ne doit pas recevoir un document HTML complet
#   /wp-login.php  doit rester accessible, sinon on s'enferme dehors
#
# Le serveur PHP intégré est mono-thread : les requêtes sont donc séquentielles.
#
#   bash banc-maintenance-mesure.sh <slug> [<slug>...]
#
set -uo pipefail

BANC="/tmp/mesplugins-banc"
. "$BANC/env"
cd "$WP"

RESULTATS="$BANC/resultats.csv"
serveur_pid=""

nettoyer() {
	[ -n "$serveur_pid" ] && kill "$serveur_pid" 2>/dev/null
	wait "$serveur_pid" 2>/dev/null
}
trap nettoyer EXIT

demarrer_serveur() {
	# Sans routeur, le serveur intégré renvoie 404 sur tout fichier absent du
	# disque — dont robots.txt, que WordPress produit à la volée. Le contrôle
	# le plus important du banc passait donc à côté de son sujet.
	php -S "127.0.0.1:$PORT" -t "$WP" "$BANC/routeur.php" >/dev/null 2>&1 &
	serveur_pid=$!
	for _ in $(seq 1 40); do
		curl -s -o /dev/null -m 2 "$BASE/" && return 0
		sleep 0.25
	done
	echo "le serveur ne répond pas" >&2
	return 1
}

# Relève le code et les en-têtes qui décident du sens de la réponse.
sonder() {
	local url="$1" entetes
	entetes="$(curl -sS -D - -o /dev/null -m 15 "$url" 2>/dev/null)"
	local code retry robots ctype
	code="$(printf '%s' "$entetes" | awk 'NR==1{print $2}')"
	retry="$(printf '%s' "$entetes" | awk 'BEGIN{IGNORECASE=1}/^retry-after:/{sub(/\r/,"");print $2;exit}')"
	robots="$(printf '%s' "$entetes" | awk 'BEGIN{IGNORECASE=1}/^x-robots-tag:/{sub(/^[^:]*: */,"");sub(/\r/,"");print;exit}')"
	ctype="$(printf '%s' "$entetes" | awk 'BEGIN{IGNORECASE=1}/^content-type:/{sub(/^[^:]*: */,"");sub(/;.*/,"");sub(/\r/,"");print;exit}')"
	printf '%s|%s|%s|%s' "${code:-000}" "${retry:--}" "${robots:--}" "${ctype:--}"
}

# Empreinte du corps servi. Sert à prouver que la maintenance est réellement
# active : si la page est identique à celle du site normal, la mesure ne veut
# rien dire et doit être signalée comme telle plutôt que publiée.
empreinte() {
	curl -sS -m 15 "$BASE/" 2>/dev/null | sha1sum | cut -c1-12
}

# La page publique porte-t-elle une consigne noindex dans son HTML ?
meta_noindex() {
	curl -sS -m 15 "$BASE/" 2>/dev/null | grep -qi 'name=.robots.[^>]*noindex' && echo oui || echo non
}

# Chaque plugin range son interrupteur ailleurs. Les clés ci-dessous ont été
# relevées dans le code de chaque extension, version par version : c'est le
# seul moyen d'activer la maintenance sans piloter quatre interfaces
# d'administration différentes.
activer_maintenance() {
	case "$1" in
	mes503-maintenance-page)
		wp eval '$o = get_option("mpmm_options", array()); $o["enabled"] = 1; update_option("mpmm_options", $o);' --quiet
		;;
	wp-maintenance-mode)
		wp eval '$o = get_option("wpmm_settings", array()); $o["general"]["status"] = 1; $o["general"]["status_date"] = date("Y-m-d H:i:s"); update_option("wpmm_settings", $o);' --quiet
		;;
	maintenance)
		# status : interrupteur de maintenance. blockse : le 503, désactivé par
		# défaut dans ce plugin — VARIANTE_503=1 le force pour mesurer aussi ce
		# que donne la meilleure configuration possible.
		wp eval '
			$o = get_option("mtnc-options", array());
			if (!isset($o["options"])) { $o["options"] = array(); }
			$o["options"]["status"] = "1";
			$o["options"]["blockse"] = getenv("VARIANTE_503") === "1" ? "1" : "0";
			update_option("mtnc-options", $o);
		' --quiet
		;;
	coming-soon)
		# Les réglages sont stockés en JSON, pas en tableau sérialisé.
		# VARIANTE_COMING_SOON=1 mesure le mode « bientôt disponible », celui
		# que la plupart des utilisateurs activent.
		# SeedProd lit ses réglages de page dans post_content_filtered, en JSON.
		# Sans ce champ, le plugin s’arrête sur « Please create your
		# Maintenance Page » — et répond 200, ce qui fausserait la mesure.
		wp eval '
			$page = wp_insert_post(array(
				"post_title" => "Maintenance",
				"post_type" => "page",
				"post_status" => "publish",
				"post_content" => "<h1>Travaux en cours</h1>",
				"post_content_filtered" => wp_json_encode(array("page_title" => "Maintenance")),
			));
			update_option("seedprod_maintenance_mode_page_id", $page);
			update_option("seedprod_coming_soon_page_id", $page);
			$cs = getenv("VARIANTE_COMING_SOON") === "1";
			update_option("seedprod_settings", wp_json_encode(array(
				"enable_maintenance_mode" => $cs ? false : true,
				"enable_coming_soon_mode" => $cs ? true : false,
			)));
		' --quiet
		;;
	minimal-coming-soon-maintenance-mode)
		# status : 1 = actif, 2 = inactif (valeur par défaut).
		wp eval '$o = get_option("signals_csmm_options", array()); $o["status"] = "1"; update_option("signals_csmm_options", $o);' --quiet
		;;
	esac
}

if [ ! -f "$RESULTATS" ]; then
	echo "plugin,version,maintenance_active,accueil,accueil_retry,accueil_xrobots,accueil_meta_noindex,robots_code,robots_type,favicon_code,favicon_type,login_code" > "$RESULTATS"
fi

demarrer_serveur

# Référence : le site sans aucun plugin de maintenance actif.
wp plugin deactivate --all --quiet 2>/dev/null
REFERENCE="$(empreinte)"

for slug in "$@"; do
	wp plugin deactivate --all --quiet 2>/dev/null
	wp plugin activate "$slug" --quiet || { echo "$slug : activation impossible" >&2; continue; }
	version="$(wp plugin get "$slug" --field=version)"
	activer_maintenance "$slug"

	if [ "$(empreinte)" = "$REFERENCE" ]; then
		actif=NON
	else
		actif=oui
	fi

	IFS='|' read -r a_code a_retry a_robots _ <<<"$(sonder "$BASE/")"
	noindex="$(meta_noindex)"
	IFS='|' read -r r_code _ _ r_type <<<"$(sonder "$BASE/robots.txt")"
	IFS='|' read -r f_code _ _ f_type <<<"$(sonder "$BASE/favicon.ico")"
	IFS='|' read -r l_code _ _ _ <<<"$(sonder "$BASE/wp-login.php")"

	ligne="${ETIQUETTE:-$slug},$version,$actif,$a_code,$a_retry,$a_robots,$noindex,$r_code,$r_type,$f_code,$f_type,$l_code"
	sed -i "/^${ETIQUETTE:-$slug},/d" "$RESULTATS"
	echo "$ligne" >> "$RESULTATS"
	echo "$ligne"
done

wp plugin deactivate --all --quiet 2>/dev/null
