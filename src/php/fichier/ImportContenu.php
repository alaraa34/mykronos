<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import des XML de contenu déposés dans fichiers/output
 *  - seuls les .xml sont lus, le reste (xsl...) est ignoré
 *  - un XML de contenu a pour racine <patch_list> (les autres sont ignorés)
 *  - le fichier PCG est retrouvé par l'id en préfixe du nom (0001_xxx_output.xml)
 *  - chaque <patch> crée ou met à jour un son (clé idFichier + type + banque)
 *  - les sons du fichier absents du XML sont supprimés (sauf si anomalie : rien n'est supprimé)
 *  - un fichier importé sans anomalie passe importContenu à 1
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use shared\php\toolbox\Toolbox_index                as TbIndex;
use mykronos\src\php\son\Son                        as Son;
use mykronos\src\php\son\Categorie                  as Categorie;

class ImportContenu
{
    public const REPERTOIRE_OUTPUT  = "output";         //dans fichiers/, au même niveau que pcg
    public const RACINE_CONTENU     = "patch_list";
    public const GROUPE_TYPE        = "TYPE";

    public static function importer(): array {
    //retourne ['fichiers' => [compte rendu par XML], 'ignores' => [], 'anomalies' => []]
        $compteRendu = ['fichiers' => [], 'ignores' => [], 'anomalies' => []];
        $repertoire = TbIndex::projetPath() . Fichier::REPERTOIRE . "/" . self::REPERTOIRE_OUTPUT;
        if (!is_dir($repertoire)) {
            $compteRendu['anomalies'][] = "Répertoire absent : " . Fichier::REPERTOIRE . "/" . self::REPERTOIRE_OUTPUT;
            return $compteRendu;
        }
        $types = self::types();

        foreach (scandir($repertoire) as $nom) {
            if (!is_file($repertoire . "/" . $nom) || strtolower(pathinfo($nom, PATHINFO_EXTENSION)) !== "xml") {continue;}
            $resultat = self::importerUnXml($repertoire . "/" . $nom, $nom, $types);
            if (isset($resultat['ignore'])) {
                $compteRendu['ignores'][] = $nom . " : " . $resultat['ignore'];
            } else {
                $compteRendu['fichiers'][] = $resultat;
            }
        }
        return $compteRendu;
    }

    private static function importerUnXml(string $chemin, string $nom, array $types): array {
        $resultat = ['xml' => $nom, 'fichier' => "", 'crees' => 0, 'modifies' => 0, 'inchanges' => 0, 'supprimes' => 0,
                     'categories' => [], 'anomalies' => []];

        //fichier PCG d'origine
        $idFichier = Fichier::idDepuisNom($nom);
        $fichier = $idFichier > 0 ? Model::mdRequeteListerUnique(
                "SELECT id, libelle, exclus FROM " . Fichier::TABLE . " WHERE id = ?", [$idFichier]) : [];
        if (count($fichier) === 0) {
            return ['ignore' => "pas de fichier PCG n°" . $idFichier . " (préfixe id absent ou inconnu)"];
        }
        if ((int)$fichier['exclus'] === 1) {
            return ['ignore' => "fichier " . $fichier['libelle'] . " exclu de l'import"];
        }
        $resultat['fichier'] = $fichier['libelle'];

        //lecture XML : pas d'accès réseau, erreurs récupérées
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($chemin, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        if ($xml === false) {
            return ['ignore' => "XML illisible"];
        }
        if ($xml->getName() !== self::RACINE_CONTENU) {
            return ['ignore' => "pas un fichier de contenu (racine <" . $xml->getName() . ">)"];
        }

        //enregistrement des sons en une transaction
        try {
            Model::mdTransaction(function () use ($xml, $idFichier, $types, &$resultat): bool {
                $idsConserves = [];
                foreach ($xml->patch as $patch) {
                    $type   = trim((string)$patch->type);
                    $banque = trim((string)$patch->id);
                    $nomSon = trim((string)$patch->name);
                    if (!isset($types[$type])) {
                        $resultat['anomalies'][] = $banque . " " . $nomSon . " : type « " . $type . " » absent de la nomenclature TYPE";
                        continue;
                    }
                    if ($banque === "") {
                        $resultat['anomalies'][] = $nomSon . " : banque (balise id) vide";
                        continue;
                    }
                    $idSsCategorie = Categorie::idSousCategorie((int)$patch->category, (int)$patch->subcategory,
                                                                $resultat['categories']);
                    $idSon = 0;
                    $resultat[Son::enregistrer($idFichier, $types[$type], $banque, $nomSon, $idSsCategorie, $idSon)]++;
                    $idsConserves[] = $idSon;
                }
                if (count($resultat['anomalies']) === 0) {
                    $resultat['supprimes'] = Son::supprimerAbsents($idFichier, $idsConserves);
                    Model::mdRequeteExecuter("UPDATE " . Fichier::TABLE . " SET importContenu = 1 WHERE id = :id",
                                             ['id' => $idFichier]);
                }
                return true;
            });
        } catch (\Throwable $e) {
            Categorie::viderCache();
            error_log("ImportContenu " . $nom . " : " . $e->getMessage());
            $resultat['crees'] = $resultat['modifies'] = $resultat['inchanges'] = $resultat['supprimes'] = 0;
            $resultat['categories'] = [];
            $resultat['anomalies'][] = "Import annulé, rien n'a été enregistré : " . $e->getMessage();
        }
        return $resultat;
    }

    private static function types(): array {
    //nomenclature TYPE : valeurA (Program, Combi...) => id
        $types = [];
        foreach (Nomenclature::mdNomenclatureGetListe(self::GROUPE_TYPE, "valeurA") as $ligne) {
            $types[trim((string)$ligne['zone'])] = (int)$ligne['identifiant'];
        }
        return $types;
    }
}
