<?php
/**
 * Routeur du serveur PHP intégré, pour le banc d'essai.
 *
 * Le serveur intégré sert les fichiers présents sur le disque et renvoie 404
 * pour le reste. WordPress produit robots.txt à la volée : sans ce routeur,
 * la requête n'atteint jamais index.php et le banc mesurerait un 404 qui ne
 * vient pas des plugins.
 */
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$racine = __DIR__ . '/wp';
$fichier = $racine . $chemin;

if ($chemin !== '/' && is_file($fichier)) {
    return false; // le serveur sert le fichier réel
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $racine . '/index.php';
