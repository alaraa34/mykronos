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
    public const EXTENSION          = "pcg";
    public const FORMAT_PREFIXE_ID  = "%04d_";          //préfixe id du nom téléchargé, ex 0012_JMJ.PCG

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
    //retourne le compte rendu ['ajoutes'=>[], 'modifies'=>[], 'inchanges'=>[], 'anomalies'=>[]]
        $compteRendu = ['ajoutes' => [], 'modifies' => [], 'inchanges' => [], 'anomalies' => []];
        $racine = TbIndex::projetPath() . self::REPERTOIRE . "/";

        foreach (Nomenclature::mdNomenclatureGetListe(self::GROUPE_TYPOLOGIE, "nom") as $typologie) {
            $dossier = self::REPERTOIRE_PCG . "/" . $typologie['zone'];
            if (!is_dir($racine . $dossier)) {
                $compteRendu['anomalies'][] = "Répertoire absent : " . self::REPERTOIRE . "/" . $dossier;
                continue;
            }
            foreach (self::listerPCG($racine . $dossier) as $relatif) {
                $emplacement = $dossier . "/" . $relatif;
                $date = date("Y-m-d", (int)filemtime($racine . $emplacement));
                $action = self::enregistrer($emplacement, $date, (int)$typologie['identifiant']);
                $compteRendu[$action][] = ['emplacement' => $emplacement, 'date' => $date];
            }
        }
        return $compteRendu;
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
