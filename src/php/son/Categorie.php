<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Catégories et sous-catégories Kronos (table récursive mk_cat_sscat)
 *  - catégorie      : idCategorie = 0,             idPersonalise = n° de catégorie du Kronos,
 *                     idType = Program ou Combi (nomenclature TYPE) : chaque type a sa propre liste
 *  - sous-catégorie : idCategorie = id du parent,  idPersonalise = n° de sous-catégorie,
 *                     idType non renseigné (c'est celui de la catégorie parente)
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
    public const SANS_NUMERO            = -1;                           //catégorie sans n° Kronos
    public const NUMERO_MAX_CATEGORIE   = 17;                           //Kronos : catégories 0 à 17
    public const NUMERO_MAX_SSCATEGORIE = 7;                            //sous-catégories 0 à 7
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
          . " ORDER BY c.idPersonalise < 0, c.idPersonalise, c.libelle",
            [self::RACINE, $idType]);
    }

    public static function listerSousCategories(int $idCategorie): array {
    //sous-catégories d'une catégorie avec nombre de sons
        return Model::mdRequeteLister(
            "SELECT s.id, s.idPersonalise, s.libelle,"
          . " (SELECT COUNT(*) FROM " . Son::TABLE . " so WHERE so.idSsCategorie = s.id) AS nbSons"
          . " FROM " . self::TABLE . " s WHERE s.idCategorie = ? ORDER BY s.idPersonalise < 0, s.idPersonalise, s.libelle",
            [$idCategorie]);
    }

    public static function detail(int $id): array {
        return Model::mdRequeteListerUnique("SELECT id, idPersonalise, libelle, idCategorie, idType FROM " . self::TABLE
                                          . " WHERE id = ?", [$id]);
    }

    public static function enregistrer(int $idParent, array $lignes, int $idType = 0): array {
    //enregistre les lignes d'un niveau : catégories du type $idType si $idParent = 0, sinon sous-catégories de $idParent
    //$lignes : id => ['numero' => n° Kronos, 'libelle' => libellé, 'fusion' => id cible ou 0, 'type' => idType (catégories)]
    //1) fusions  2) numéros, libellés et types, contrôlés sur le résultat final - tout ou rien
    //retourne ['modifies' => n, 'fusions' => n, 'erreurs' => [...]]
        $resultat = ['modifies' => 0, 'fusions' => 0, 'erreurs' => []];
        $racine = $idParent === self::RACINE;
        $actuels = [];
        foreach (Model::mdRequeteLister("SELECT id, idPersonalise, libelle, idType FROM " . self::TABLE
                                      . " WHERE idCategorie = ?" . ($racine ? " AND idType = ?" : ""),
                                        $racine ? [$idParent, $idType] : [$idParent]) as $ligne) {
            $actuels[(int)$ligne['id']] = $ligne;
        }
        $maximum = $racine ? self::NUMERO_MAX_CATEGORIE : self::NUMERO_MAX_SSCATEGORIE;
        $types = self::types();

        //fusions demandées (dans la même liste)
        $fusions = [];
        foreach ($lignes as $id => $ligne) {
            $cible = (int)($ligne['fusion'] ?? 0);
            if ($cible === 0 || !isset($actuels[(int)$id])) {continue;}
            if ($cible === (int)$id || !isset($actuels[$cible])) {
                $resultat['erreurs'][] = "Fusion impossible pour « " . $actuels[(int)$id]['libelle'] . " »";
                continue;
            }
            $fusions[(int)$id] = $cible;
        }
        foreach ($fusions as $cible) {
            if (isset($fusions[$cible])) {
                $resultat['erreurs'][] = "« " . $actuels[$cible]['libelle'] . " » ne peut pas être à la fois fusionnée et cible d'une fusion";
            }
        }

        //valeurs finales des lignes conservées
        $finales = [];
        foreach ($actuels as $id => $actuel) {
            if (isset($fusions[$id])) {continue;}
            $saisie = $lignes[$id] ?? [];
            $numero  = isset($saisie['numero']) && $saisie['numero'] !== "" ? (int)$saisie['numero'] : (int)$actuel['idPersonalise'];
            $libelle = isset($saisie['libelle']) ? trim((string)$saisie['libelle']) : (string)$actuel['libelle'];
            $type    = $racine ? (int)($saisie['type'] ?? $actuel['idType']) : null;
            if ($libelle === "" || mb_strlen($libelle) > self::LONGUEUR_LIBELLE) {
                $resultat['erreurs'][] = "Libellé vide ou de plus de " . self::LONGUEUR_LIBELLE . " caractères (« " . $actuel['libelle'] . " »)";
            }
            if ($numero !== self::SANS_NUMERO && ($numero < 0 || $numero > $maximum)) {
                $resultat['erreurs'][] = "N° " . $numero . " hors limites (0 à " . $maximum . ", ou -1) pour « " . $libelle . " »";
            }
            if ($racine && !isset($types[$type])) {
                $resultat['erreurs'][] = "Type inconnu pour « " . $libelle . " »";
            }
            $finales[$id] = ['numero' => $numero, 'libelle' => $libelle, 'type' => $type];
        }

        //n° uniques : dans la liste affichée, et dans la liste de destination pour un changement de type
        $restants = array_filter($finales, fn($f) => !$racine || $f['type'] === $idType);
        $resultat['erreurs'] = array_merge($resultat['erreurs'], self::doublons($restants));
        if ($racine) {
            foreach (array_filter($finales, fn($f) => $f['type'] !== $idType) as $partant) {
                if ($partant['numero'] === self::SANS_NUMERO || !isset($types[$partant['type']])) {continue;}
                $occupe = Model::mdRequeteListerUnique("SELECT libelle FROM " . self::TABLE
                        . " WHERE idCategorie = 0 AND idType = ? AND idPersonalise = ?", [$partant['type'], $partant['numero']]);
                if (count($occupe) > 0) {
                    $resultat['erreurs'][] = "N° " . $partant['numero'] . " déjà utilisé dans les catégories "
                                           . $types[$partant['type']] . " (« " . $occupe['libelle'] . " »)";
                }
            }
        }
        if (count($resultat['erreurs']) > 0) {
            return $resultat;
        }

        Model::mdTransaction(function () use ($fusions, $finales, $actuels, $racine, &$resultat): bool {
            foreach ($fusions as $source => $cible) {
                $racine ? self::fusionnerCategorie($source, $cible) : self::fusionnerSousCategorie($source, $cible);
                $resultat['fusions']++;
            }
            foreach ($finales as $id => $finale) {
                $inchange = $finale['numero'] === (int)$actuels[$id]['idPersonalise']
                         && $finale['libelle'] === (string)$actuels[$id]['libelle']
                         && (!$racine || $finale['type'] === (int)$actuels[$id]['idType']);
                if ($inchange) {continue;}
                Model::mdRequeteExecuter("UPDATE " . self::TABLE
                        . " SET idPersonalise = :numero, libelle = :libelle" . ($racine ? ", idType = :type" : "")
                        . " WHERE id = :id",
                        array_merge(['numero' => $finale['numero'], 'libelle' => $finale['libelle'], 'id' => $id],
                                    $racine ? ['type' => $finale['type']] : []));
                $resultat['modifies']++;
            }
            return true;
        });
        self::viderCache();
        return $resultat;
    }

    private static function doublons(array $finales): array {
    //erreurs pour les n° (hors -1) utilisés plusieurs fois
        $erreurs = [];
        $numeros = array_filter(array_column($finales, 'numero'), fn($n) => $n !== self::SANS_NUMERO);
        foreach (array_keys(array_filter(array_count_values($numeros), fn($nb) => $nb > 1)) as $double) {
            $erreurs[] = "N° " . $double . " utilisé plusieurs fois : "
                . implode(", ", array_map(fn($f) => "« " . $f['libelle'] . " »",
                                          array_filter($finales, fn($f) => $f['numero'] === $double)));
        }
        return $erreurs;
    }

    private static function fusionnerSousCategorie(int $source, int $cible): void {
    //les sons passent dans la sous-catégorie cible, la source est supprimée
        Model::mdRequeteExecuter("UPDATE " . Son::TABLE . " SET idSsCategorie = :cible WHERE idSsCategorie = :source",
                                 ['cible' => $cible, 'source' => $source]);
        Model::mdRequeteExecuter("DELETE FROM " . self::TABLE . " WHERE id = :id", ['id' => $source]);
    }

    private static function fusionnerCategorie(int $source, int $cible): void {
    //chaque sous-catégorie de la source rejoint celle de même n° dans la cible (fusion), sinon elle est déplacée
        $ciblesParNumero = [];
        foreach (self::listerSousCategories($cible) as $sousCategorie) {
            if ((int)$sousCategorie['idPersonalise'] !== self::SANS_NUMERO) {
                $ciblesParNumero[(int)$sousCategorie['idPersonalise']] ??= (int)$sousCategorie['id'];
            }
        }
        foreach (self::listerSousCategories($source) as $sousCategorie) {
            $numero = (int)$sousCategorie['idPersonalise'];
            if (isset($ciblesParNumero[$numero])) {
                self::fusionnerSousCategorie((int)$sousCategorie['id'], $ciblesParNumero[$numero]);
            } else {
                Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET idCategorie = :cible WHERE id = :id",
                                         ['cible' => $cible, 'id' => (int)$sousCategorie['id']]);
                if ($numero !== self::SANS_NUMERO) {$ciblesParNumero[$numero] = (int)$sousCategorie['id'];}
            }
        }
        Model::mdRequeteExecuter("DELETE FROM " . self::TABLE . " WHERE id = :id", ['id' => $source]);
    }
}
