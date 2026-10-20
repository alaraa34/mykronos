<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import d'un XML de composition (racine <combi_content_list>) - appelé par ImportOutput
 * Structure attendue (d'après le xsl fourni) :
 *   <combi><id>U-B000</id><timbres><program><id>U-A000</id></program>...</timbres></combi>
 *  - les liens combi -> program du fichier sont remplacés par ceux du XML
 *  - un XML sans combi est normal (PCG sans combi) : rien à enregistrer, composition cochée
 ******************************************************************************/
use shared\php\database\Model as Model;

class ImportComposition
{
    public const RACINE             = "combi_content_list";
    public const NATURE             = "Composition";
    public const COLONNE_FICHIER    = "importComposition";
    public const TABLE              = PREFIXE_BDD . "combi_utilise_program";

    public static function importer(\SimpleXMLElement $xml, int $idFichier, array &$resultat): void {
        $compteurs = ['combis' => 0, 'liens' => 0, 'supprimes' => 0];
        $liens = [];
        foreach ($xml->combi as $combi) {
            $banqueCombi = trim((string)$combi->id);
            if ($banqueCombi === "") {
                $resultat['anomalies'][] = "Combi sans banque (balise id vide)";
                continue;
            }
            $compteurs['combis']++;
            foreach ($combi->timbres->program ?? [] as $program) {
                $banqueProgram = trim((string)$program->id);
                if ($banqueProgram !== "") {
                    $liens[$banqueCombi . "|" . $banqueProgram] = [$banqueCombi, $banqueProgram];   //sans doublon
                }
            }
        }
        if (count($resultat['anomalies']) > 0) {return;}

        //remplacement des liens du fichier
        $compteurs['supprimes'] = Model::mdRequetePreparer(
                "DELETE FROM " . self::TABLE . " WHERE idFichier = ?", [$idFichier])->rowCount();
        foreach ($liens as [$banqueCombi, $banqueProgram]) {
            Model::mdInsert(self::TABLE, ['idFichier' => $idFichier, 'banqueCombi' => $banqueCombi,
                                          'banqueProgram' => $banqueProgram]);
        }
        $compteurs['liens'] = count($liens);
        $resultat['compteurs'] = $compteurs;
    }
}
