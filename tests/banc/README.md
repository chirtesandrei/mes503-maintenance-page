# Banc d'essai des plugins de maintenance

Ces scripts montent un WordPress jetable, y installent plusieurs extensions de
maintenance, les activent une par une et relèvent ce que le serveur répond
réellement à un visiteur anonyme.

Ils existent pour que le comparatif publié sur
<https://mesplugins.fr/guides/comparatif-plugins-maintenance-wordpress/> soit
vérifiable par quelqu'un d'autre que son auteur — nous éditons l'un des
plugins mesurés.

## Ce qui est mesuré

| Adresse | Attendu |
| --- | --- |
| `/` | 503, avec `Retry-After` |
| `/robots.txt` | **jamais** 5xx, et `Content-Type: text/plain` |
| `/favicon.ico` | pas un document HTML complet |
| `/wp-login.php` | 200 — sinon on s'enferme dehors |

Une réponse 5xx prolongée sur `robots.txt` fait cesser l'exploration du site
entier : c'est la mesure la plus importante du banc, et celle que les pages de
présentation des extensions ne donnent jamais.

## Usage

Dépendances : `wp-cli`, `php`, `mariadb` avec `sudo`, `curl`.

```sh
bash banc-maintenance-setup.sh
bash banc-maintenance-mesure.sh mes503-maintenance-page wp-maintenance-mode maintenance
```

Variantes d'un même plugin :

```sh
VARIANTE_503=1 ETIQUETTE='maintenance (503 active)' bash banc-maintenance-mesure.sh maintenance
VARIANTE_COMING_SOON=1 ETIQUETTE='coming-soon (bientot disponible)' bash banc-maintenance-mesure.sh coming-soon
```

Les résultats s'accumulent dans `/tmp/mesplugins-banc/resultats.csv`.

## Garde-fou

Avant chaque mesure, l'empreinte de la page servie est comparée à celle du
site sans plugin actif. Si elles sont identiques, la colonne
`maintenance_active` vaut `NON` : la maintenance n'était pas réellement en
place et la ligne ne doit pas être publiée. C'est le piège principal de ce
genre de test — chaque extension range son interrupteur ailleurs, et il est
facile de mesurer un site parfaitement normal en croyant mesurer autre chose.

## Résultats publiés

`resultats-2026-08-20.csv` — WordPress 7.1, PHP 8.3.31.
