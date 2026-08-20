# Mes503 Maintenance Page

Plugin WordPress qui affiche une page de maintenance claire aux visiteurs
pendant que les administrateurs continuent de travailler normalement.

La particularité tient dans les signaux envoyés : la page temporaire répond en
**HTTP 503** avec un en-tête `Retry-After` et une consigne `noindex`. Une page
de maintenance servie en 200 ressemble, pour un moteur de recherche, à une
nouvelle page permanente remplaçant le contenu habituel.

- Site du projet : <https://mesplugins.fr/plugins/mode-maintenance/>
- Guide associé : <https://mesplugins.fr/guides/mode-maintenance-et-seo/>
- Version courante : **0.1.4** — bêta publique, gratuite
- Licence : GPL v2 ou ultérieure

## Ce que fait le plugin

- activation et désactivation en un clic ;
- titre, message, logo et couleur d'accent personnalisables ;
- bouton facultatif vers une URL de repli ;
- aperçu réservé aux administrateurs ;
- réponse HTTP 503 avec `Retry-After` configurable ;
- consignes `noindex` pour les moteurs ;
- `robots.txt` laissé intact ;
- aucune collecte de données, aucune requête externe ;
- suppression complète des réglages à la désinstallation.

### Pourquoi `robots.txt` est exclu

Un statut 5xx prolongé sur ce seul fichier fait cesser l'exploration du site
entier — exactement l'inverse de ce qu'une maintenance temporaire doit
signaler. L'exclusion reposait au départ sur `is_robots()`, qui dépend de
règles de réécriture absentes en permaliens simples ; depuis la 0.1.3, le
chemin de la requête est également contrôlé.

## Installation

1. Téléchargez l'archive depuis
   <https://mesplugins.fr/plugins/mode-maintenance/> (le SHA-256 est publié
   à côté du fichier).
2. Dans WordPress : Extensions → Ajouter → Téléverser une extension.
3. Activez, puis ouvrez Réglages → Mode maintenance.

## Organisation du dépôt

| Chemin | Contenu |
| --- | --- |
| `mes503-maintenance-page.php` | Point d'entrée et constantes |
| `includes/` | Classe principale `MPMM_Mode_Maintenance` |
| `admin/`, `public/` | Feuilles de style et script d'administration |
| `languages/` | Catalogue `.pot` et traduction française |
| `tests/static-checks.mjs` | Contrôles statiques exécutables sans WordPress |
| `tests/integration-vps.sh` | Test d'intégration sur un WordPress réel |
| `QA-CHECKLIST.md` | Contrôles humains avant publication |
| `SPECIFICATION-V0.1.0.md` | Spécification d'origine |

## Contribuer

Les rapports de bogue passent par les
[issues](https://github.com/chirtesandrei/mes503-maintenance-page/issues).
Un rapport utile indique la version de WordPress, la version de PHP, la
structure de permaliens et le comportement observé — le défaut corrigé en
0.1.3 ne se manifestait qu'en permaliens simples.

## Développement assisté

Ce plugin est développé avec l'aide d'outils d'intelligence artificielle, puis
relu et testé par un humain avant chaque publication. Le détail du procédé est
documenté sur <https://mesplugins.fr/atelier/>.

## English

The plugin ships in English with a complete French translation. The
WordPress.org readme, including the full changelog and FAQ, is in
[`readme.txt`](readme.txt).
