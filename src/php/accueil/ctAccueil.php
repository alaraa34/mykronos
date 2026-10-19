<?php
declare(strict_types=1);
namespace mykronos\src\php\accueil;
/*******************************************************************************
 * Contrôleur de l'accueil mykronos
 * appel : index.php?dom=accueil&ctr=accueil&fct=xxx  -> fonction ctXxx
 ******************************************************************************/

const NOM_APPLI     = "myKronos";
const COULEUR_THEME = "#2b2d30";
const COULEUR_FOND  = "#ece8e2";

function ctAccueil(): void {
    require('tpAccueil.php');
}

function ctManifest(): void {
//manifeste PWA (application installable sur smartphone)
//appel index.php?ctr=accueil&fct=manifest depuis tpLayout
    $manifeste = [
        'name'             => NOM_APPLI,
        'short_name'       => NOM_APPLI,
        'description'      => "Exploitation des fichiers du Korg Kronos.",
        'lang'             => "fr",
        'start_url'        => "./",
        'scope'            => "./",
        'display'          => "standalone",
        'background_color' => COULEUR_FOND,
        'theme_color'      => COULEUR_THEME,
        'icons' => [
            ['src' => "icons/icon-192.png",          'sizes' => "192x192", 'type' => "image/png"],
            ['src' => "icons/icon-512.png",          'sizes' => "512x512", 'type' => "image/png"],
            ['src' => "icons/icon-512-maskable.png", 'sizes' => "512x512", 'type' => "image/png", 'purpose' => "maskable"],
        ],
    ];
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode($manifeste, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit; //pas de layout
}
