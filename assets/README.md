# Ressources WordPress.org

Ce dossier n'est **pas** livré dans le ZIP du plugin (il est exclu par
`.distignore`). Sur WordPress.org, il est déposé dans `assets/` du dépôt SVN,
à côté de `trunk/`, et alimente la page publique du plugin.

## Ce qui manque encore

Aucun de ces fichiers n'existe à ce jour. Ils demandent une installation
WordPress réelle et un travail graphique — ils ne peuvent pas être générés
depuis le dépôt.

| Fichier | Dimensions | Contenu attendu |
| --- | --- | --- |
| `icon-256x256.png` | 256 × 256 | Marque `M` sur fond violet, identique à celle de la page de maintenance |
| `banner-772x250.png` | 772 × 250 | Bandeau, titre court, ton éditorial ivoire du site |
| `banner-1544x500.png` | 1544 × 500 | Version haute densité du bandeau |
| `screenshot-1.png` | ≥ 1200 de large | Réglages : contenu et interrupteur du mode maintenance |
| `screenshot-2.png` | ≥ 1200 de large | Réglages : couleur d'accent et logo |
| `screenshot-3.png` | ≥ 1200 de large | Page publique de maintenance, vue responsive |

## Règles à respecter

- Les captures doivent montrer l'interface **réelle**, sans retouche ni
  montage : c'est une exigence de WordPress.org et la base de la promesse du
  projet.
- L'ordre des fichiers `screenshot-N.png` doit correspondre exactement à
  l'ordre des légendes de la section `== Screenshots ==` du `readme.txt`.
- Tant que les captures n'existent pas, la section `== Screenshots ==` reste
  absente du `readme.txt`. `tests/static-checks.mjs` échoue si le readme
  annonce des captures sans que ce dossier soit présent.

## Comment produire les captures

Les trois captures peuvent être prises pendant l'exécution de
`tests/integration-vps.sh`, qui monte déjà une installation WordPress jetable
avec le plugin activé et le mode maintenance en fonctionnement.
