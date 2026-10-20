<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;

/*******************************************************************************
 * Import direct des fichiers PCG (sans passer par les XML de PCG Tools)
 * Pour chaque fichier non exclu dont le contenu ou la composition reste à importer :
 *  - lecture du PCG (LecteurPcg)
 *  - sons : création / mise à jour (clé idFichier + type + banque), suppression des sons disparus
 *    catégorie et sous-catégorie (Programs et Combis, chacun sa liste) via la table récursive, créées « à renseigner » si besoin
 *    avec favori, moteur (Programs) et tempo (Combis)
 *  - composition : les timbres des combis (program, zone de clavier, vélocité) du fichier sont remplacés
 *  - set lists : celles du fichier sont remplacées (emplacements renseignés seulement)
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
    public const TABLE_SETLISTS = PREFIXE_BDD . "setlist";
    public const TABLE_SLOTS    = PREFIXE_BDD . "setlist_slot";
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
                $compteurs = ['crees' => 0, 'modifies' => 0, 'inchanges' => 0, 'supprimes' => 0, 'combis' => 0, 'liens' => 0,
                              'setlists' => 0, 'slots' => 0];
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
                    $compteurs[Son::enregistrer($idFichier, $types[$son['type']], $son['banque'], $son['nom'], $idSsCategorie,
                                                ['favori' => $son['favori'], 'moteur' => $son['moteur'], 'tempo' => $son['tempo']],
                                                $idSon)]++;
                    $idsConserves[] = $idSon;
                }
                if (count($resultat['anomalies']) > 0) {
                    throw new \RuntimeException(self::ANNULATION);     //tout ou rien
                }
                $compteurs['supprimes'] = Son::supprimerAbsents($idFichier, $idsConserves);
                [$compteurs['combis'], $compteurs['liens']] = self::remplacerLiens($idFichier, $lecture['compositions']);
                [$compteurs['setlists'], $compteurs['slots']] = self::remplacerSetlists($idFichier, $lecture['setlists'], $types);
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
    //timbres des combis du fichier (un lien par timbre actif), retourne [nombre de combis avec au moins un timbre, nombre de liens]
        Model::mdRequetePreparer("DELETE FROM " . self::TABLE_LIENS . " WHERE idFichier = ?", [$idFichier]);
        $combis = $liens = 0;
        foreach ($compositions as $banqueCombi => $timbres) {
            if (count($timbres) > 0) {$combis++;}
            foreach ($timbres as $t) {
                Model::mdInsert(self::TABLE_LIENS, ['idFichier' => $idFichier, 'banqueCombi' => $banqueCombi,
                        'banqueProgram' => $t['banque'], 'timbre' => $t['timbre'], 'canal' => $t['canal'],
                        'noteBasse' => $t['noteBasse'], 'noteHaute' => $t['noteHaute'],
                        'veloBasse' => $t['velociteBasse'], 'veloHaute' => $t['velociteHaute'], 'volume' => $t['volume']]);
                $liens++;
            }
        }
        return [$combis, $liens];
    }

    private static function remplacerSetlists(int $idFichier, array $setlists, array $types): array {
    //set lists du fichier (les emplacements suivent par cascade), retourne [nombre de set lists, nombre d'emplacements]
        Model::mdRequetePreparer("DELETE FROM " . self::TABLE_SETLISTS . " WHERE idFichier = ?", [$idFichier]);
        $nbSlots = 0;
        foreach ($setlists as $setlist) {
            Model::mdInsert(self::TABLE_SETLISTS, ['idFichier' => $idFichier, 'numero' => $setlist['numero'], 'nom' => $setlist['nom']]);
            $idSetlist = (int)\shared\php\database\Database::dbConnect()->lastInsertId();
            foreach ($setlist['slots'] as $slot) {
                Model::mdInsert(self::TABLE_SLOTS, ['idSetlist' => $idSetlist, 'numero' => $slot['numero'], 'nom' => $slot['nom'],
                        'idType' => $slot['type'] !== null ? ($types[$slot['type']] ?? null) : null, 'banque' => $slot['banque'],
                        'volume' => $slot['volume'], 'description' => $slot['description']]);
                $nbSlots++;
            }
        }
        return [count($setlists), $nbSlots];
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
