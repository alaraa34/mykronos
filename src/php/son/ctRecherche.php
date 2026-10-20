<?php
declare(strict_types=1);
namespace mykronos\src\php\son;
/*******************************************************************************
 * Contrôleur recherche du domaine son
 * appel : index.php?fct=rechercherSon&dom=son&ctr=recherche&q=texte[&type=id][&typologie=id|-1 sauf Kronos|-2 Kronos|0 toutes]
 ******************************************************************************/

function ctRechercherSon(): void {
    $texte       = trim((string)filter_input(INPUT_GET, 'q'));
    $idType      = (int)filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);
    $saisie      = filter_input(INPUT_GET, 'typologie', FILTER_VALIDATE_INT);
    $idTypologie = is_int($saisie) ? $saisie : Recherche::SAUF_KRONOS;       //par défaut : tout sauf mes Kronos
    $moteur      = (string)filter_input(INPUT_GET, 'moteur');
    $favoris     = filter_input(INPUT_GET, 'favoris') === "1";
    $resultat    = Recherche::ajouterPresenceKronos(Recherche::rechercher($texte, $idType, $idTypologie,
                                                                         ['moteur' => $moteur, 'favoris' => $favoris]));
    $types       = Recherche::listeTypes();
    $typologies  = Recherche::listeTypologies();
    require('tpRecherche.php');
}

function ctRechercheCombinaison(): void {
//recherche limitée aux combis, avec leur composition et leur présence sur mes Kronos
//appel : index.php?fct=rechercheCombinaison&dom=son&ctr=recherche&q=texte[&typologie=id]
    $texte       = trim((string)filter_input(INPUT_GET, 'q'));
    $saisie      = filter_input(INPUT_GET, 'typologie', FILTER_VALIDATE_INT);
    $idTypologie = is_int($saisie) ? $saisie : Recherche::SAUF_KRONOS;
    $favoris     = filter_input(INPUT_GET, 'favoris') === "1";
    $resultat    = Recherche::rechercherCombinaisons($texte, $idTypologie, $favoris);
    $typologies  = Recherche::listeTypologies();
    require('tpRechercheCombinaison.php');
}

function ctImprimerCombi(int $id = 0): void {
//fiche imprimable d'une combi : en-tête et composition (timbres, zones, banques sur K2 / K3)
//appel : index.php?fct=imprimerCombi&dom=son&ctr=recherche&id=idSon
    $fiche = Recherche::combi($id);
    if (count($fiche) === 0) {
        http_response_code(404);
        die("Combinaison introuvable");
    }
    $combi = $fiche['combi'];
    $resultat = ['kronos' => $fiche['kronos']];
    require('tpImprimerCombi.php');
}
