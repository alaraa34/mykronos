<?php
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Compte rendu de la détection des fichiers PCG
 * Variables attendues : $compteRendu (voir Fichier::detecter), $aSupprimer, $extensions, $suppression (null ou résultat)
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$rubriques = [
    'ajoutes'   => ['Ajoutés',   'success'],
    'modifies'  => ['Date modifiée (imports réinitialisés)', 'warning'],
    'renommes'  => ['Exports Kronos renommés', 'primary'],
    'anomalies' => ['Anomalies', 'danger'],
    'inchanges' => ['Inchangés', 'secondary'],
];
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Détection des fichiers PCG</h2>
    <div class="row g-2 mb-4">
        <?php foreach ($rubriques as $cle => [$titre, $couleur]): ?>
        <div class="col-6 col-md">
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

    <?php $mo = fn(int $octets): string => number_format($octets / 1048576, 1, ',', ' ') . " Mo"; ?>
    <?php if ($suppression !== null): ?>
        <div class="alert alert-<?= count($suppression['erreurs']) === 0 ? 'success' : 'warning' ?> py-2">
            <?= (int)$suppression['supprimes'] ?> fichier<?= $suppression['supprimes'] > 1 ? 's' : '' ?> inutile<?= $suppression['supprimes'] > 1 ? 's' : '' ?> supprimé<?= $suppression['supprimes'] > 1 ? 's' : '' ?>
            (<?= $mo((int)$suppression['octets']) ?> libérés), <?= (int)$suppression['dossiers'] ?> dossier<?= $suppression['dossiers'] > 1 ? 's' : '' ?> vide<?= $suppression['dossiers'] > 1 ? 's' : '' ?> retiré<?= $suppression['dossiers'] > 1 ? 's' : '' ?>.
            <?php foreach ($suppression['erreurs'] as $erreur): ?><div class="small"><?= $e($erreur) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="fw-semibold mb-1">Fichiers inutiles pour le Kronos</div>
            <?php $raisons = array_merge($extensions, ["PCG Nautilus"]); ?>
            <?php if (count($aSupprimer) === 0): ?>
                <p class="small text-muted mb-0">Aucun fichier à supprimer (<?= $e(implode(", ", $raisons)) ?>) dans fichiers/pcg.</p>
            <?php else: ?>
                <details class="small mb-2">
                    <?php $parRaison = array_count_values(array_column($aSupprimer, 'raison')); ?>
                    <summary><?= count($aSupprimer) ?> fichier<?= count($aSupprimer) > 1 ? 's' : '' ?>,
                        <?= $mo(array_sum(array_column($aSupprimer, 'taille'))) ?> :
                        <?= $e(implode(", ", array_map(fn($r, $n) => $n . " " . $r, array_keys($parRaison), $parRaison))) ?></summary>
                    <ul class="mt-1 mb-0">
                        <?php foreach ($aSupprimer as $f): ?><li><?= $e($f['emplacement']) ?> <span class="text-muted">(<?= $e($f['raison']) ?>, <?= $mo($f['taille']) ?>)</span></li><?php endforeach; ?>
                    </ul>
                </details>
                <form method="post" action="<?= TbAdressage::getURLstatic('fichier', 'supprimerFichiersInutiles') ?>"
                      data-attente="Suppression des fichiers inutiles…"
                      onsubmit="return confirm('Supprimer définitivement <?= count($aSupprimer) ?> fichier(s) ? Seuls les PCG de Nautilus sont supprimés parmi les PCG ; les fichiers d\'échantillons ne sont jamais supprimés.');">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Supprimer ces fichiers</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <a class="btn btn-dark btn-sm" href="<?= TbAdressage::getURLstatic('fichier', 'detecterFichier') ?>">Relancer la détection</a>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
