<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Fragment : informations d'un son sous son nom (moteur, tempo, set lists de mes Kronos)
 * Inclus par tpRecherche et tpRechercheCombinaison - variable attendue : $son
 * L'étoile du favori est affichée avec le nom par la page appelante
 ******************************************************************************/
$e ??= fn($valeur) => htmlspecialchars((string)$valeur);
$details = [];
if (!empty($son['moteur'])) {
    $details[] = '<span class="badge moteur" title="Moteur de synthèse">' . $e($son['moteur']) . '</span>';
}
if ($son['tempo'] !== null && (float)$son['tempo'] > 0) {
    $details[] = '<span class="text-muted" title="Tempo de la combi"><i class="bi bi-speedometer2"></i> '
               . $e(rtrim(rtrim(number_format((float)$son['tempo'], 2, ',', ''), '0'), ',')) . ' BPM</span>';
}
$slots = $son['setlists'] ?? [];
?>
<?php if (count($details) > 0): ?>
    <div class="small"><?= implode(' ', $details) ?></div>
<?php endif; ?>
<?php if (count($slots) > 0): ?>
    <div class="small setlists-son" title="Emplacements de set lists qui appellent ce son">
        <?php foreach (array_slice($slots, 0, 3) as $slot): ?>
            <div><i class="bi bi-music-note-list"></i> <span class="badge provenance provenance-kronos"><?= $e($slot['machine']) ?></span>
                <?= $e($slot['setlist']) ?> <span class="text-muted">n°<?= (int)$slot['numero'] ?></span>
                <?= $slot['nom'] !== '' ? '« ' . $e($slot['nom']) . ' »' : '' ?></div>
        <?php endforeach; ?>
        <?php if (count($slots) > 3): ?><div class="text-muted">… et <?= count($slots) - 3 ?> autres emplacements</div><?php endif; ?>
    </div>
<?php endif; ?>
