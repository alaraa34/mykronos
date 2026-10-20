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

    <?php
    $mo = fn(int $octets): string => number_format($octets / 1048576, 1, ',', ' ') . " Mo";
    //affiche une liste de fichiers (emplacement, taille, raison) regroupée par cause : PCG Nautilus d'abord, puis les extensions
    $afficherParCause = function (array $liste) use ($e, $mo): void {
        $groupes = [];
        foreach ($liste as $f) {$groupes[$f['raison']][] = $f;}
        uksort($groupes, fn($a, $b) => [$a !== "Nautilus", $a] <=> [$b !== "Nautilus", $b]);
        foreach ($groupes as $raison => $fichiers): ?>
        <details class="small mb-2">
            <summary><span class="fw-semibold"><?= $e($raison === "Nautilus" ? "PCG Nautilus" : "Extension ." . $raison) ?></span>
                <span class="badge text-bg-secondary"><?= count($fichiers) ?></span>
                <span class="text-muted"><?= $mo(array_sum(array_column($fichiers, 'taille'))) ?></span></summary>
            <ul class="mt-1 mb-0">
                <?php foreach ($fichiers as $f): ?><li><?= $e($f['emplacement']) ?> <span class="text-muted">(<?= $mo($f['taille']) ?>)</span></li><?php endforeach; ?>
            </ul>
        </details>
        <?php endforeach;
    };
    ?>
    <?php if ($suppression !== null): ?>
        <div class="alert alert-<?= count($suppression['erreurs']) === 0 ? 'success' : 'warning' ?> py-2">
            <div class="mb-2"><?= (int)$suppression['supprimes'] ?> fichier<?= $suppression['supprimes'] > 1 ? 's' : '' ?> inutile<?= $suppression['supprimes'] > 1 ? 's' : '' ?> supprimé<?= $suppression['supprimes'] > 1 ? 's' : '' ?>
            (<?= $mo((int)$suppression['octets']) ?> libérés), <?= (int)$suppression['dossiers'] ?> dossier<?= $suppression['dossiers'] > 1 ? 's' : '' ?> vide<?= $suppression['dossiers'] > 1 ? 's' : '' ?> retiré<?= $suppression['dossiers'] > 1 ? 's' : '' ?>.</div>
            <?php $afficherParCause($suppression['fichiers'] ?? []); ?>
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
                <p class="small mb-2"><?= count($aSupprimer) ?> fichier<?= count($aSupprimer) > 1 ? 's' : '' ?>,
                    <?= $mo(array_sum(array_column($aSupprimer, 'taille'))) ?> au total</p>
                <?php $afficherParCause($aSupprimer); ?>
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
