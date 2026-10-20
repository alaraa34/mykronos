<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Fichier PCG du Korg Kronos (table mk_fichier)
 * Les fichiers sont rangés dans fichiers/pcg/<typologie>/... (sous-dossiers admis)
 * typologie = id de la nomenclature TYPOLOGIE dont le nom est le répertoire (ex sonsK2+)
 ******************************************************************************/
use shared\php\classes\socle\Mere                       as Mere;
use shared\php\database\Model                           as Model;
use shared\php\database\Model_utils                     as ModelU;
use shared\php\classes\personalisation\Nomenclature     as Nomenclature;
use shared\php\toolbox\Toolbox_index                    as TbIndex;

class Fichier extends Mere
{
    // Attributs : id dans la classe mère, un attribut par colonne de la table
    public string $libelle = "";            //nom du fichier sans .pcg
    public string $emplacement = "";        //chemin à partir du répertoire fichiers, ex pcg/sonsK2+/JMJ Pack/JMJ.PCG
    public string $dateFichier = "";        //date du fichier (Y-m-d)
    public bool   $importContenu = false;
    public bool   $importComposition = false;
    public int    $typologie = 0;           //id nomenclature TYPOLOGIE
    public bool   $exclus = false;

    // Constantes
    public const TABLE              = PREFIXE_BDD . "fichier";
    public const INCLUDE_CRUD       = [];
    public const REPERTOIRE         = "fichiers";       //racine des fichiers Kronos dans le projet (hors git)
    public const REPERTOIRE_PCG     = "pcg";            //sous-répertoire des PCG, contient un dossier par typologie
    public const GROUPE_TYPOLOGIE   = "TYPOLOGIE";
    public const PREFIXE_KRONOS     = "from";           //typologies des exports de mes Kronos : fromK2, fromK3
    public const EXTENSION          = "pcg";
    public const FORMAT_PREFIXE_ID  = "%04d_";          //préfixe id du nom téléchargé, ex 0012_JMJ.PCG
    public const GROUPE_A_SUPPRIMER = "SUPPRIMER";      //nomenclature : extensions des fichiers inutiles (nom = pdf, mp4...)
    //extensions jamais supprimées, même si elles sont dans la nomenclature : fichiers utiles à une installation sur le Kronos
    public const MOT_NAUTILUS       = "nautilus";       //PCG de Nautilus : nom contenant ce mot, ou en-tête Nautilus
    public const MODELE_NAUTILUS    = 0xDD;             //octet 4 de l'en-tête KORG d'un PCG de Nautilus
    public const EXTENSIONS_KRONOS  = ["pcg", "ksc", "kmp", "ksf", "sng", "exsins", "kge", "kgl", "mid", "wav", "aif", "aiff"];

    //------------------------------------------------------------------------------------------------
    //CONSTRUCTEUR
    //-----------------------------------------------------------------------------------------------
    public function __construct(int $id = 0) {
        parent::__construct($id);
    }

    //------------------------------------------------------------------------------------------------
    //METIER
    //-----------------------------------------------------------------------------------------------
    public static function detecter(): array {
    //parcourt les répertoires de typologie et inscrit les PCG dans la table
    //  - emplacement inconnu                      : insertion (valeurs par défaut)
    //  - emplacement connu, même date             : rien
    //  - emplacement connu, date différente       : nouvelle date, importContenu et importComposition remis à false
    //l'emplacement identifie le fichier : un même nom peut exister dans deux dossiers différents
    //les exports de mes Kronos (fromK2 / fromK3) sont d'abord renommés K2_aaaa-mm-jj.PCG / K3_aaaa-mm-jj.PCG
    //les PCG de Nautilus ne sont pas inscrits : ils sont proposés à la suppression (fichiersASupprimer)
    //retourne le compte rendu ['ajoutes'=>[], 'modifies'=>[], 'renommes'=>[], 'inchanges'=>[], 'anomalies'=>[]]
        $compteRendu = ['ajoutes' => [], 'modifies' => [], 'renommes' => [], 'inchanges' => [], 'anomalies' => []];
        $racine = TbIndex::projetPath() . self::REPERTOIRE . "/";

        foreach (Nomenclature::mdNomenclatureGetListe(self::GROUPE_TYPOLOGIE, "nom") as $typologie) {
            $dossier = self::REPERTOIRE_PCG . "/" . $typologie['zone'];
            if (!is_dir($racine . $dossier)) {
                $compteRendu['anomalies'][] = "Répertoire absent : " . self::REPERTOIRE . "/" . $dossier;
                continue;
            }
            foreach (self::listerPCG($racine . $dossier) as $relatif) {
                if (self::estNautilus($racine . $dossier . "/" . $relatif)) {continue;}
                if (str_starts_with((string)$typologie['zone'], self::PREFIXE_KRONOS)) {
                    $relatif = self::renommerExportKronos($racine . $dossier, $relatif, (string)$typologie['zone'], $compteRendu);
                }
                $emplacement = $dossier . "/" . $relatif;
                $date = date("Y-m-d", (int)filemtime($racine . $emplacement));
                $action = self::enregistrer($emplacement, $date, (int)$typologie['identifiant']);
                $compteRendu[$action][] = ['emplacement' => $emplacement, 'date' => $date];
            }
        }
        return $compteRendu;
    }

    private static function renommerExportKronos(string $repertoire, string $relatif, string $typologie, array &$compteRendu): string {
    //renomme un export de Kronos en K2_aaaa-mm-jj.PCG (machine = typologie sans "from", date du fichier)
    //le PCG ne contient rien qui distingue le K2 du K3 : c'est le dossier de dépôt qui fait foi
    //retourne le nouveau chemin relatif (l'ancien si déjà au bon format ou si le renommage échoue)
        $machine = strtoupper(substr($typologie, strlen(self::PREFIXE_KRONOS)));          //K2, K3
        $nom = basename($relatif);
        if (preg_match('/^' . preg_quote($machine, '/') . '_\d{4}-\d{2}-\d{2}(_\d+)?\.pcg$/i', $nom) === 1) {
            return $relatif;
        }
        $sousDossier = dirname($relatif) === "." ? "" : dirname($relatif) . "/";
        $base = $machine . "_" . date("Y-m-d", (int)filemtime($repertoire . "/" . $relatif));
        $nouveau = $base . ".PCG";
        for ($n = 2; file_exists($repertoire . "/" . $sousDossier . $nouveau); $n++) {
            $nouveau = $base . "_" . $n . ".PCG";
        }
        if (!@rename($repertoire . "/" . $relatif, $repertoire . "/" . $sousDossier . $nouveau)) {
            $compteRendu['anomalies'][] = "Renommage impossible (fichier ouvert ?) : " . $relatif;
            return $relatif;
        }
        //fichier déjà inscrit sous l'ancien nom : la ligne suit le fichier
        $prefixe = self::REPERTOIRE_PCG . "/" . $typologie . "/";
        Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET emplacement = :nouveau, libelle = :libelle WHERE emplacement = :ancien",
                                 ['nouveau' => $prefixe . $sousDossier . $nouveau, 'libelle' => pathinfo($nouveau, PATHINFO_FILENAME),
                                  'ancien' => $prefixe . $relatif]);
        $compteRendu['renommes'][] = ['emplacement' => $prefixe . $relatif . " → " . $nouveau,
                                      'date' => date("Y-m-d", (int)filemtime($repertoire . "/" . $sousDossier . $nouveau))];
        return $sousDossier . $nouveau;
    }

    private static function enregistrer(string $emplacement, string $date, int $idTypologie): string {
    //retourne la rubrique du compte rendu : ajoutes, modifies, inchanges ou anomalies
        $existant = ModelU::mdSelectTableUnique(self::TABLE, ['emplacement' => $emplacement]);

        if (count($existant) === 0) {
            $fichier = new Fichier();
            $fichier->libelle     = pathinfo($emplacement, PATHINFO_FILENAME);
            $fichier->emplacement = $emplacement;
            $fichier->dateFichier = $date;
            $fichier->typologie   = $idTypologie;
            return $fichier->update() ? 'ajoutes' : 'anomalies';
        }
        if ((string)$existant['dateFichier'] === $date) {
            return 'inchanges';
        }
        $retour = Model::mdUpdate(self::TABLE,
                    ['dateFichier' => $date, 'importContenu' => false, 'importComposition' => false],
                    "id=" . (int)$existant['id']);
        return $retour ? 'modifies' : 'anomalies';
    }

    public static function listerATraiter(): array {
    //fichiers dont l'import du contenu ou de la composition reste à faire (exclus compris, pour pouvoir les réintégrer)
    //chaque ligne contient les colonnes de la table + typologieNom (nom de la nomenclature, ex sonsK2+)
        $requete = "SELECT f.*, n.nom AS typologieNom"
                 . " FROM " . self::TABLE . " f"
                 . " LEFT JOIN " . Nomenclature::TABLE . " n ON n.id = f.typologie"
                 . " WHERE f.importContenu = 0 OR f.importComposition = 0"
                 . " ORDER BY n.ordre, f.emplacement";
        return Model::mdRequeteLister($requete);
    }

    public static function exclure(int $id, bool $exclus): bool {
    //coche ou décoche exclus : un fichier exclu ne fera pas l'objet d'un import de contenu
        return Model::mdRequeteExecuter("UPDATE " . self::TABLE . " SET exclus = :exclus WHERE id = :id",
                                        ['exclus' => $exclus, 'id' => $id]);
    }

    public static function estExclu(int $id): bool {
        return (int)(ModelU::mdSelectTableUnique(self::TABLE, ['id' => $id])['exclus'] ?? 0) === 1;
    }

    public static function cheminComplet(int $id): string {
    //chemin disque du fichier, "" si inconnu, absent ou hors du répertoire fichiers
        $fichier = ModelU::mdSelectTableUnique(self::TABLE, ['id' => $id]);
        if (count($fichier) === 0) {return "";}
        $racine = realpath(TbIndex::projetPath() . self::REPERTOIRE);
        $chemin = realpath($racine . "/" . $fichier['emplacement']);
        if ($racine === false || $chemin === false || !str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR) || !is_file($chemin)) {
            return "";
        }
        return $chemin;
    }

    public static function nomAvecId(int $id, string $nom): string {
    //nom donné au fichier téléchargé : l'id en préfixe évite les homonymes
    //et permet de reconnaître le fichier quand il revient du Kronos (fromK2 / fromK3)
        return sprintf(self::FORMAT_PREFIXE_ID, $id) . $nom;
    }

    public static function idDepuisNom(string $nom): int {
    //id lu dans le préfixe d'un nom de fichier, 0 si pas de préfixe
        return preg_match('/^(\d+)_/', $nom, $morceaux) === 1 ? (int)$morceaux[1] : 0;
    }

    //------------------------------------------------------------------------------------------------
    //TECHNIQUE
    //-----------------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------------------
    //FICHIERS INUTILES (allègement du répertoire fichiers/pcg)
    //-----------------------------------------------------------------------------------------------
    public static function extensionsASupprimer(): array {
    //extensions de la nomenclature SUPPRIMER, en minuscules, sans les extensions protégées du Kronos
        $extensions = [];
        foreach (Nomenclature::mdNomenclatureGetListe(self::GROUPE_A_SUPPRIMER, "nom") as $ligne) {
            $extension = strtolower(ltrim(trim((string)$ligne['zone']), "."));
            if ($extension !== "" && !in_array($extension, self::EXTENSIONS_KRONOS, true)) {$extensions[] = $extension;}
        }
        return array_values(array_unique($extensions));
    }

    public static function fichiersASupprimer(): array {
    //fichiers de fichiers/pcg à supprimer : extension de la nomenclature SUPPRIMER, ou PCG de Nautilus
    //[['emplacement' => chemin relatif, 'taille' => octets, 'raison' => extension ou Nautilus], ...]
        $extensions = self::extensionsASupprimer();
        $racine = TbIndex::projetPath() . self::REPERTOIRE . "/" . self::REPERTOIRE_PCG;
        if (!is_dir($racine)) {return [];}
        $liste = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterateur as $element) {
            //.DS_Store : l'extension est "ds_store"
            $nom = $element->getFilename();
            $extension = strtolower(str_starts_with($nom, ".") && substr_count($nom, ".") === 1 ? substr($nom, 1) : $element->getExtension());
            if (!$element->isFile()) {continue;}
            $raison = match (true) {
                in_array($extension, $extensions, true)                          => $extension,
                $extension === self::EXTENSION && self::estNautilus($element->getPathname()) => "Nautilus",
                default                                                          => null,
            };
            if ($raison !== null) {
                $liste[] = ['emplacement' => self::REPERTOIRE_PCG . "/" . str_replace("\\", "/", substr($element->getPathname(), strlen($racine) + 1)),
                            'taille'      => (int)$element->getSize(), 'raison' => $raison];
            }
        }
        usort($liste, fn($a, $b) => strnatcasecmp($a['emplacement'], $b['emplacement']));
        return $liste;
    }

    public static function supprimerFichiersInutiles(): array {
    //supprime les fichiers listés par fichiersASupprimer puis les dossiers devenus vides (sauf les dossiers de typologie)
    //retourne ['supprimes' => n, 'octets' => n, 'dossiers' => n, 'erreurs' => [...]]
        $resultat = ['supprimes' => 0, 'octets' => 0, 'dossiers' => 0, 'erreurs' => []];
        $racine = TbIndex::projetPath() . self::REPERTOIRE . "/";
        $dossiers = [];
        foreach (self::fichiersASupprimer() as $fichier) {
            $chemin = $racine . $fichier['emplacement'];
            if (@unlink($chemin)) {
                if ($fichier['raison'] === "Nautilus") {self::oublier($fichier['emplacement']);}
                $resultat['supprimes']++;
                $resultat['octets'] += $fichier['taille'];
                $dossiers[dirname($chemin)] = true;
            } else {
                $resultat['erreurs'][] = "Suppression impossible : " . $fichier['emplacement'];
            }
        }
        //dossiers vides, du plus profond au moins profond, sans remonter jusqu'aux dossiers de typologie
        $typologies = array_map(fn($t) => realpath($racine . self::REPERTOIRE_PCG . "/" . $t['zone']),
                                Nomenclature::mdNomenclatureGetListe(self::GROUPE_TYPOLOGIE, "nom"));
        $aVoir = array_keys($dossiers);
        while (count($aVoir) > 0) {
            usort($aVoir, fn($a, $b) => strlen($b) <=> strlen($a));
            $dossier = array_shift($aVoir);
            $reel = realpath($dossier);
            if ($reel === false || in_array($reel, $typologies, true) || !str_starts_with($reel, (string)realpath($racine . self::REPERTOIRE_PCG))) {continue;}
            if (count(scandir($dossier)) === 2 && @rmdir($dossier)) {
                $resultat['dossiers']++;
                $aVoir[] = dirname($dossier);
            }
        }
        return $resultat;
    }

    public static function estNautilus(string $chemin): bool {
    //PCG de Nautilus : "nautilus" dans le nom, ou en-tête KORG du Nautilus
        if (stripos(basename($chemin), self::MOT_NAUTILUS) !== false) {return true;}
        $entete = @file_get_contents($chemin, false, null, 0, 8);
        return is_string($entete) && strlen($entete) === 8 && str_starts_with($entete, "KORG")
            && ord($entete[4]) === self::MODELE_NAUTILUS;
    }

    private static function oublier(string $emplacement): void {
    //retire de la base un PCG supprimé du disque : ses sons et set lists suivent (clés étrangères), puis les liens de composition
        $ligne = ModelU::mdSelectTableUnique(self::TABLE, ['emplacement' => $emplacement]);
        if (count($ligne) === 0) {return;}
        Model::mdRequeteExecuter("DELETE FROM " . PREFIXE_BDD . "combi_utilise_program WHERE idFichier = :id", ['id' => (int)$ligne['id']]);
        Model::mdRequeteExecuter("DELETE FROM " . self::TABLE . " WHERE id = :id", ['id' => (int)$ligne['id']]);
    }

    private static function listerPCG(string $repertoire): array {
    //chemins relatifs (séparateur /) des .pcg du répertoire et de ses sous-répertoires, extension sans casse
        $liste = [];
        $iterateur = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($repertoire, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterateur as $element) {
            if ($element->isFile() && strtolower($element->getExtension()) === self::EXTENSION) {
                $relatif = substr($element->getPathname(), strlen($repertoire) + 1);
                $liste[] = str_replace("\\", "/", $relatif);
            }
        }
        sort($liste, SORT_NATURAL | SORT_FLAG_CASE);
        return $liste;
    }
}
