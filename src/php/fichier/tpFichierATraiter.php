<?php
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Fichiers PCG à traiter (import du contenu ou de la composition à faire)
 * Variable attendue : $fichiers (Fichier::listerATraiter)
 * La case Exclus est enregistrée immédiatement (AJAX vers ctExclureFichier)
 * et désactive les boutons Importer et Télécharger
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$etat = fn($valeur) => (int)$valeur === 1
        ? '<i class="bi bi-check-circle-fill text-success" title="Fait"></i>'
        : '<i class="bi bi-circle text-secondary" title="À faire"></i>';
ob_start(); ?>
<section class="py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h4 mb-0">Fichiers à traiter <span class="badge text-bg-secondary"><?= count($fichiers) ?></span></h2>
        <a class="btn btn-dark btn-sm" data-attente="Import des PCG en cours…" href="<?= TbAdressage::getURLstatic('fichier', 'importerPcg', 0, 'son') ?>">
            <i class="bi bi-box-arrow-in-down"></i> Importer tous les PCG à traiter
        </a>
    </div>
    <?php if (count($fichiers) === 0): ?>
        <p class="text-muted">Aucun fichier à traiter.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover align-middle" id="fichiersATraiter">
        <thead>
            <tr>
                <th>Typologie</th>
                <th>Fichier</th>
                <th>Date</th>
                <th class="text-center">Contenu</th>
                <th class="text-center">Composition</th>
                <th class="text-center">Exclus</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fichiers as $f): $exclus = (int)$f['exclus'] === 1; ?>
            <tr class="<?= $exclus ? 'fichier-exclus' : '' ?>">
                <td class="text-nowrap"><?= $e($f['typologieNom'] ?? '?') ?></td>
                <td>
                    <div class="fw-semibold"><?= $e($f['libelle']) ?></div>
                    <div class="small text-muted"><?= $e(dirname((string)$f['emplacement'])) ?></div>
                </td>
                <td class="text-nowrap"><?= $e(date("d/m/Y", strtotime((string)$f['dateFichier']))) ?></td>
                <td class="text-center"><?= $etat($f['importContenu']) ?></td>
                <td class="text-center"><?= $etat($f['importComposition']) ?></td>
                <td class="text-center">
                    <input class="form-check-input case-exclus" type="checkbox" data-id="<?= (int)$f['id'] ?>"
                           <?= $exclus ? 'checked' : '' ?> aria-label="Exclure <?= $e($f['libelle']) ?>">
                </td>
                <td class="text-center text-nowrap">
                    <a class="btn btn-outline-dark btn-sm bouton-action<?= $exclus ? ' disabled' : '' ?>"
                       title="Importer <?= $e($f['libelle']) ?>" data-attente="Import de <?= $e($f['libelle']) ?>…"
                       <?= $exclus ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       href="<?= TbAdressage::getURLstatic('fichier', 'importerPcgFichier', (int)$f['id'], 'son') ?>">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </a>
                    <a class="btn btn-outline-dark btn-sm bouton-action<?= $exclus ? ' disabled' : '' ?>"
                       title="Télécharger <?= $e($f['libelle']) ?>"
                       <?= $exclus ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       href="<?= TbAdressage::getURLstatic('fichier', 'telechargerFichier', (int)$f['id']) ?>">
                        <i class="bi bi-download"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$urlExclure = TbAdressage::getURLstatic('fichier', 'exclureFichier');
$script = <<<HTML
<script>
function activerActions(ligne, actif) {
//un fichier exclu ne peut être ni importé ni téléchargé
    ligne.querySelectorAll('.bouton-action').forEach(function (bouton) {
        bouton.classList.toggle('disabled', !actif);
        if (actif) {
            bouton.removeAttribute('aria-disabled');
            bouton.removeAttribute('tabindex');
        } else {
            bouton.setAttribute('aria-disabled', 'true');
            bouton.setAttribute('tabindex', '-1');
        }
    });
}
document.querySelectorAll('.case-exclus').forEach(function (caseExclus) {
    caseExclus.addEventListener('change', function () {
        const ligne = caseExclus.closest('tr');
        const donnees = new FormData();
        donnees.append('id', caseExclus.dataset.id);
        donnees.append('exclus', caseExclus.checked ? '1' : '0');
        caseExclus.disabled = true;
        fetch('{$urlExclure}', {method: 'POST', body: donnees})
            .then(function (reponse) { return reponse.json(); })
            .then(function (resultat) {
                if (!resultat.ok) { throw new Error('refus'); }
                ligne.classList.toggle('fichier-exclus', caseExclus.checked);
                activerActions(ligne, !caseExclus.checked);
            })
            .catch(function () {
                caseExclus.checked = !caseExclus.checked;
                alert("L'enregistrement de la case Exclus a échoué.");
            })
            .finally(function () { caseExclus.disabled = false; });
    });
});
</script>
HTML;
require(TbAdressage::projetGetLayout(__NAMESPACE__));
