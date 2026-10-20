<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Set lists des PCG exportés de mes Kronos (tables mk_setlist et mk_setlist_slot)
 * Alimentées par l'import (ImportPcg) ; ici la lecture pour l'écran des set lists
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use mykronos\src\php\fichier\Fichier                as Fichier;

class Setlist
{
    public const TABLE       = PREFIXE_BDD . "setlist";
    public const TABLE_SLOTS = PREFIXE_BDD . "setlist_slot";

    public static function fichiersAvecSetlists(): array {
    //PCG importés qui ont des set lists, exports de Kronos d'abord puis les plus récents : id => libellé
        $liste = [];
        foreach (Model::mdRequeteLister(
                "SELECT f.id, f.libelle, f.dateFichier, n.nom AS typologie, COUNT(sl.id) AS nb"
              . " FROM " . Fichier::TABLE . " f"
              . " JOIN " . self::TABLE . " sl ON sl.idFichier = f.id"
              . " LEFT JOIN " . Nomenclature::TABLE . " n ON n.id = f.typologie"
              . " GROUP BY f.id, f.libelle, f.dateFichier, n.nom"
              . " ORDER BY n.nom LIKE 'from%' DESC, f.dateFichier DESC, f.id DESC") as $f) {
            $liste[(int)$f['id']] = $f['typologie'] . " - " . $f['libelle'] . " (" . (int)$f['nb'] . " set lists)";
        }
        return $liste;
    }

    public static function lister(int $idFichier, string $texte = ""): array {
    //set lists du fichier avec leurs emplacements ; avec un texte, seuls les emplacements dont le nom, la description
    //ou le nom de la set list contiennent tous les mots
    //chaque emplacement : numero, nom, type, banque, volume, description, son (nom du son appelé dans le même PCG)
        $mots = Recherche::mots($texte);
        $conditions = ["sl.idFichier = ?"];
        $parametres = [$idFichier];
        foreach ($mots as $mot) {
            $conditions[] = "(s.nom LIKE ? OR s.description LIKE ? OR sl.nom LIKE ?)";
            $motif = "%" . addcslashes($mot, "%_\\") . "%";
            array_push($parametres, $motif, $motif, $motif);
        }
        $setlists = [];
        foreach (Model::mdRequeteLister(
                "SELECT sl.numero AS numeroSetlist, sl.nom AS setlist, s.numero, s.nom, s.banque, s.volume, s.description,"
              . " t.valeurA AS type, so.nom AS son, so.favori"
              . " FROM " . self::TABLE . " sl"
              . " JOIN " . self::TABLE_SLOTS . " s ON s.idSetlist = sl.id"
              . " LEFT JOIN " . Nomenclature::TABLE . " t ON t.id = s.idType"
              . " LEFT JOIN " . Son::TABLE . " so ON so.idFichier = sl.idFichier AND so.idType = s.idType AND so.banque = s.banque"
              . " WHERE " . implode(" AND ", $conditions)
              . " ORDER BY sl.numero, s.numero", $parametres) as $ligne) {
            $n = (int)$ligne['numeroSetlist'];
            $setlists[$n] ??= ['numero' => $n, 'nom' => $ligne['setlist'], 'slots' => []];
            $setlists[$n]['slots'][] = $ligne;
        }
        return array_values($setlists);
    }
}
