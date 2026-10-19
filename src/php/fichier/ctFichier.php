<?php
declare(strict_types=1);
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Contrôleur des fichiers PCG
 * appel : index.php?fct=xxx&dom=fichier&ctr=fichier  -> fonction ctXxx
 ******************************************************************************/

function ctDetecterFichier(): void {
//inscrit dans mk_fichier les PCG présents dans fichiers/pcg/<typologie>
    try {
        $compteRendu = Fichier::detecter();
    } catch (\Throwable $e) {
        error_log("ctDetecterFichier : " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")");
        $compteRendu = ['ajoutes' => [], 'modifies' => [], 'inchanges' => [],
                        'anomalies' => ["Détection interrompue : " . $e->getMessage()]];
    }
    require('tpDetection.php');
}

function ctAfficherFichierATraiter(): void {
//liste des fichiers dont importContenu ou importComposition est à 0
    $fichiers = Fichier::listerATraiter();
    require('tpFichierATraiter.php');
}

function ctExclureFichier(): void {
//appel AJAX depuis tpFichierATraiter : POST id, exclus (0/1) - réponse JSON {ok:bool}
    $id     = (int)filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $exclus = filter_input(INPUT_POST, 'exclus', FILTER_VALIDATE_BOOLEAN);
    $ok = $id > 0 && Fichier::exclure($id, (bool)$exclus);
    while (ob_get_level() > 0) {ob_end_clean();}
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok]);
    exit;
}

function ctTelechargerFichier(int $id = 0): void {
//envoie le PCG au navigateur, appel index.php?fct=telechargerFichier&dom=fichier&ctr=fichier&id=xx
    $chemin = Fichier::cheminComplet($id);
    if ($chemin === "" || Fichier::estExclu($id)) {
        http_response_code(404);
        die("Fichier introuvable ou exclu");
    }
    while (ob_get_level() > 0) {ob_end_clean();}
    $nom = Fichier::nomAvecId($id, basename($chemin));     //ex 0012_JMJ.PCG
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($chemin));
    header("Content-Disposition: attachment; filename=\"" . str_replace('"', '', $nom) . "\"; filename*=UTF-8''" . rawurlencode($nom));
    readfile($chemin);
    exit;
}
