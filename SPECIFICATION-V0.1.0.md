# Cahier des charges — MesPlugins Mode maintenance 0.1.0

Date : 4 août 2026  
Statut : bêta de préproduction

## Objectif

Permettre à un administrateur WordPress d'afficher une page temporaire claire et adaptée au mobile pendant une intervention, sans perdre son propre accès au site.

## Utilisateur principal

Propriétaire de site, freelance ou petite agence souhaitant activer une maintenance temporaire sans constructeur de pages ni réglages complexes.

## Fonctions incluses

- activation et désactivation en un clic ;
- titre de page, titre principal et message ;
- couleur principale ;
- logo choisi dans la médiathèque ;
- bouton facultatif ;
- prévisualisation réservée aux administrateurs ;
- accès normal pour les administrateurs connectés ;
- exclusion de l'administration, de la connexion, des tâches planifiées, d'AJAX, de REST, de XML-RPC et de WP-CLI ;
- statut HTTP 503 ;
- en-tête Retry-After réglable de 300 à 86 400 secondes ;
- en-têtes anti-cache et X-Robots-Tag ;
- aucune collecte de données ni requête externe ;
- suppression de l'option à la désinstallation.

## Hors périmètre

- modèles multiples ;
- compte à rebours ;
- formulaire de newsletter ;
- rôles personnalisés autorisés ;
- règles d'exclusion par URL ;
- multisite centralisé ;
- statistiques ;
- mises à jour automatiques hors WordPress.org ;
- version payante.

## Critères d'acceptation

1. Le plugin s'installe et s'active depuis un ZIP sans erreur PHP.
2. Le mode est désactivé par défaut.
3. Un visiteur anonyme reçoit le code 503 et la page configurée lorsque le mode est actif.
4. La réponse contient Retry-After et X-Robots-Tag.
5. La page de connexion reste accessible.
6. Un administrateur connecté voit le site normalement.
7. Les réglages refusent les valeurs dangereuses ou invalides.
8. Le plugin n'émet aucune requête externe.
9. La page est lisible sur mobile et au clavier.
10. La désinstallation supprime uniquement l'option du plugin.
