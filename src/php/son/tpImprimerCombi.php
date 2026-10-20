<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Fiche imprimable d'une combi (page seule, sans menu)
 * Variables attendues : $combi (voir Recherche::combi), $resultat (avec 'kronos')
 ******************************************************************************/
$e = fn($valeur) => htmlspecialchars((string)$valeur);
$machines = ['K2', 'K3'];
$tempo = $combi['tempo'] !== null && (float)$combi['tempo'] > 0
       ? rtrim(rtrim(number_format((float)$combi['tempo'], 2, ',', ''), '0'), ',') . " BPM" : "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($combi['nom']) ?> - myKronos</title>
<?php include_once (ROOT_PATH . 'shared/includes/bootstrap_jquery_css.php'); ?>
<link rel="stylesheet" href="src/css/mykronos.css?v=<?= @filemtime(__DIR__ . '/../../css/mykronos.css') ?>" type="text/css">
<style>
    body { background: #fff; }
    .fiche { max-width: 60rem; }
    @media print {
        @page { margin: 12mm; }
        .fiche { max-width: none; }
        a { color: inherit; text-decoration: none; }
        .badge { border: 1px solid #6c757d; }
    }
</style>
</head>
<body>
<div class="container fiche py-3">
    <div class="d-flex justify-content-end gap-2 mb-3 d-print-none">
        <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.close()">Fermer</button>
    </div>

    <h1 class="h3 mb-1"><?php if ((int)$combi['favori'] === 1): ?><i class="bi bi-star-fill favori"></i> <?php endif; ?><?= $e($combi['nom']) ?></h1>
    <div class="mb-3 text-muted">
        Combi <?= $e($combi['banque']) ?>
        <?= $combi['categorie'] !== null ? ' · ' . $e($combi['categorie']) . ' / ' . $e($combi['sousCategorie']) : '' ?>
        <?= $tempo !== '' ? ' · ' . $e($tempo) : '' ?>
        <?= ($combi['disposition'] ?? '') !== '' ? ' · ' . $e($combi['disposition']) : '' ?>
    </div>

    <table class="table table-sm table-bordered w-auto mb-3 small">
        <tr><th class="table-light">PCG</th><td><?= $e($combi['fichier']) ?> <span class="text-muted">(<?= $e($combi['typologie'] ?? '?') ?>)</span></td></tr>
        <?php foreach ($machines as $m): ?>
        <tr><th class="table-light"><?= $m ?></th>
            <td><?php if (!isset($resultat['kronos'][$m])): ?><span class="text-muted">aucun export importé</span>
                <?php elseif (count($combi['kronos'][$m] ?? []) === 0): ?>absente
                <?php else: ?><strong><?= $e(implode(", ", $combi['kronos'][$m])) ?></strong><?php endif; ?></td></tr>
        <?php endforeach; ?>
    </table>

    <?php include('tpComposition.php'); ?>

    <?php if (count($combi['setlists'] ?? []) > 0): ?>
    <h2 class="h6 mt-4">Appelée dans les set lists</h2>
    <ul class="small mb-0">
        <?php foreach ($combi['setlists'] as $slot): ?>
            <li><?= $e($slot['machine']) ?> · <?= $e($slot['setlist']) ?> · n°<?= (int)$slot['numero'] ?>
                <?= $slot['nom'] !== '' ? '« ' . $e($slot['nom']) . ' »' : '' ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p class="small text-muted mt-4 mb-0">myKronos · <?= date("d/m/Y") ?></p>
</div>
</body>
</html>
