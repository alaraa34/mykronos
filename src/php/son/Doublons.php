<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Doublons sur mes Kronos
 *  - pour chaque Kronos (dernier export importé de fromK2, de fromK3), les Programs ou Combis
 *    présents sous le même nom dans plusieurs banques de CE Kronos
 *  - un son présent une fois sur K2 et une fois sur K3 n'est pas un doublon
 *  - comparaison des noms sans majuscules ni espaces de fin (comme la recherche)
 ******************************************************************************/
use shared\php\database\Model as Model;
use mykronos\src\php\fichier\Fichier as Fichier;

class Doublons
{
    public static function detecter(): array {
    //['K2' => ['fichier' => libellé, 'doublons' => [...]], 'K3' => ...]
    //chaque doublon : type, nom, exemplaires => [banque, categorie, favori, setlists => [...], combis => [...] (Programs : Combis qui l utilisent)]
        $types = array_flip(Categorie::types());
        $ordreTypes = [$types['Program'] ?? 0 => 0, $types['Combi'] ?? 0 => 1];
        $resultat = [];
        foreach (Recherche::exportsKronos() as $machine => $idFichier) {
            $fichier = Model::mdRequeteListerUnique("SELECT libelle FROM " . Fichier::TABLE . " WHERE id = ?", [$idFichier]);

            //emplacements de set lists de ce Kronos : idType / banque => libellés
            $slots = [];
            foreach (Model::mdRequeteLister(
                    "SELECT sl.numero AS numeroSetlist, sl.nom AS setlist, s.numero, s.idType, s.banque"
                  . " FROM " . Recherche::TABLE_SLOTS . " s JOIN " . Recherche::TABLE_SETLISTS . " sl ON sl.id = s.idSetlist"
                  . " WHERE sl.idFichier = ? AND s.banque IS NOT NULL ORDER BY sl.numero, s.numero", [$idFichier]) as $s) {
                $slots[(int)$s['idType']][$s['banque']][] = "Set list " . $s['numeroSetlist'] . " " . $s['setlist'] . " / slot " . $s['numero'];
            }

            //Combis de ce Kronos qui utilisent chaque banque de Program : banque => ["U-B058 Nom (timbres 1, 2)", ...]
            $combis = [];
            foreach (Model::mdRequeteLister(
                    "SELECT l.banqueProgram, l.banqueCombi, cb.nom, GROUP_CONCAT(l.timbre ORDER BY l.timbre SEPARATOR ', ') AS timbres"
                  . " FROM " . Recherche::TABLE_LIENS . " l"
                  . " LEFT JOIN " . Son::TABLE . " cb ON cb.idFichier = l.idFichier AND cb.banque = l.banqueCombi AND cb.idType = ?"
                  . " WHERE l.idFichier = ? GROUP BY l.banqueProgram, l.banqueCombi, cb.nom ORDER BY l.banqueCombi",
                    [$types['Combi'] ?? 0, $idFichier]) as $l) {
                $combis[$l['banqueProgram']][] = $l['banqueCombi'] . " " . rtrim((string)$l['nom'])
                                              . " (timbre" . (str_contains((string)$l['timbres'], ",") ? "s " : " ") . $l['timbres'] . ")";
            }

            //sons de ce Kronos regroupés par type et nom
            $groupes = [];
            foreach (Model::mdRequeteLister(
                    "SELECT so.idType, so.nom, so.banque, so.favori, t.valeurA AS type, c.libelle AS categorie, sc.libelle AS sousCategorie"
                  . " FROM " . Son::TABLE . " so"
                  . " JOIN " . PREFIXE_BDD . "nomenclature t ON t.id = so.idType"
                  . " LEFT JOIN " . Categorie::TABLE . " sc ON sc.id = so.idSsCategorie"
                  . " LEFT JOIN " . Categorie::TABLE . " c ON c.id = sc.idCategorie"
                  . " WHERE so.idFichier = ? AND so.idType IN (?, ?) ORDER BY so.nom, so.banque",
                    [$idFichier, $types['Program'] ?? 0, $types['Combi'] ?? 0]) as $son) {
                $idType = (int)$son['idType'];
                $cle = $idType . "|" . mb_strtolower(rtrim((string)$son['nom']));
                $groupes[$cle] ??= ['idType' => $idType, 'type' => $son['type'], 'nom' => rtrim((string)$son['nom']), 'exemplaires' => []];
                $groupes[$cle]['exemplaires'][] = [
                    'banque'    => $son['banque'],
                    'categorie' => $son['categorie'] === null ? "" : $son['categorie'] . " / " . $son['sousCategorie'],
                    'favori'    => (bool)$son['favori'],
                    'setlists'  => $slots[$idType][$son['banque']] ?? [],
                    'combis'    => $idType === ($types['Program'] ?? 0) ? ($combis[$son['banque']] ?? []) : [],
                ];
            }
            $doublons = array_values(array_filter($groupes, fn($g) => count($g['exemplaires']) > 1));
            usort($doublons, fn($a, $b) => [$ordreTypes[$a['idType']] ?? 9, mb_strtolower($a['nom'])]
                                       <=> [$ordreTypes[$b['idType']] ?? 9, mb_strtolower($b['nom'])]);
            $resultat[$machine] = ['fichier' => (string)($fichier['libelle'] ?? ""), 'doublons' => $doublons];
        }
        ksort($resultat);
        return $resultat;
    }
}
