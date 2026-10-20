<?php
declare(strict_types=1);
namespace mykronos\src\php\son;
/*******************************************************************************
 * Contrôleur setlist du domaine son : set lists de mes Kronos
 * appel : index.php?fct=afficherSetlists&dom=son&ctr=setlist[&id=idFichier][&q=texte]
 ******************************************************************************/

function ctAfficherSetlists(int $id = 0): void {
    $fichiers = Setlist::fichiersAvecSetlists();
    $idFichier = isset($fichiers[$id]) ? $id : (int)(array_key_first($fichiers) ?? 0);
    $texte = trim((string)filter_input(INPUT_GET, 'q'));
    $setlists = $idFichier > 0 ? Setlist::lister($idFichier, $texte) : [];
    require('tpSetlists.php');
}
