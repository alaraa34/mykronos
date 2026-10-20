<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import des XML déposés dans fichiers/output
 *  - seuls les .xml sont lus (le .xsl qui accompagne un XML est supprimé avec lui)
 *  - le fichier PCG est retrouvé par l'id en préfixe du nom (0001_xxx_output.xml)
 *  - la nature du XML est reconnue par sa racine :
 *        <patch_list>          contenu     -> ImportContenu
 *        <combi_content_list>  composition -> ImportComposition
 *  - tout ou rien : à la moindre anomalie rien n'est enregistré et le XML reste dans output
 *  - un XML traité sans anomalie est supprimé du répertoire output
 ******************************************************************************/
use shared\php\database\Model           as Model;
use shared\php\toolbox\Toolbox_index    as TbIndex;
use mykronos\src\php\son\Categorie      as Categorie;

class ImportOutput
{
    public const REPERTOIRE_OUTPUT = "output";                  //dans fichiers/, au même niveau que pcg
    private const ANNULATION       = "ANNULATION_ANOMALIES";    //rollback volontaire quand le XML a des anomalies
    public const NATURES = [
        ImportContenu::RACINE       => ImportContenu::class,
        ImportComposition::RACINE   => ImportComposition::class,
    ];

    public static function importer(): array {
    //retourne ['fichiers' => [compte rendu par XML], 'ignores' => [], 'anomalies' => []]
        $compteRendu = ['fichiers' => [], 'ignores' => [], 'anomalies' => []];
        $repertoire = TbIndex::projetPath() . Fichier::REPERTOIRE . "/" . self::REPERTOIRE_OUTPUT;
        if (!is_dir($repertoire)) {
            $compteRendu['anomalies'][] = "Répertoire absent : " . Fichier::REPERTOIRE . "/" . self::REPERTOIRE_OUTPUT;
            return $compteRendu;
        }
        foreach (scandir($repertoire) as $nom) {
            $chemin = $repertoire . "/" . $nom;
            if (!is_file($chemin) || strtolower(pathinfo($nom, PATHINFO_EXTENSION)) !== "xml") {continue;}
            $resultat = self::importerUnXml($chemin, $nom);
            if (isset($resultat['ignore'])) {
                $compteRendu['ignores'][] = $nom . " : " . $resultat['ignore'];
                continue;
            }
            if (count($resultat['anomalies']) === 0) {
                $resultat['supprime'] = self::supprimerXml($chemin);
            }
            $compteRendu['fichiers'][] = $resultat;
        }
        return $compteRendu;
    }

    private static function importerUnXml(string $chemin, string $nom): array {
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

        //lecture XML : pas d'accès réseau, erreurs récupérées
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($chemin, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        if ($xml === false) {
            return ['ignore' => "XML illisible"];
        }
        $classe = self::NATURES[$xml->getName()] ?? null;
        if ($classe === null) {
            return ['ignore' => "nature inconnue (racine <" . $xml->getName() . ">)"];
        }

        $resultat = ['xml' => $nom, 'fichier' => $fichier['libelle'], 'nature' => $classe::NATURE,
                     'compteurs' => [], 'categories' => [], 'anomalies' => [], 'supprime' => false];
        try {
            Model::mdTransaction(function () use ($classe, $xml, $idFichier, &$resultat): bool {
                $classe::importer($xml, $idFichier, $resultat);
                if (count($resultat['anomalies']) > 0) {
                    throw new \RuntimeException(self::ANNULATION);     //tout ou rien : rien n'est enregistré
                }
                Model::mdRequeteExecuter("UPDATE " . Fichier::TABLE . " SET " . $classe::COLONNE_FICHIER
                                       . " = 1 WHERE id = :id", ['id' => $idFichier]);
                return true;
            });
        } catch (\Throwable $e) {
            Categorie::viderCache();
            $resultat['compteurs'] = [];
            $resultat['categories'] = [];
            if ($e->getMessage() !== self::ANNULATION) {
                error_log("ImportOutput " . $nom . " : " . $e->getMessage());
                $resultat['anomalies'][] = "Erreur : " . $e->getMessage();
            }
        }
        return $resultat;
    }

    private static function supprimerXml(string $chemin): bool {
    //supprime le XML et le .xsl de même nom
        $xsl = substr($chemin, 0, -4) . ".xsl";
        if (is_file($xsl)) {@unlink($xsl);}
        return @unlink($chemin);
    }
}
