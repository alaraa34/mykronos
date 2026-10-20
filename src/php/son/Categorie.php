<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Catégories et sous-catégories Kronos (table récursive mk_cat_sscat)
 *  - catégorie      : idCategorie = 0,             idPersonalise = n° de catégorie du Kronos
 *  - sous-catégorie : idCategorie = id du parent,  idPersonalise = n° de sous-catégorie
 ******************************************************************************/
use shared\php\database\Model       as Model;
use shared\php\database\Database    as Database;

class Categorie
{
    public const TABLE              = PREFIXE_BDD . "cat_sscat";
    public const RACINE             = 0;                                //idCategorie d'une catégorie
    public const LIBELLE_CATEGORIE  = "Catégorie à renseigner";
    public const LIBELLE_SSCATEGORIE= "Sous catégorie à renseigner";

    private static array $cache = [];                                   //"idParent/numero" => id

    public static function idSousCategorie(int $numCategorie, int $numSousCategorie, array &$creations): int {
    //id de la sous-catégorie (numéros du Kronos), créée avec sa catégorie si besoin
    //$creations reçoit le libellé de chaque création, pour le compte rendu
        $idCategorie = self::trouverOuCreer($numCategorie, self::RACINE, self::LIBELLE_CATEGORIE, $creations);
        return self::trouverOuCreer($numSousCategorie, $idCategorie, self::LIBELLE_SSCATEGORIE, $creations);
    }

    public static function viderCache(): void {
    //à appeler après une transaction annulée : les id créés n'existent plus
        self::$cache = [];
    }

    private static function trouverOuCreer(int $numero, int $idParent, string $libelle, array &$creations): int {
        $cle = $idParent . "/" . $numero;
        if (isset(self::$cache[$cle])) {return self::$cache[$cle];}

        $existant = Model::mdRequeteListerUnique(
                "SELECT id FROM " . self::TABLE . " WHERE idCategorie = ? AND idPersonalise = ? ORDER BY id LIMIT 1",
                [$idParent, $numero]);
        if (count($existant) > 0) {
            return self::$cache[$cle] = (int)$existant['id'];
        }
        Model::mdInsert(self::TABLE, ['idPersonalise' => $numero, 'libelle' => $libelle, 'idCategorie' => $idParent]);
        $id = (int)Database::dbConnect()->lastInsertId();
        $creations[] = $idParent === self::RACINE
                ? "Catégorie " . $numero . " (id " . $id . ")"
                : "Sous-catégorie " . $numero . " de la catégorie id " . $idParent . " (id " . $id . ")";
        return self::$cache[$cle] = $id;
    }
}
