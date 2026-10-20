<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import d'un XML de contenu (racine <patch_list>) dans mk_son - appelé par ImportOutput
 *  - chaque <patch> crée ou met à jour un son (clé idFichier + type + banque)
 *  - les sons du fichier absents du XML sont supprimés (sauf si anomalie : rien n'est supprimé)
 ******************************************************************************/
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use mykronos\src\php\son\Son                        as Son;
use mykronos\src\php\son\Categorie                  as Categorie;

class ImportContenu
{
    public const RACINE             = "patch_list";
    public const NATURE             = "Contenu";
    public const COLONNE_FICHIER    = "importContenu";
    public const GROUPE_TYPE        = "TYPE";

    public static function importer(\SimpleXMLElement $xml, int $idFichier, array &$resultat): void {
        $types = self::types();
        $compteurs = ['crees' => 0, 'modifies' => 0, 'inchanges' => 0, 'supprimes' => 0];
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
            $compteurs[Son::enregistrer($idFichier, $types[$type], $banque, $nomSon, $idSsCategorie, $idSon)]++;
            $idsConserves[] = $idSon;
        }
        if (count($resultat['anomalies']) === 0) {
            $compteurs['supprimes'] = Son::supprimerAbsents($idFichier, $idsConserves);
        }
        $resultat['compteurs'] = $compteurs;
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
