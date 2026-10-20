<?php
declare(strict_types=1);
namespace mykronos\src\php\son;
/*******************************************************************************
 * Contrôleur son du domaine son : catégories et sous-catégories (table mk_cat_sscat)
 * appel : index.php?fct=xxx&dom=son&ctr=son  -> fonction ctXxx
 * Les catégories sont propres à un type (Program ou Combi) : id = idType (Program par défaut)
 ******************************************************************************/

function ctEditerCategories(int $id = 0, string $message = "", bool $succes = true): void {
//liste des catégories du type $id
    $types = Categorie::types();
    $idType = isset($types[$id]) ? $id : (int)(array_search("Program", $types, true) ?: array_key_first($types));
    $categories = Categorie::listerCategories($idType);
    require('tpCategories.php');
}

function ctEnregistrerCategories(int $id = 0): void {
    [$message, $succes] = messageEnregistrement(Categorie::enregistrer(Categorie::RACINE, lignesSaisies(), $id));
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
    [$message, $succes] = messageEnregistrement(Categorie::enregistrer($id, lignesSaisies()));
    ctEditerSsCategories($id, $message, $succes);
}

function lignesSaisies(): array {
//lignes[id][numero|libelle|fusion|type] du formulaire
    $lignes = $_POST['lignes'] ?? [];
    return is_array($lignes) ? array_filter($lignes, 'is_array') : [];
}

function messageEnregistrement(array $resultat): array {
//[message, succès] après enregistrement
    if (count($resultat['erreurs']) > 0) {
        return ["Rien n'a été enregistré : " . implode(" ; ", $resultat['erreurs']), false];
    }
    $parties = [];
    if ($resultat['fusions'] > 0) {$parties[] = $resultat['fusions'] . " fusion" . ($resultat['fusions'] > 1 ? "s" : "");}
    if ($resultat['modifies'] > 0) {$parties[] = $resultat['modifies'] . " ligne" . ($resultat['modifies'] > 1 ? "s" : "") . " modifiée" . ($resultat['modifies'] > 1 ? "s" : "");}
    return [count($parties) > 0 ? implode(", ", $parties) : "Aucune modification", true];
}
