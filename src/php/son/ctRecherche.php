<?php
declare(strict_types=1);
namespace mykronos\src\php\son;
/*******************************************************************************
 * Contrôleur recherche du domaine son
 * appel : index.php?fct=rechercherSon&dom=son&ctr=recherche&q=texte[&type=id][&typologie=id]
 ******************************************************************************/

function ctRechercherSon(): void {
    $texte       = trim((string)filter_input(INPUT_GET, 'q'));
    $idType      = (int)filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);
    $idTypologie = (int)filter_input(INPUT_GET, 'typologie', FILTER_VALIDATE_INT);
    $resultat    = Recherche::rechercher($texte, $idType, $idTypologie);
    $types       = Recherche::listeTypes();
    $typologies  = Recherche::listeTypologies();
    require('tpRecherche.php');
}
