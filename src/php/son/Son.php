<?php
declare(strict_types=1);
namespace mykronos\src\php\son;

/*******************************************************************************
 * Son extrait d'un fichier PCG (table mk_son)
 * Un son est identifié par idFichier + idType + banque
 ******************************************************************************/
use shared\php\database\Model     as Model;
use shared\php\database\Database  as Database;

class Son
{
    public const TABLE = PREFIXE_BDD . "son";

    public static function enregistrer(int $idFichier, int $idType, string $banque, string $nom, ?int $idSsCategorie,
                                       array $attributs = [], int &$id = 0): string {
    //création ou mise à jour, retourne crees, modifies ou inchanges - $id reçoit l'id du son
    //$attributs : favori (bool), moteur (HD-1 / EXi), tempo (BPM)
        $valeurs = ['nom' => $nom, 'idSsCategorie' => $idSsCategorie,
                    'favori' => (bool)($attributs['favori'] ?? false), 'moteur' => $attributs['moteur'] ?? null,
                    'tempo' => isset($attributs['tempo']) ? round((float)$attributs['tempo'], 2) : null];
        $existant = Model::mdRequeteListerUnique(
                "SELECT id, nom, idSsCategorie, favori, moteur, tempo FROM " . self::TABLE
              . " WHERE idFichier = ? AND idType = ? AND banque = ?",
                [$idFichier, $idType, $banque]);

        if (count($existant) === 0) {
            Model::mdInsert(self::TABLE, array_merge(['idFichier' => $idFichier, 'idType' => $idType, 'banque' => $banque], $valeurs));
            $id = (int)Database::dbConnect()->lastInsertId();
            return 'crees';
        }
        $id = (int)$existant['id'];
        $identique = (string)$existant['nom'] === $nom
                  && ($existant['idSsCategorie'] === null ? null : (int)$existant['idSsCategorie']) === $idSsCategorie
                  && (bool)$existant['favori'] === $valeurs['favori']
                  && $existant['moteur'] === $valeurs['moteur']
                  && ($existant['tempo'] === null ? null : round((float)$existant['tempo'], 2)) === $valeurs['tempo'];
        if ($identique) {
            return 'inchanges';
        }
        Model::mdUpdate(self::TABLE, $valeurs, "id=" . $id);
        return 'modifies';
    }

    public static function supprimerAbsents(int $idFichier, array $idsConserves): int {
    //supprime les sons du fichier qui ne sont plus dans le PCG réimporté, retourne le nombre supprimé
        $requete = "DELETE FROM " . self::TABLE . " WHERE idFichier = ?";
        if (count($idsConserves) > 0) {
            $requete .= " AND id NOT IN (" . implode(",", array_map('intval', $idsConserves)) . ")";
        }
        return Model::mdRequetePreparer($requete, [$idFichier])->rowCount();
    }
}
