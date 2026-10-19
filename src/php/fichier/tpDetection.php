<?php
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Compte rendu de la détection des fichiers PCG
 * Variable attendue : $compteRendu (voir Fichier::detecter)
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$rubriques = [
    'ajoutes'   => ['Ajoutés',   'success'],
    'modifies'  => ['Date modifiée (imports réinitialisés)', 'warning'],
    'anomalies' => ['Anomalies', 'danger'],
    'inchanges' => ['Inchangés', 'secondary'],
];
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Détection des fichiers PCG</h2>
    <div class="row g-2 mb-4">
        <?php foreach ($rubriques as $cle => [$titre, $couleur]): ?>
        <div class="col-6 col-md-3">
            <div class="border border-<?= $couleur ?> rounded p-2 text-center">
                <div class="fs-3 fw-bold text-<?= $couleur ?>"><?= count($compteRendu[$cle]) ?></div>
                <div class="small"><?= $titre ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($rubriques as $cle => [$titre, $couleur]):
        if (count($compteRendu[$cle]) === 0) {continue;} ?>
    <details class="mb-3" <?= $cle !== 'inchanges' ? 'open' : '' ?>>
        <summary class="fw-semibold text-<?= $couleur ?>"><?= $titre ?> (<?= count($compteRendu[$cle]) ?>)</summary>
        <table class="table table-sm table-striped mt-2">
            <?php foreach ($compteRendu[$cle] as $ligne): ?>
            <tr>
                <?php if (is_array($ligne)): ?>
                <td><?= $e($ligne['emplacement']) ?></td>
                <td class="text-nowrap"><?= $e(date("d/m/Y", strtotime($ligne['date']))) ?></td>
                <?php else: ?>
                <td colspan="2"><?= $e($ligne) ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </details>
    <?php endforeach; ?>

    <a class="btn btn-dark btn-sm" href="<?= TbAdressage::getURLstatic('fichier', 'detecterFichier') ?>">Relancer la détection</a>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
