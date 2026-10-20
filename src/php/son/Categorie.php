<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Catégories et sous-catégories Kronos (table récursive mk_cat_sscat)
 *  - catégorie      : idCategorie = 0,             idPersonalise = n° de catégorie du Kronos,
 *                     idType = Program ou Combi (nomenclature TYPE) : chaque type a sa propre liste
 *  - sous-catégorie : idCategorie = id du parent,  idPersonalise = n° de sous-catégorie,
 *                     idType non renseigné (c'est celui de la catégorie parente)
 * La table est alimentée par le Global d'un PCG de mon Kronos (chargerDepuisKronos) ; seuls les libellés se modifient à l'écran.
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\database\Database                    as Database;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;

class Categorie
{
    public const TABLE                  = PREFIXE_BDD . "cat_sscat";
    public const RACINE                 = 0;                            //idCategorie d'une catégorie
    public const LIBELLE_CATEGORIE      = "Catégorie à renseigner";
    public const LIBELLE_SSCATEGORIE    = "Sous catégorie à renseigner";
    public const LONGUEUR_LIBELLE       = 50;                           //varchar(50) de la table
    public const TYPES_AVEC_CATEGORIE   = ["Program", "Combi"];         //valeurA de la nomenclature TYPE

    private static array $cache = [];                                   //"idType/idParent/numero" => id

    //------------------------------------------------------------------------------------------------
    // IMPORT
    //------------------------------------------------------------------------------------------------
    public static function idSousCategorie(int $idType, int $numCategorie, int $numSousCategorie, array &$creations): int {
    //id de la sous-catégorie (numéros du Kronos) dans la liste du type (Program ou Combi),
    //créée avec sa catégorie si besoin - $creations reçoit chaque création, pour le compte rendu
        $idCategorie = self::trouverOuCreer($idType, $numCategorie, self::RACINE, self::LIBELLE_CATEGORIE, $creations);
        return self::trouverOuCreer($idType, $numSousCategorie, $idCategorie, self::LIBELLE_SSCATEGORIE, $creations);
    }

    public static function viderCache(): void {
    //à appeler après une transaction annulée ou une modification : les id en cache peuvent ne plus exister
        self::$cache = [];
    }

    private static function trouverOuCreer(int $idType, int $numero, int $idParent, string $libelle, array &$creations): int {
        $cle = $idType . "/" . $idParent . "/" . $numero;
        if (isset(self::$cache[$cle])) {return self::$cache[$cle];}

        $racine = $idParent === self::RACINE;
        $existant = Model::mdRequeteListerUnique(
                "SELECT id FROM " . self::TABLE . " WHERE idCategorie = ? AND idPersonalise = ?"
              . ($racine ? " AND idType = ?" : "") . " ORDER BY id LIMIT 1",
                $racine ? [$idParent, $numero, $idType] : [$idParent, $numero]);
        if (count($existant) > 0) {
            return self::$cache[$cle] = (int)$existant['id'];
        }
        Model::mdInsert(self::TABLE, ['idPersonalise' => $numero, 'libelle' => $libelle, 'idCategorie' => $idParent,
                                      'idType' => $racine ? $idType : null]);
        $id = (int)Database::dbConnect()->lastInsertId();
        $creations[] = $racine
                ? "Catégorie " . self::nomType($idType) . " " . $numero . " (id " . $id . ")"
                : "Sous-catégorie " . $numero . " de la catégorie id " . $idParent . " (id " . $id . ")";
        return self::$cache[$cle] = $id;
    }

    public static function chargerDepuisKronos(array $categories): array {
    //met la table à l'image des catégories lues dans le Global d'un PCG (LecteurPcg::lire()['categories'])
    //  - pour chaque type, n° de catégorie et n° de sous-catégorie : libellé mis à jour, ou ligne créée
    //  - les lignes qui n'existent pas sur le Kronos (sans n°, autre type...) sont supprimées si aucun son n'y est rangé,
    //    conservées sinon (signalées dans 'conservees')
    //retourne ['creees' => n, 'renommees' => n, 'inchangees' => n, 'supprimees' => n, 'conservees' => [libellés]]
        $resultat = ['creees' => 0, 'renommees' => 0, 'inchangees' => 0, 'supprimees' => 0, 'conservees' => []];
        $types = array_flip(self::types());                         //Program / Combi => id
        Model::mdTransaction(function () use ($categories, $types, &$resultat): bool {
            $gardees = [];
            foreach ($categories as $nomType => $liste) {
                if (!isset($types[$nomType])) {continue;}
                foreach ($liste as $numero => $categorie) {
                    $idCategorie = self::poser($types[$nomType], self::RACINE, (int)$numero, $categorie['libelle'], $resultat);
                    $gardees[$idCategorie] = true;
                    foreach ($categorie['sous'] as $numeroSous => $libelle) {
                        $gardees[self::poser($types[$nomType], $idCategorie, (int)$numeroSous, $libelle, $resultat)] = true;
                    }
                }
            }
            self::nettoyer($gardees, $resultat);
            return true;
        });
        self::viderCache();
        return $resultat;
    }

    private static function nettoyer(array $gardees, array &$resultat): void {
    //supprime les lignes absentes du Kronos qui ne contiennent aucun son (sous-catégories d'abord, puis catégories vides)
        $sonsParLigne = array_column(Model::mdRequeteLister(
                "SELECT idSsCategorie, COUNT(*) AS nb FROM " . Son::TABLE . " WHERE idSsCategorie IS NOT NULL GROUP BY idSsCategorie"),
                'nb', 'idSsCategorie');
        foreach (Model::mdRequeteLister("SELECT id, libelle FROM " . self::TABLE . " WHERE idCategorie <> 0") as $sous) {
            if (isset($gardees[(int)$sous['id']])) {continue;}
            if (isset($sonsParLigne[(int)$sous['id']])) {
                $resultat['conservees'][] = $sous['libelle'] . " (" . $sonsParLigne[(int)$sous['id']] . " sons)";
                continue;
            }
            Model::mdRequeteExecuter("DELETE FROM " . self::TABLE . " WHERE id = :id", ['id' => (int)$sous['id']]);
            $resultat['supprimees']++;
        }
        foreach (Model::mdRequeteLister("SELECT c.id, c.libelle, (SELECT COUNT(*) FROM " . self::TABLE . " s WHERE s.idCategorie = c.id) AS nb"
                                      . " FROM " . self::TABLE . " c WHERE c.idCategorie = 0") as $categorie) {
            if (isset($gardees[(int)$categorie['id']]) || (int)$categorie['nb'] > 0) {continue;}
            Model::mdRequeteExecuter("DELETE FROM " . self::TABLE . " WHERE id = :id", ['id' => (int)$categorie['id']]);
            $resultat['supprimees']++;
        }
    }

    private static function poser(int $idType, int $idParent, int $numero, string $libelle, array &$resultat): int {
    //ligne (type, parent, n°) avec ce libellé : retourne son id
        $racine = $idParent === self::RACINE;
        $libelle = mb_substr(trim($libelle) !== "" ? trim($libelle) : ($racine ? self::LIBELLE_CATEGORIE : self::LIBELLE_SSCATEGORIE),
                             0, self::LONGUEUR_LIBELLE);
        $existant = Model::mdRequeteListerUnique(
                "SELECT id, libelle FROM " . self::TABLE . " WHERE idCategorie = ? AND idPersonalise = ?"
              . ($racine ? " AND idType = ?" : "") . " ORDER BY id LIMIT 1",
                $racine ? [$idParent, $numero, $idType] : [$idParent, $numero]);
        if (count($existant) === 0) {
            Model::mdInsert(self::TABLE, ['idPersonalise' => $numero, 'libelle' => $libelle, 'idCategorie' => $idParent,
                                          'idType' => $racine ? $idType : null]);
            $resultat['creees']++;
            return (int)Database::dbConnect()->lastInsertId();
        }
        if ((string)$existant['libelle'] !== $libelle) {
            Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET libelle = :libelle WHERE id = :id",
                                     ['libelle' => $libelle, 'id' => (int)$existant['id']]);
            $resultat['renommees']++;
        } else {
            $resultat['inchangees']++;
        }
        return (int)$existant['id'];
    }

    //------------------------------------------------------------------------------------------------
    // TYPES
    //------------------------------------------------------------------------------------------------
    public static function types(): array {
    //types qui ont des catégories : id nomenclature => libellé (Program, Combi)
        $types = [];
        foreach (Nomenclature::mdNomenclatureGetListe("TYPE", "valeurA") as $ligne) {
            if (in_array(trim((string)$ligne['zone']), self::TYPES_AVEC_CATEGORIE, true)) {
                $types[(int)$ligne['identifiant']] = trim((string)$ligne['zone']);
            }
        }
        return $types;
    }

    public static function nomType(int $idType): string {
        return self::types()[$idType] ?? ("type " . $idType);
    }

    //------------------------------------------------------------------------------------------------
    // EDITION
    //------------------------------------------------------------------------------------------------
    public static function listerCategories(int $idType): array {
    //catégories d'un type avec nombre de sous-catégories et de sons
        return Model::mdRequeteLister(
            "SELECT c.id, c.idPersonalise, c.libelle, c.idType,"
          . " (SELECT COUNT(*) FROM " . self::TABLE . " s WHERE s.idCategorie = c.id) AS nbSousCategories,"
          . " (SELECT COUNT(*) FROM " . Son::TABLE . " so JOIN " . self::TABLE . " s ON s.id = so.idSsCategorie"
          . "   WHERE s.idCategorie = c.id) AS nbSons"
          . " FROM " . self::TABLE . " c WHERE c.idCategorie = ? AND c.idType = ?"
          . " ORDER BY c.idPersonalise, c.libelle",
            [self::RACINE, $idType]);
    }

    public static function listerSousCategories(int $idCategorie): array {
    //sous-catégories d'une catégorie avec nombre de sons
        return Model::mdRequeteLister(
            "SELECT s.id, s.idPersonalise, s.libelle,"
          . " (SELECT COUNT(*) FROM " . Son::TABLE . " so WHERE so.idSsCategorie = s.id) AS nbSons"
          . " FROM " . self::TABLE . " s WHERE s.idCategorie = ? ORDER BY s.idPersonalise, s.libelle",
            [$idCategorie]);
    }

    public static function detail(int $id): array {
        return Model::mdRequeteListerUnique("SELECT id, idPersonalise, libelle, idCategorie, idType FROM " . self::TABLE
                                          . " WHERE id = ?", [$id]);
    }

    public static function renommer(int $idParent, array $lignes, int $idType = 0): array {
    //libellés d'un niveau : catégories du type $idType si $idParent = 0, sinon sous-catégories de $idParent
    //$lignes : id => ['libelle' => libellé] - tout ou rien
    //retourne ['modifies' => n, 'erreurs' => [...]]
        $resultat = ['modifies' => 0, 'erreurs' => []];
        $racine = $idParent === self::RACINE;
        $actuels = array_column(Model::mdRequeteLister(
                "SELECT id, libelle FROM " . self::TABLE . " WHERE idCategorie = ?" . ($racine ? " AND idType = ?" : ""),
                $racine ? [$idParent, $idType] : [$idParent]), 'libelle', 'id');
        $modifications = [];
        foreach ($lignes as $id => $ligne) {
            $id = (int)$id;
            if (!array_key_exists($id, $actuels) || !isset($ligne['libelle'])) {continue;}
            $libelle = trim((string)$ligne['libelle']);
            if ($libelle === (string)$actuels[$id]) {continue;}
            if ($libelle === "" || mb_strlen($libelle) > self::LONGUEUR_LIBELLE) {
                $resultat['erreurs'][] = "Libellé vide ou de plus de " . self::LONGUEUR_LIBELLE . " caractères (« " . $actuels[$id] . " »)";
                continue;
            }
            $modifications[$id] = $libelle;
        }
        if (count($resultat['erreurs']) > 0) {return $resultat;}
        Model::mdTransaction(function () use ($modifications, &$resultat): bool {
            foreach ($modifications as $id => $libelle) {
                Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET libelle = :libelle WHERE id = :id",
                                         ['libelle' => $libelle, 'id' => $id]);
                $resultat['modifies']++;
            }
            return true;
        });
        return $resultat;
    }
}
