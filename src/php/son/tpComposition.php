<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Fragment : composition d'une combi (programs, banque d'origine, banques sur K2 / K3)
 * Inclus par tpRecherche et tpRechercheCombinaison
 * Variables attendues : $combi (avec 'programs', voir Recherche::ajouterPresenceKronos), $resultat (avec 'kronos')
 ******************************************************************************/
$e ??= fn($valeur) => htmlspecialchars((string)$valeur);
$machinesKronos = ['K2', 'K3'];
//banques sur un Kronos, ou croix si le son n'y est pas (? si aucun export de ce Kronos n'est importé)
$presenceKronos ??= function (array $kronos, string $machine) use ($resultat, $e): string {
    if (!isset($resultat['kronos'][$machine])) {
        return '<span class="text-muted small" title="Aucun export ' . $machine . ' importé">?</span>';
    }
    $banques = $kronos[$machine] ?? [];
    if (count($banques) === 0) {
        return '<i class="bi bi-x-circle-fill text-danger" title="Absent du ' . $machine . '"></i><span class="visually-hidden">absent</span>';
    }
    return '<span class="text-success fw-semibold">' . $e(implode(", ", $banques)) . '</span>';
};
?>
<div class="ms-3 ps-3 border-start border-3 border-success">
    <div class="small fw-semibold mb-1">Programs qui composent « <?= $e($combi['nom']) ?> »</div>
    <table class="table table-sm table-borderless small mb-1">
        <thead>
            <tr class="text-muted"><th>Program</th><th>Catégorie</th><th>PCG d'origine</th>
                <?php foreach ($machinesKronos as $m): ?><th class="text-center"><?= $m ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($combi['programs'] as $p): ?>
            <tr>
                <td class="fw-semibold"><?= $p['nom'] !== null ? $e($p['nom']) : '<span class="fst-italic text-muted fw-normal">nom inconnu</span>' ?>
                    <?php if ($p['nom'] !== null && !$p['origine']): ?><span class="badge text-bg-light border fw-normal" title="Program d'usine : nom repris de l'export du Kronos">usine</span><?php endif; ?></td>
                <td class="text-muted"><?= $e($p['categorie']) ?></td>
                <td class="text-nowrap"><?= $e($p['banque']) ?></td>
                <?php foreach ($machinesKronos as $m): ?><td class="text-center"><?= $presenceKronos($p['kronos'], $m) ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
