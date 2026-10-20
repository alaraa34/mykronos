<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Doublons de Programs et Combis sur chacun de mes Kronos
 * Variable attendue : $doublons (voir Doublons::detecter)
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-1">Sons en double sur mes Kronos</h2>
    <p class="small text-muted mb-3">Programs et Combis présents sous le même nom dans plusieurs banques d'un même Kronos
        (dernier export importé). Un son présent sur K2 et sur K3 n'est pas compté.</p>

    <?php if (count($doublons) === 0): ?>
        <div class="alert alert-warning py-2">Aucun export de Kronos importé (fromK2 / fromK3).</div>
    <?php endif; ?>

    <?php foreach ($doublons as $machine => $kronos):
        $parType = array_count_values(array_column($kronos['doublons'], 'type')); ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold"><?= $e($machine) ?></span>
            <span class="small text-muted"><?= $e($kronos['fichier']) ?></span>
            <span class="ms-auto small">
                <?php if (count($kronos['doublons']) === 0): ?>aucun doublon
                <?php else: ?><?= $e(implode(", ", array_map(fn($t, $n) => $n . " " . $t . ($n > 1 ? "s" : ""), array_keys($parType), $parType))) ?> en double<?php endif; ?>
            </span>
        </div>
        <?php if (count($kronos['doublons']) > 0): ?>
        <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Type</th><th>Nom</th><th>Banque</th><th>Catégorie</th><th>Set lists</th></tr></thead>
            <tbody>
            <?php foreach ($kronos['doublons'] as $d):
                $n = count($d['exemplaires']);
                foreach ($d['exemplaires'] as $i => $ex): ?>
                <tr class="<?= $i === $n - 1 ? 'border-bottom border-secondary' : '' ?>">
                    <?php if ($i === 0): ?>
                    <td rowspan="<?= $n ?>" class="small"><?= $e($d['type']) ?></td>
                    <td rowspan="<?= $n ?>" class="fw-semibold">
                        <a href="<?= TbAdressage::getURLstatic('son', 'rechercherSon', 0, 'recherche') ?>&q=<?= urlencode($d['nom']) ?>&typologie=<?= Recherche::KRONOS ?>"
                           title="Rechercher ce son"><?= $e($d['nom']) ?></a></td>
                    <?php endif; ?>
                    <td class="text-nowrap"><?= $e($ex['banque']) ?>
                        <?php if ($ex['favori']): ?><i class="bi bi-star-fill text-warning" title="Favori"></i><?php endif; ?></td>
                    <td class="small"><?= $e($ex['categorie']) ?></td>
                    <td class="small">
                        <?php if (count($ex['setlists']) === 0): ?><span class="text-muted">—</span>
                        <?php else: ?><?= implode("<br>", array_map($e, $ex['setlists'])) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
