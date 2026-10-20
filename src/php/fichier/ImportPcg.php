<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import direct des fichiers PCG (sans passer par les XML de PCG Tools)
 * Pour chaque fichier non exclu dont le contenu ou la composition reste à importer :
 *  - lecture du PCG (LecteurPcg)
 *  - sons : création / mise à jour (clé idFichier + type + banque), suppression des sons disparus
 *    catégorie et sous-catégorie (Programs et Combis, chacun sa liste) via la table récursive, créées « à renseigner » si besoin
 *  - composition : les liens combi -> program du fichier sont remplacés
 *  - importContenu et importComposition passent à 1
 * Tout ou rien par fichier : à la moindre anomalie, rien n'est enregistré pour ce fichier.
 ******************************************************************************/
use shared\php\database\Model                       as Model;
use shared\php\classes\personalisation\Nomenclature as Nomenclature;
use mykronos\src\php\son\Son                        as Son;
use mykronos\src\php\son\Categorie                  as Categorie;

class ImportPcg
{
    public const GROUPE_TYPE    = "TYPE";
    public const TABLE_LIENS    = PREFIXE_BDD . "combi_utilise_program";
    private const ANNULATION    = "ANNULATION_ANOMALIES";

    public static function importerATraiter(): array {
    //tous les fichiers à traiter, retourne ['fichiers' => [compte rendu par fichier], 'anomalies' => []]
        $compteRendu = ['fichiers' => [], 'anomalies' => []];
        $types = self::types();
        $fichiers = Model::mdRequeteLister("SELECT id FROM " . Fichier::TABLE
                  . " WHERE exclus = 0 AND (importContenu = 0 OR importComposition = 0) ORDER BY id");
        foreach ($fichiers as $fichier) {
            $compteRendu['fichiers'][] = self::importerUnFichier((int)$fichier['id'], $types);
        }
        return $compteRendu;
    }

    public static function importerUnFichier(int $idFichier, ?array $types = null): array {
        $types ??= self::types();
        $fichier = Model::mdRequeteListerUnique("SELECT id, libelle, exclus FROM " . Fichier::TABLE . " WHERE id = ?",
                                                [$idFichier]);
        $resultat = ['id' => $idFichier, 'fichier' => $fichier['libelle'] ?? ("n°" . $idFichier), 'modele' => "",
                     'compteurs' => [], 'categories' => [], 'anomalies' => []];
        if (count($fichier) === 0) {
            $resultat['anomalies'][] = "Fichier inconnu";
            return $resultat;
        }
        if ((int)$fichier['exclus'] === 1) {
            $resultat['anomalies'][] = "Fichier exclu de l'import";
            return $resultat;
        }
        $chemin = Fichier::cheminComplet($idFichier);
        if ($chemin === "") {
            $resultat['anomalies'][] = "PCG introuvable sur le disque";
            return $resultat;
        }
        try {
            $lecture = LecteurPcg::lire($chemin);
        } catch (\Throwable $e) {
            $resultat['anomalies'][] = "PCG illisible : " . $e->getMessage();
            return $resultat;
        }
        $resultat['modele'] = $lecture['modele'] . " " . $lecture['os'];

        try {
            Model::mdTransaction(function () use ($lecture, $idFichier, $types, &$resultat): bool {
                $compteurs = ['crees' => 0, 'modifies' => 0, 'inchanges' => 0, 'supprimes' => 0, 'combis' => 0, 'liens' => 0];
                $idsConserves = [];
                foreach ($lecture['sons'] as $son) {
                    if (!isset($types[$son['type']])) {
                        $resultat['anomalies'][] = $son['banque'] . " " . $son['nom'] . " : type « " . $son['type']
                                                 . " » absent de la nomenclature TYPE";
                        continue;
                    }
                    $idSsCategorie = $son['categorie'] === null ? null
                            : Categorie::idSousCategorie($types[$son['type']], $son['categorie'], $son['sousCategorie'],
                                                  $resultat['categories']);
                    $idSon = 0;
                    $compteurs[Son::enregistrer($idFichier, $types[$son['type']], $son['banque'], $son['nom'],
                                                $idSsCategorie, $idSon)]++;
                    $idsConserves[] = $idSon;
                }
                if (count($resultat['anomalies']) > 0) {
                    throw new \RuntimeException(self::ANNULATION);     //tout ou rien
                }
                $compteurs['supprimes'] = Son::supprimerAbsents($idFichier, $idsConserves);
                [$compteurs['combis'], $compteurs['liens']] = self::remplacerLiens($idFichier, $lecture['compositions']);
                Model::mdRequeteExecuter("UPDATE " . Fichier::TABLE
                        . " SET importContenu = 1, importComposition = 1 WHERE id = :id", ['id' => $idFichier]);
                $resultat['compteurs'] = $compteurs;
                return true;
            });
        } catch (\Throwable $e) {
            Categorie::viderCache();
            $resultat['compteurs'] = [];
            $resultat['categories'] = [];
            if ($e->getMessage() !== self::ANNULATION) {
                error_log("ImportPcg " . $idFichier . " : " . $e->getMessage());
                $resultat['anomalies'][] = "Erreur : " . $e->getMessage();
            }
        }
        return $resultat;
    }

    private static function remplacerLiens(int $idFichier, array $compositions): array {
    //liens combi -> program du fichier, retourne [nombre de combis avec au moins un program, nombre de liens]
        Model::mdRequetePreparer("DELETE FROM " . self::TABLE_LIENS . " WHERE idFichier = ?", [$idFichier]);
        $combis = $liens = 0;
        foreach ($compositions as $banqueCombi => $programs) {
            if (count($programs) > 0) {$combis++;}
            foreach ($programs as $banqueProgram) {
                Model::mdInsert(self::TABLE_LIENS, ['idFichier' => $idFichier, 'banqueCombi' => $banqueCombi,
                                                    'banqueProgram' => $banqueProgram]);
                $liens++;
            }
        }
        return [$combis, $liens];
    }

    private static function types(): array {
    //nomenclature TYPE : valeurA (Program, Combi, WaveSequence, DrumKit, DrumPattern) => id
        $types = [];
        foreach (Nomenclature::mdNomenclatureGetListe(self::GROUPE_TYPE, "valeurA") as $ligne) {
            $types[trim((string)$ligne['zone'])] = (int)$ligne['identifiant'];
        }
        return $types;
    }
}
