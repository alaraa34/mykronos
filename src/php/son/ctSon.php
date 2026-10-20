<?php
declare(strict_types=1);
namespace mykronos\src\php\son;
/*******************************************************************************
 * Contrôleur son du domaine son : catégories et sous-catégories (table mk_cat_sscat)
 * appel : index.php?fct=xxx&dom=son&ctr=son  -> fonction ctXxx
 * Les catégories sont propres à un type (Program ou Combi) : id = idType (Program par défaut)
 ******************************************************************************/
use shared\php\database\Model           as Model;
use mykronos\src\php\fichier\Fichier     as Fichier;
use mykronos\src\php\fichier\LecteurPcg  as LecteurPcg;

function ctEditerCategories(int $id = 0, string $message = "", bool $succes = true): void {
//liste des catégories du type $id
    $types = Categorie::types();
    $idType = isset($types[$id]) ? $id : (int)(array_search("Program", $types, true) ?: array_key_first($types));
    $categories = Categorie::listerCategories($idType);
    $pcgKronos = pcgKronos();
    require('tpCategories.php');
}

function ctChargerCategoriesKronos(int $id = 0): void {
//noms et n° des catégories Program et Combi lus dans le Global d'un PCG exporté d'un Kronos (fromK2 / fromK3)
    set_time_limit(120);
    $chemin = Fichier::cheminComplet($id);
    if ($chemin === "") {
        ctEditerCategories(0, "Choisis un PCG exporté de ton Kronos", false);
        return;
    }
    try {
        $categories = LecteurPcg::lire($chemin)['categories'];
        if ($categories === null) {
            ctEditerCategories(0, "Ce PCG ne contient pas le Global (enregistrer le PCG avec Global Setting coché)", false);
            return;
        }
        $r = Categorie::chargerDepuisKronos($categories);
        $message = "Catégories du Kronos chargées : " . $r['creees'] . " créées, " . $r['renommees'] . " renommées, "
                 . $r['inchangees'] . " inchangées, " . $r['supprimees'] . " supprimées (absentes du Kronos)";
        if (count($r['conservees']) > 0) {
            $message .= " - conservées car elles contiennent des sons : " . implode(", ", $r['conservees']);
        }
        ctEditerCategories(0, $message, count($r['conservees']) === 0);
    } catch (\Throwable $e) {
        error_log("ctChargerCategoriesKronos : " . $e->getMessage());
        ctEditerCategories(0, "Chargement impossible : " . $e->getMessage(), false);
    }
}

function pcgKronos(): array {
//PCG exportés des Kronos (typologies fromK2 / fromK3), les plus récents d'abord : id => libellé
    $lignes = Model::mdRequeteLister(
        "SELECT f.id, f.libelle, f.dateFichier, n.nom AS typologie FROM " . Fichier::TABLE . " f"
      . " JOIN " . PREFIXE_BDD . "nomenclature n ON n.id = f.typologie"
      . " WHERE n.groupe = ? AND n.nom LIKE 'from%' AND f.exclus = 0 ORDER BY f.dateFichier DESC, f.id DESC",
        [Fichier::GROUPE_TYPOLOGIE]);
    $liste = [];
    foreach ($lignes as $l) {
        $liste[(int)$l['id']] = $l['typologie'] . " - " . $l['libelle'] . " (" . date("d/m/Y", strtotime((string)$l['dateFichier'])) . ")";
    }
    return $liste;
}

function ctEnregistrerCategories(int $id = 0): void {
    [$message, $succes] = messageEnregistrement(Categorie::renommer(Categorie::RACINE, lignesSaisies(), $id));
    ctEditerCategories($id, $message, $succes);
}

function ctEditerSsCategories(int $id = 0, string $message = "", bool $succes = true): void {
//sous-catégories de la catégorie $id (première catégorie Program si non fournie)
    if ($id === 0) {
        $types = Categorie::types();
        $premieres = Categorie::listerCategories((int)(array_search("Program", $types, true) ?: array_key_first($types)));
        $id = count($premieres) > 0 ? (int)$premieres[0]['id'] : 0;
    }
    $categorie = Categorie::detail($id);
    if (count($categorie) === 0 || (int)$categorie['idCategorie'] !== Categorie::RACINE) {
        ctEditerCategories(0, "Catégorie inconnue", false);
        return;
    }
    $idType = (int)$categorie['idType'];
    $categories = Categorie::listerCategories($idType);
    $sousCategories = Categorie::listerSousCategories($id);
    require('tpSsCategories.php');
}

function ctEnregistrerSsCategories(int $id = 0): void {
    [$message, $succes] = messageEnregistrement(Categorie::renommer($id, lignesSaisies()));
    ctEditerSsCategories($id, $message, $succes);
}

function lignesSaisies(): array {
//lignes[id][libelle] du formulaire
    $lignes = $_POST['lignes'] ?? [];
    return is_array($lignes) ? array_filter($lignes, 'is_array') : [];
}

function messageEnregistrement(array $resultat): array {
//[message, succès] après enregistrement des libellés
    if (count($resultat['erreurs']) > 0) {
        return ["Rien n'a été enregistré : " . implode(" ; ", $resultat['erreurs']), false];
    }
    $n = $resultat['modifies'];
    return [$n === 0 ? "Aucune modification" : $n . " libellé" . ($n > 1 ? "s" : "") . " enregistré" . ($n > 1 ? "s" : ""), true];
}
