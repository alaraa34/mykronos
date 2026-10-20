<?php
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Compte rendu de l'import des XML de contenu
 * Variable attendue : $compteRendu (voir ImportContenu::importer)
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Import des fichiers output</h2>

    <?php foreach ($compteRendu['anomalies'] as $anomalie): ?>
        <div class="alert alert-danger py-2"><?= $e($anomalie) ?></div>
    <?php endforeach; ?>

    <?php if (count($compteRendu['fichiers']) === 0 && count($compteRendu['anomalies']) === 0): ?>
        <p class="text-muted">Aucun XML de contenu à importer dans fichiers/output.</p>
    <?php endif; ?>

    <?php foreach ($compteRendu['fichiers'] as $f): $ok = count($f['anomalies']) === 0; ?>
    <div class="card mb-3 border-<?= $ok ? 'success' : 'warning' ?>">
        <div class="card-header d-flex justify-content-between flex-wrap gap-2">
            <span class="fw-semibold"><?= $e($f['fichier']) ?></span>
            <span class="small text-muted"><?= $e($f['xml']) ?></span>
        </div>
        <div class="card-body py-2">
            <span class="badge text-bg-success">Créés <?= $f['crees'] ?></span>
            <span class="badge text-bg-primary">Modifiés <?= $f['modifies'] ?></span>
            <span class="badge text-bg-secondary">Inchangés <?= $f['inchanges'] ?></span>
            <span class="badge text-bg-danger">Supprimés <?= $f['supprimes'] ?></span>
            <?php if ($ok): ?>
                <span class="badge text-bg-dark">Contenu importé</span>
            <?php endif; ?>
            <?php if (count($f['categories']) > 0): ?>
                <div class="mt-2 small"><strong>Créées « à renseigner » :</strong>
                    <?= $e(implode(" ; ", $f['categories'])) ?></div>
            <?php endif; ?>
            <?php foreach ($f['anomalies'] as $anomalie): ?>
                <div class="mt-1 small text-danger"><?= $e($anomalie) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($compteRendu['ignores']) > 0): ?>
    <details class="mb-3">
        <summary class="text-secondary">XML ignorés (<?= count($compteRendu['ignores']) ?>)</summary>
        <ul class="small mt-2">
            <?php foreach ($compteRendu['ignores'] as $ignore): ?><li><?= $e($ignore) ?></li><?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>

    <a class="btn btn-dark btn-sm" href="<?= TbAdressage::getURLstatic('fichier', 'afficherFichierATraiter') ?>">Fichiers à traiter</a>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
