<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Set lists d'un PCG (exports de mes Kronos) : emplacements, son appelé, volume, description
 * Variables attendues : $fichiers, $idFichier, $texte, $setlists
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;
use shared\php\classes\socle\Menu        as Menu;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$mots = Recherche::mots($texte);
$surligner = function (string $texteBrut) use ($mots, $e): string {
    $html = $e($texteBrut);
    foreach ($mots as $mot) {
        $html = preg_replace('/(' . preg_quote($e($mot), '/') . ')/iu', '<mark>$1</mark>', $html);
    }
    return $html;
};
$couleurs = ['Program' => 'primary', 'Combi' => 'success'];
$nbSlots = array_sum(array_map(fn($s) => count($s['slots']), $setlists));
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Set lists</h2>
    <?php if (count($fichiers) === 0): ?>
        <p class="text-muted">Aucune set list importée : importe un PCG exporté de ton Kronos (fromK2 / fromK3).</p>
    <?php else: ?>
    <form method="get" action="index.php" class="row g-2 align-items-end mb-3" role="search" data-attente="Recherche en cours…">
        <input type="hidden" name="<?= Menu::FONCTION ?>" value="afficherSetlists">
        <input type="hidden" name="<?= Menu::DOMAINE ?>" value="son">
        <input type="hidden" name="<?= Menu::CONTROLEUR ?>" value="setlist">
        <div class="col-12 col-md-5">
            <label class="form-label small mb-1" for="id">PCG</label>
            <select class="form-select" id="id" name="id">
                <?php foreach ($fichiers as $id => $libelle): ?>
                    <option value="<?= $id ?>" <?= $id === $idFichier ? 'selected' : '' ?>><?= $e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5">
            <label class="form-label small mb-1" for="q">Titre, emplacement ou note (description)</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= $e($texte) ?>" placeholder="ex : sunshine, solo, Gm7">
        </div>
        <div class="col-12 col-md-2">
            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-search"></i> Afficher</button>
        </div>
    </form>

    <p class="small"><?= count($setlists) ?> set list<?= count($setlists) > 1 ? 's' : '' ?>, <?= $nbSlots ?> emplacement<?= $nbSlots > 1 ? 's' : '' ?>
        <?= $texte !== "" ? "contenant « " . $e($texte) . " »" : "" ?></p>

    <div class="accordion" id="setlists">
    <?php foreach ($setlists as $sl): $idBloc = 'setlist-' . (int)$sl['numero']; $ouvert = $texte !== "" || count($setlists) === 1; ?>
        <div class="accordion-item">
            <h3 class="accordion-header">
                <button class="accordion-button <?= $ouvert ? '' : 'collapsed' ?> py-2" type="button" data-bs-toggle="collapse"
                        data-bs-target="#<?= $idBloc ?>" aria-expanded="<?= $ouvert ? 'true' : 'false' ?>" aria-controls="<?= $idBloc ?>">
                    <span class="text-muted me-2"><?= sprintf("%03d", (int)$sl['numero']) ?></span>
                    <span class="fw-semibold me-2"><?= $surligner((string)$sl['nom']) ?></span>
                    <span class="badge text-bg-secondary"><?= count($sl['slots']) ?></span>
                </button>
            </h3>
            <div id="<?= $idBloc ?>" class="accordion-collapse collapse <?= $ouvert ? 'show' : '' ?>">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-setlist">
                        <thead><tr><th class="text-center">N°</th><th>Emplacement</th><th>Son appelé</th><th class="text-center">Vol.</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($sl['slots'] as $slot): ?>
                            <tr>
                                <td class="text-center text-muted"><?= (int)$slot['numero'] ?></td>
                                <td class="fw-semibold"><?= $surligner((string)$slot['nom']) ?></td>
                                <td class="small text-nowrap">
                                    <?php if ($slot['type'] !== null): ?>
                                        <span class="badge text-bg-<?= $couleurs[$slot['type']] ?? 'secondary' ?>"><?= $e($slot['type']) ?></span>
                                        <span class="text-muted"><?= $e($slot['banque']) ?></span>
                                        <?php if ($slot['son'] !== null): ?>
                                            <a href="index.php?<?= Menu::FONCTION ?>=<?= $slot['type'] === 'Combi' ? 'rechercheCombinaison' : 'rechercherSon' ?>&amp;<?= Menu::DOMAINE ?>=son&amp;<?= Menu::CONTROLEUR ?>=recherche&amp;typologie=<?= Recherche::KRONOS ?>&amp;q=<?= urlencode((string)$slot['son']) ?>"
                                               title="Voir ce son dans la recherche"><?= $e($slot['son']) ?></a>
                                            <?php if ((int)$slot['favori'] === 1): ?><i class="bi bi-star-fill favori" title="Favori"></i><?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border">Song</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small"><?= $slot['volume'] !== null ? (int)$slot['volume'] : '' ?></td>
                                <td class="small description-slot"><?= $surligner((string)$slot['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
