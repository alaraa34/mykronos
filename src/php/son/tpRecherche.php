<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Recherche de sons
 * Variables attendues : $texte, $idType, $idTypologie, $moteur, $favoris, $resultat, $types, $typologies
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;
use shared\php\classes\socle\Menu        as Menu;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$mots = Recherche::mots($texte);
$surligner = function (string $nom) use ($mots, $e): string {
//met en évidence les mots recherchés dans le nom (sans tenir compte des majuscules)
    $html = $e($nom);
    foreach ($mots as $mot) {
        $html = preg_replace('/(' . preg_quote($e($mot), '/') . ')/iu', '<mark>$1</mark>', $html);
    }
    return $html;
};
$couleurs = ['Program' => 'primary', 'Combi' => 'success', 'WaveSequence' => 'warning', 'DrumKit' => 'danger', 'DrumPattern' => 'info'];
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Rechercher un son</h2>
    <form method="get" action="index.php" class="row g-2 align-items-end mb-3" role="search" data-attente="Recherche en cours…">
        <input type="hidden" name="<?= Menu::FONCTION ?>" value="rechercherSon">
        <input type="hidden" name="<?= Menu::DOMAINE ?>" value="son">
        <input type="hidden" name="<?= Menu::CONTROLEUR ?>" value="recherche">
        <div class="col-12 col-md-3">
            <label class="form-label small mb-1" for="q">Nom, catégorie ou sous-catégorie</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= $e($texte) ?>"
                   placeholder="ex : brass stab, e.piano, pad" autofocus>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="0">Tous</option>
                <?php foreach ($types as $id => $nom): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$id === $idType ? 'selected' : '' ?>><?= $e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
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
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="moteur">Moteur (Programs)</label>
            <select class="form-select" id="moteur" name="moteur">
                <option value="">Tous</option>
                <?php foreach (Recherche::MOTEURS as $code => $libelle): ?>
                    <option value="<?= $e($code) ?>" <?= $code === $moteur ? 'selected' : '' ?>><?= $e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="favoris" name="favoris" value="1" <?= $favoris ? 'checked' : '' ?>>
                <label class="form-check-label small" for="favoris"><i class="bi bi-star-fill favori"></i> Favoris</label>
            </div>
        </div>
        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-dark w-100" title="Rechercher"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <?php if ($texte === "" && !$favoris): ?>
        <p class="text-muted">Tape un ou plusieurs mots : chaque mot doit apparaître dans le nom du son, sa catégorie ou sa sous-catégorie.
            Sans mot, coche <i class="bi bi-star-fill favori"></i> Favoris pour lister tes favoris.</p>
    <?php elseif (count($resultat['sons']) === 0): ?>
        <p class="text-muted">Aucun son trouvé<?= $texte !== "" ? " pour « " . $e($texte) . " »" : "" ?>.</p>
    <?php else: ?>
        <p class="small">
            <?= count($resultat['sons']) ?> son<?= count($resultat['sons']) > 1 ? 's' : '' ?>
            <?php if ($resultat['trop']): ?>
                <span class="text-danger">(limité aux <?= Recherche::MAXIMUM ?> premiers : précise la recherche)</span>
            <?php endif; ?>
        </p>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="resultatsRecherche">
            <thead>
                <tr><th>Son</th><th>Type</th><th>Banque</th><th>Catégorie</th><th>Provenance</th><th>Fichier PCG</th><th class="text-center">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultat['sons'] as $son): $estCombi = $son['type'] === 'Combi'; $idLiens = 'liens-' . (int)$son['id']; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php if ((int)$son['favori'] === 1): ?><i class="bi bi-star-fill favori" title="Favori sur le Kronos"></i> <?php endif; ?><?= $surligner((string)$son['nom']) ?></div>
                        <?php include('tpInfosSon.php'); ?>
                    </td>
                    <td><span class="badge text-bg-<?= $couleurs[$son['type']] ?? 'secondary' ?>"><?= $e($son['type']) ?></span></td>
                    <td class="text-nowrap"><?= $e($son['banque']) ?></td>
                    <td class="small">
                        <?php if ($son['categorie'] !== null): ?>
                            <?= $surligner((string)$son['categorie']) ?><br>
                            <span class="text-muted"><?= $surligner((string)$son['sousCategorie']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge provenance <?= str_starts_with((string)$son['typologie'], 'from') ? 'provenance-kronos' : '' ?>"
                              title="<?= str_starts_with((string)$son['typologie'], 'from') ? 'Exporté de mon Kronos' : 'Banque de sons' ?>"><?= $e($son['typologie'] ?? '?') ?></span></td>
                    <td class="small"><?= $e($son['fichier']) ?></td>
                    <td class="text-center text-nowrap">
                        <?php if (count($son['liens']) > 0): ?>
                        <button type="button" class="btn btn-sm <?= $estCombi ? 'btn-success' : 'btn-outline-primary' ?>"
                                data-bs-toggle="collapse" data-bs-target="#<?= $idLiens ?>" aria-expanded="false" aria-controls="<?= $idLiens ?>"
                                title="<?= $estCombi ? 'Programs qui composent la combi' : 'Combis qui utilisent ce program' ?>">
                            <i class="bi <?= $estCombi ? 'bi-diagram-3' : 'bi-box-arrow-up-right' ?>"></i> <?= count($son['liens']) ?>
                        </button>
                        <?php endif; ?>
                        <?php if ((int)$son['exclus'] === 0): ?>
                        <a class="btn btn-outline-dark btn-sm" title="Télécharger le PCG <?= $e($son['fichier']) ?>"
                           href="<?= TbAdressage::getURLstatic('fichier', 'telechargerFichier', (int)$son['idFichier']) ?>">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (count($son['liens']) > 0): ?>
                <tr class="collapse ligne-liens" id="<?= $idLiens ?>">
                    <td colspan="7">
                        <?php if ($estCombi): $combi = $son; include('tpComposition.php'); else: ?>
                        <div class="ms-3 ps-3 border-start border-3 border-primary">
                            <div class="small fw-semibold mb-1">Combis qui utilisent « <?= $e($son['nom']) ?> »</div>
                            <table class="table table-sm table-borderless small mb-1">
                                <?php foreach ($son['liens'] as $lien): ?>
                                <tr>
                                    <td class="text-nowrap text-muted" style="width:6rem"><?= $e($lien['banque']) ?></td>
                                    <?php if ($lien['nom'] !== null): ?>
                                        <td class="fw-semibold"><?= $e($lien['nom']) ?></td>
                                        <td class="text-muted"><?= $e($lien['categorie'] ?? '') ?><?= $lien['sousCategorie'] !== null ? ' / ' . $e($lien['sousCategorie']) : '' ?></td>
                                    <?php else: ?>
                                        <td colspan="2" class="fst-italic text-muted">absente de ce PCG</td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <?php endif; ?>
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
