<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Contrôleur son du domaine fichier : import des XML déposés dans fichiers/output
 * appel : index.php?fct=xxx&dom=fichier&ctr=son  -> fonction ctXxx
 ******************************************************************************/

function ctImporterFichiersOutput(): void {
//XML de contenu (<patch_list>) -> mk_son ; la composition viendra ensuite
    try {
        $compteRendu = ImportContenu::importer();
    } catch (\Throwable $e) {
        error_log("ctImporterFichiersOutput : " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")");
        $compteRendu = ['fichiers' => [], 'ignores' => [], 'anomalies' => ["Import interrompu : " . $e->getMessage()]];
    }
    require('tpImportContenu.php');
}
