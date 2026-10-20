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

    public static function enregistrer(int $idFichier, int $idType, string $banque,
                                       string $nom, ?int $idSsCategorie, int &$id = 0): string {
    //création ou mise à jour, retourne crees, modifies ou inchanges - $id reçoit l'id du son
        $existant = Model::mdRequeteListerUnique(
                "SELECT id, nom, idSsCategorie FROM " . self::TABLE
              . " WHERE idFichier = ? AND idType = ? AND banque = ?",
                [$idFichier, $idType, $banque]);

        if (count($existant) === 0) {
            Model::mdInsert(self::TABLE, ['idFichier' => $idFichier, 'idType' => $idType, 'banque' => $banque,
                                          'nom' => $nom, 'idSsCategorie' => $idSsCategorie]);
            $id = (int)Database::dbConnect()->lastInsertId();
            return 'crees';
        }
        $id = (int)$existant['id'];
        $categorieExistante = $existant['idSsCategorie'] === null ? null : (int)$existant['idSsCategorie'];
        if ((string)$existant['nom'] === $nom && $categorieExistante === $idSsCategorie) {
            return 'inchanges';
        }
        Model::mdUpdate(self::TABLE, ['nom' => $nom, 'idSsCategorie' => $idSsCategorie], "id=" . (int)$existant['id']);
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
