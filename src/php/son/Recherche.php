<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Recherche de sons
 *  - chaque mot saisi doit se trouver dans le nom du son, sa catégorie ou sa sous-catégorie
 *    (sans tenir compte des majuscules ni des accents)
 *  - filtres facultatifs : type (Program, Combi...) et provenance du fichier :
 *    SAUF_KRONOS (défaut) tout sauf les exports de mes Kronos, KRONOS seulement mes Kronos, TOUTES, ou une typologie
 *  - pour chaque combi trouvée : les programs qu'elle utilise ; pour chaque program : les combis qui l'utilisent
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use mykronos\src\php\fichier\Fichier                as Fichier;

class Recherche
{
    public const MAXIMUM        = 200;                  //nombre de sons affichés au plus
    public const TABLE_LIENS    = PREFIXE_BDD . "combi_utilise_program";
    public const SAUF_KRONOS    = -1;                   //provenance : tout sauf fromK2 / fromK3 (par défaut)
    public const KRONOS         = -2;                   //provenance : seulement fromK2 / fromK3
    public const TOUTES         = 0;
    private const PREFIXE_KRONOS = "from";              //typologies des exports de mes Kronos

    public static function rechercher(string $texte, int $idType = 0, int $idTypologie = self::SAUF_KRONOS): array {
    //retourne ['sons' => [...], 'trop' => bool] - chaque son a en plus 'liens' (programs utilisés ou combis utilisatrices)
        $mots = self::mots($texte);
        if (count($mots) === 0) {return ['sons' => [], 'trop' => false];}

        $conditions = [];
        $parametres = [];
        foreach ($mots as $mot) {
            $conditions[] = "(so.nom LIKE ? OR sc.libelle LIKE ? OR c.libelle LIKE ?)";
            $motif = "%" . addcslashes($mot, "%_\\") . "%";
            array_push($parametres, $motif, $motif, $motif);
        }
        if ($idType > 0)      {$conditions[] = "so.idType = ?";    $parametres[] = $idType;}
        if ($idTypologie > 0) {$conditions[] = "f.typologie = ?";  $parametres[] = $idTypologie;}
        if ($idTypologie === self::SAUF_KRONOS) {$conditions[] = "(ty.nom IS NULL OR ty.nom NOT LIKE ?)"; $parametres[] = self::PREFIXE_KRONOS . "%";}
        if ($idTypologie === self::KRONOS)      {$conditions[] = "ty.nom LIKE ?";                         $parametres[] = self::PREFIXE_KRONOS . "%";}

        $sons = Model::mdRequeteLister(
            "SELECT so.id, so.nom, so.banque, so.idType, so.idFichier, t.valeurA AS type,"
          . " sc.libelle AS sousCategorie, c.libelle AS categorie,"
          . " f.libelle AS fichier, f.emplacement, f.exclus, ty.nom AS typologie"
          . " FROM " . Son::TABLE . " so"
          . " JOIN " . Nomenclature::TABLE . " t ON t.id = so.idType"
          . " JOIN " . Fichier::TABLE . " f ON f.id = so.idFichier"
          . " LEFT JOIN " . Nomenclature::TABLE . " ty ON ty.id = f.typologie"
          . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = so.idSsCategorie"
          . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
          . " WHERE " . implode(" AND ", $conditions)
          . " ORDER BY so.nom, f.libelle, so.banque LIMIT " . (self::MAXIMUM + 1),
            $parametres);
        $trop = count($sons) > self::MAXIMUM;
        $sons = array_slice($sons, 0, self::MAXIMUM);
        return ['sons' => self::ajouterLiens($sons), 'trop' => $trop];
    }

    public static function mots(string $texte): array {
    //mots de la recherche, sans doublon
        return array_values(array_unique(preg_split('/\s+/u', trim($texte), -1, PREG_SPLIT_NO_EMPTY)));
    }

    private static function ajouterLiens(array $sons): array {
    //combi -> programs utilisés (avec leur nom s'ils sont dans le même PCG), program -> combis qui l'utilisent
        $types = array_flip(Categorie::types());                 //Program / Combi => id
        $idProgram = $types['Program'] ?? 0;
        $idCombi   = $types['Combi'] ?? 0;
        foreach ($sons as &$son) {
            $son['liens'] = [];
            if ((int)$son['idType'] === $idCombi) {
                $son['liens'] = Model::mdRequeteLister(
                    "SELECT l.banqueProgram AS banque, p.nom, c.libelle AS categorie, sc.libelle AS sousCategorie"
                  . " FROM " . self::TABLE_LIENS . " l"
                  . " LEFT JOIN " . Son::TABLE . " p ON p.idFichier = l.idFichier AND p.banque = l.banqueProgram AND p.idType = ?"
                  . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = p.idSsCategorie"
                  . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
                  . " WHERE l.idFichier = ? AND l.banqueCombi = ? ORDER BY l.id",
                    [$idProgram, (int)$son['idFichier'], $son['banque']]);
            } elseif ((int)$son['idType'] === $idProgram) {
                $son['liens'] = Model::mdRequeteLister(
                    "SELECT l.banqueCombi AS banque, cb.nom, c.libelle AS categorie, sc.libelle AS sousCategorie"
                  . " FROM " . self::TABLE_LIENS . " l"
                  . " LEFT JOIN " . Son::TABLE . " cb ON cb.idFichier = l.idFichier AND cb.banque = l.banqueCombi AND cb.idType = ?"
                  . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = cb.idSsCategorie"
                  . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
                  . " WHERE l.idFichier = ? AND l.banqueProgram = ? ORDER BY l.banqueCombi",
                    [$idCombi, (int)$son['idFichier'], $son['banque']]);
            }
        }
        unset($son);
        return $sons;
    }

    public static function listeTypes(): array {
    //types de son présents dans la nomenclature : id => libellé
        return array_column(Nomenclature::mdNomenclatureGetListe("TYPE", "valeurA"), 'zone', 'identifiant');
    }

    public static function listeTypologies(): array {
        return array_column(Nomenclature::mdNomenclatureGetListe(Fichier::GROUPE_TYPOLOGIE, "nom"), 'zone', 'identifiant');
    }

    //------------------------------------------------------------------------------------------------
    // RECHERCHE DE COMBINAISONS : composition et présence sur mes Kronos
    //------------------------------------------------------------------------------------------------
    public static function rechercherCombinaisons(string $texte, int $idTypologie = self::SAUF_KRONOS): array {
    //combis trouvées, chacune avec 'kronos' (banques de la combi sur K2 / K3)
    //et 'programs' : [banque d'origine, nom, catégorie, 'kronos' => ['K2' => [banques], 'K3' => [banques]]]
    //la présence sur un Kronos se juge par le nom, dans le dernier export importé de ce Kronos
        $idCombi = array_flip(Categorie::types())['Combi'] ?? 0;
        return self::ajouterPresenceKronos(self::rechercher($texte, $idCombi, $idTypologie));
    }

    public static function ajouterPresenceKronos(array $resultat): array {
    //complète les combis d'un résultat de recherche : 'kronos' (banques de la combi sur K2 / K3) et 'programs'
    //(composition avec banque d'origine et banques sur K2 / K3) - les autres sons ne sont pas modifiés
        $types = array_flip(Categorie::types());
        $idCombi = $types['Combi'] ?? 0;
        $idProgram = $types['Program'] ?? 0;
        $kronos = self::exportsKronos();
        $resultat['kronos'] = $kronos;
        if (count($resultat['sons']) === 0) {return $resultat;}

        //contenu des exports chargé une fois : nom => banques, et banque => nom (programs d'usine)
        $index = [];
        foreach ($kronos as $machine => $idFichier) {
            $index[$machine] = ['noms' => [$idProgram => [], $idCombi => []], 'banques' => []];
            foreach (Model::mdRequeteLister("SELECT idType, banque, nom FROM " . Son::TABLE
                                          . " WHERE idFichier = ? AND idType IN (?, ?)", [$idFichier, $idProgram, $idCombi]) as $son) {
                $index[$machine]['noms'][(int)$son['idType']][self::cle((string)$son['nom'])][] = $son['banque'];
                if ((int)$son['idType'] === $idProgram) {$index[$machine]['banques'][$son['banque']] = $son['nom'];}
            }
        }
        $surKronos = function (int $idType, ?string $nom) use ($kronos, $index): array {
            $banques = [];
            foreach (array_keys($kronos) as $machine) {
                $liste = $nom === null ? [] : ($index[$machine]['noms'][$idType][self::cle($nom)] ?? []);
                sort($liste);
                $banques[$machine] = $liste;
            }
            return $banques;
        };

        foreach ($resultat['sons'] as &$combi) {
            if ((int)$combi['idType'] !== $idCombi) {continue;}
            $combi['kronos'] = $surKronos($idCombi, (string)$combi['nom']);
            $combi['programs'] = [];
            foreach ($combi['liens'] as $lien) {
                $nom = $lien['nom'];
                if ($nom === null) {
                    //program absent du PCG d'origine (son d'usine, banque I-...) : nom repris d'un export Kronos, même banque
                    foreach (array_keys($kronos) as $machine) {$nom ??= $index[$machine]['banques'][$lien['banque']] ?? null;}
                }
                $combi['programs'][] = [
                    'banque'    => $lien['banque'],
                    'nom'       => $nom,
                    'origine'   => $lien['nom'] !== null,           //false : nom repris d'un export Kronos
                    'categorie' => $lien['categorie'] === null ? "" : $lien['categorie'] . " / " . $lien['sousCategorie'],
                    'kronos'    => $surKronos($idProgram, $nom),
                ];
            }
        }
        unset($combi);
        return $resultat;
    }

    private static function cle(string $nom): string {
    //comparaison des noms comme MySQL : sans majuscules ni espaces de fin
        return mb_strtolower(rtrim($nom));
    }

    public static function exportsKronos(): array {
    //dernier export importé de chaque Kronos : 'K2' => idFichier, 'K3' => idFichier (absent si aucun)
        $exports = [];
        foreach (Model::mdRequeteLister(
                "SELECT f.id, n.nom AS typologie FROM " . Fichier::TABLE . " f"
              . " JOIN " . Nomenclature::TABLE . " n ON n.id = f.typologie"
              . " WHERE n.groupe = ? AND n.nom LIKE ? AND f.importContenu = 1 AND f.exclus = 0"
              . " ORDER BY f.dateFichier DESC, f.id DESC",
                [Fichier::GROUPE_TYPOLOGIE, self::PREFIXE_KRONOS . "%"]) as $ligne) {
            $machine = strtoupper(substr((string)$ligne['typologie'], strlen(self::PREFIXE_KRONOS)));
            $exports[$machine] ??= (int)$ligne['id'];
        }
        ksort($exports);
        return $exports;
    }
}
