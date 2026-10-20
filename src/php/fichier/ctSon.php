<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Contrôleur son du domaine fichier : import direct des PCG (contenu + composition)
 * appel : index.php?fct=xxx&dom=fichier&ctr=son  -> fonction ctXxx
 ******************************************************************************/

function ctImporterPcg(): void {
//importe tous les fichiers à traiter (non exclus, contenu ou composition à 0)
    set_time_limit(600);
    try {
        $compteRendu = ImportPcg::importerATraiter();
    } catch (\Throwable $e) {
        error_log("ctImporterPcg : " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")");
        $compteRendu = ['fichiers' => [], 'anomalies' => ["Import interrompu : " . $e->getMessage()]];
    }
    require('tpImportPcg.php');
}

function ctImporterPcgFichier(int $id = 0): void {
//importe (ou réimporte) un seul fichier, appel ...&fct=importerPcgFichier&id=xx
    $compteRendu = ['fichiers' => [ImportPcg::importerUnFichier($id)], 'anomalies' => []];
    require('tpImportPcg.php');
}

function ctImporterFichiersOutput(): void {
//ancienne entrée du menu (fct=importerFichiersOutput) : redirigée vers l'import direct des PCG
    ctImporterPcg();
}
