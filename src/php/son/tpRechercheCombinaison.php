<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Recherche de combinaisons : composition et banques sur le PCG d'origine, le K2 et le K3
 * Variables attendues : $texte, $idTypologie, $favoris, $resultat (avec 'kronos'), $typologies
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;
use shared\php\classes\socle\Menu        as Menu;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$mots = Recherche::mots($texte);
$surligner = function (string $nom) use ($mots, $e): string {
    $html = $e($nom);
    foreach ($mots as $mot) {
        $html = preg_replace('/(' . preg_quote($e($mot), '/') . ')/iu', '<mark>$1</mark>', $html);
    }
    return $html;
};
$machines = ['K2', 'K3'];
//banques sur un Kronos, ou croix si le son n'y est pas (ou si aucun export de ce Kronos n'est importé)
$presenceKronos = function (array $kronos, string $machine) use ($resultat, $e): string {
    if (!isset($resultat['kronos'][$machine])) {
        return '<span class="text-muted small" title="Aucun export ' . $machine . ' importé">?</span>';
    }
    $banques = $kronos[$machine] ?? [];
    if (count($banques) === 0) {
        return '<i class="bi bi-x-circle-fill text-danger" title="Absent du ' . $machine . '"></i><span class="visually-hidden">absent</span>';
    }
    return '<span class="text-success fw-semibold">' . $e(implode(", ", $banques)) . '</span>';
};
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Rechercher une combinaison</h2>
    <form method="get" action="index.php" class="row g-2 align-items-end mb-3" role="search" data-attente="Recherche en cours…">
        <input type="hidden" name="<?= Menu::FONCTION ?>" value="rechercheCombinaison">
        <input type="hidden" name="<?= Menu::DOMAINE ?>" value="son">
        <input type="hidden" name="<?= Menu::CONTROLEUR ?>" value="recherche">
        <div class="col-12 col-md-5">
            <label class="form-label small mb-1" for="q">Nom, catégorie ou sous-catégorie de la combi</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= $e($texte) ?>" placeholder="ex : walk of life, orchestral" autofocus>
        </div>
        <div class="col-8 col-md-3">
            <label class="form-label small mb-1" for="typologie">Provenance</label>
            <select class="form-select" id="typologie" name="typologie">
                <option value="<?= Recherche::SAUF_KRONOS ?>" <?= $idTypologie === Recherche::SAUF_KRONOS ? 'selected' : '' ?>>Tout sauf mes Kronos</option>
                <option value="<?= Recherche::KRONOS ?>" <?= $idTypologie === Recherche::KRONOS ? 'selected' : '' ?>>Mes Kronos (K2 et K3)</option>
                <option value="<?= Recherche::TOUTES ?>" <?= $idTypologie === Recherche::TOUTES ? 'selected' : '' ?>>Toutes</option>
                <?php foreach ($typologies as $id => $nom): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$id === $idTypologie ? 'selected' : '' ?>><?= $e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="favoris" name="favoris" value="1" <?= $favoris ? 'checked' : '' ?>>
                <label class="form-check-label small" for="favoris"><i class="bi bi-star-fill favori"></i> Favoris</label>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-search"></i> Rechercher</button>
        </div>
    </form>

    <?php if ($texte === "" && !$favoris): ?>
        <p class="text-muted">Tape un ou plusieurs mots. Pour chaque combi, le bouton <i class="bi bi-diagram-3"></i> montre ses programs
            et leur banque dans le PCG d'origine, sur le K2 et sur le K3.</p>
    <?php elseif (count($resultat['sons']) === 0): ?>
        <p class="text-muted">Aucune combinaison trouvée<?= $texte !== "" ? " pour « " . $e($texte) . " »" : "" ?>.</p>
    <?php else: ?>
        <p class="small">
            <?= count($resultat['sons']) ?> combinaison<?= count($resultat['sons']) > 1 ? 's' : '' ?>
            <?php if ($resultat['trop']): ?><span class="text-danger">(limité aux <?= Recherche::MAXIMUM ?> premières : précise la recherche)</span><?php endif; ?>
            <span class="text-muted ms-2">Présence sur les Kronos jugée par le nom, dans le dernier export importé
                <?php foreach ($machines as $m): ?><?= $m ?> : <?= isset($resultat['kronos'][$m]) ? 'n°' . $resultat['kronos'][$m] : 'aucun' ?> <?php endforeach; ?></span>
        </p>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="resultatsCombis">
            <thead>
                <tr><th>Combinaison</th><th>Banque</th><th>Catégorie</th><th>Provenance</th>
                    <?php foreach ($machines as $m): ?><th class="text-center"><?= $m ?></th><?php endforeach; ?>
                    <th class="text-center">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultat['sons'] as $combi): $idCompo = 'compo-' . (int)$combi['id']; ?>
                <tr>
                    <td class="fw-semibold"><?php if ((int)$combi['favori'] === 1): ?><i class="bi bi-star-fill favori" title="Favori sur le Kronos"></i> <?php endif; ?><?= $surligner((string)$combi['nom']) ?>
                        <div class="small text-muted fw-normal"><?= $e($combi['fichier']) ?></div>
                        <div class="fw-normal"><?php $son = $combi; include('tpInfosSon.php'); ?></div></td>
                    <td class="text-nowrap"><?= $e($combi['banque']) ?></td>
                    <td class="small"><?php if ($combi['categorie'] !== null): ?><?= $surligner((string)$combi['categorie']) ?><br>
                        <span class="text-muted"><?= $surligner((string)$combi['sousCategorie']) ?></span><?php endif; ?></td>
                    <td><span class="badge provenance <?= str_starts_with((string)$combi['typologie'], 'from') ? 'provenance-kronos' : '' ?>"><?= $e($combi['typologie'] ?? '?') ?></span></td>
                    <?php foreach ($machines as $m): ?><td class="text-center small"><?= $presenceKronos($combi['kronos'], $m) ?></td><?php endforeach; ?>
                    <td class="text-center text-nowrap">
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#<?= $idCompo ?>"
                                aria-expanded="false" aria-controls="<?= $idCompo ?>" title="Programs qui composent la combi"
                                <?= count($combi['programs']) === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-diagram-3"></i> <?= count($combi['programs']) ?>
                        </button>
                        <?php if ((int)$combi['exclus'] === 0): ?>
                        <a class="btn btn-outline-dark btn-sm" title="Télécharger le PCG <?= $e($combi['fichier']) ?>"
                           href="<?= TbAdressage::getURLstatic('fichier', 'telechargerFichier', (int)$combi['idFichier']) ?>"><i class="bi bi-download"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (count($combi['programs']) > 0): ?>
                <tr class="collapse ligne-liens" id="<?= $idCompo ?>">
                    <td colspan="<?= 5 + count($machines) ?>">
                        <?php include('tpComposition.php'); ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
