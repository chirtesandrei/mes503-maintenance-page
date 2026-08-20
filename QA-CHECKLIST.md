# Checklist de validation — Mes503 Maintenance Page

Automatisé : `node tests/static-checks.mjs` (hors ligne) puis
`bash tests/integration-vps.sh <archive>` (WordPress réel).

## Paquet

- [ ] Le ZIP contient un unique dossier `mes503-maintenance-page`.
- [ ] Les chemins internes du ZIP utilisent des barres obliques, pas des antislashs.
- [ ] La version du fichier principal correspond au stable tag du readme.
- [ ] `MPMM_VERSION` correspond à la version de l'en-tête.
- [ ] Le changelog mentionne la version publiée.
- [ ] Les fichiers de développement sont exclus via `.distignore`.
- [ ] Tous les fichiers PHP passent `php -l`.
- [ ] Plugin Check ne remonte aucune erreur bloquante.

## Installation et cycle de vie

- [ ] Installation depuis le ZIP.
- [ ] Activation sans avertissement ni erreur fatale.
- [ ] Mode désactivé par défaut.
- [ ] Désactivation sans modifier le site.
- [ ] Désinstallation : suppression de `mpmm_options` uniquement.

## Réglages

- [ ] Accès limité à `manage_options`.
- [ ] Nonce et contrôle de capacité présents via la Settings API.
- [ ] Texte, URL, couleur, identifiant média et durée assainis.
- [ ] Valeurs échappées lors de l'affichage.
- [ ] Logo sélectionnable et retirable.
- [ ] Aperçu inaccessible sans capacité et nonce valides.

## Page publique

- [ ] Visiteur anonyme : statut 503.
- [ ] En-tête Retry-After valide.
- [ ] En-tête X-Robots-Tag présent.
- [ ] Métadonnée robots noindex présente.
- [ ] **`/robots.txt` ne reçoit jamais le 503 ni la page de maintenance et finit sur la réponse normale du cœur WordPress après une éventuelle redirection.**
- [ ] **`/favicon.ico` ne reçoit pas de document HTML.**
- [ ] Administrateur connecté : site normal.
- [ ] `/wp-login.php` accessible.
- [ ] Administration, AJAX, cron, REST, XML-RPC et WP-CLI exclus.
- [ ] Aucun script, police, pixel ou appel externe.
- [ ] Affichage correct à 360, 768 et 1440 pixels.
- [ ] Navigation clavier et contraste acceptables.

## Internationalisation

- [ ] Les chaînes sources sont en anglais.
- [ ] Toutes les chaînes portent le domaine `mes503-maintenance-page`.
- [ ] `Domain Path: /languages` présent dans l'en-tête.
- [ ] Le fichier POT est régénéré (`python ../tools/build-i18n.py`).
- [ ] Le catalogue `fr_FR` couvre 100 % des chaînes et est compilé en `.mo`.
- [ ] Le ZIP n'embarque aucun `.po`/`.mo` et se replie proprement en anglais tant que WordPress.org n'a pas distribué son pack français.
- [ ] Après mise à disposition du pack WordPress.org, l'interface s'affiche bien en français sur un site `fr_FR`.
- [ ] Aucun texte en dur dans `admin/js/admin.js`.

## Documentation

- [ ] Installation expliquée.
- [ ] Limites documentées.
- [ ] Compatibilités testées indiquées honnêtement.
- [ ] Collecte de données explicitement décrite.
- [ ] Changelog à jour.
- [ ] La section `== Screenshots ==` n'est présente que si `assets/` contient les captures.
- [ ] Procédure de signalement disponible avant lancement public.
