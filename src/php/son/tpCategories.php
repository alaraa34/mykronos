<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Catégories (racines de mk_cat_sscat), une liste par type : Program et Combi
 * Les n° et libellés viennent du Global de mon Kronos ; les libellés restent modifiables
 * Variables attendues : $types, $idType, $categories, $pcgKronos, $message, $succes
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;
use shared\php\classes\socle\Menu        as Menu;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Catégories</h2>
    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= $succes ? 'success' : 'warning' ?> py-2"><?= $e($message) ?></div>
    <?php endif; ?>

    <form method="get" action="index.php" class="row g-2 align-items-end mb-3" data-attente="Lecture des catégories du Kronos…">
        <input type="hidden" name="<?= Menu::FONCTION ?>" value="chargerCategoriesKronos">
        <input type="hidden" name="<?= Menu::DOMAINE ?>" value="son">
        <input type="hidden" name="<?= Menu::CONTROLEUR ?>" value="son">
        <div class="col-12 col-md-7">
            <label class="form-label small mb-1" for="pcgKronos">Charger les catégories depuis un PCG de mon Kronos (enregistré avec le Global)</label>
            <select class="form-select form-select-sm" id="pcgKronos" name="id" <?= count($pcgKronos) === 0 ? 'disabled' : '' ?>>
                <?php if (count($pcgKronos) === 0): ?>
                    <option>Aucun PCG fromK2 / fromK3 détecté</option>
                <?php endif; ?>
                <?php foreach ($pcgKronos as $id => $libelle): ?>
                    <option value="<?= $id ?>"><?= $e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5">
            <button type="submit" class="btn btn-dark btn-sm" <?= count($pcgKronos) === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-cloud-download"></i> Charger les catégories du Kronos</button>
        </div>
    </form>

    <ul class="nav nav-tabs mb-3">
        <?php foreach ($types as $id => $nom): ?>
            <li class="nav-item">
                <a class="nav-link <?= $id === $idType ? 'active' : '' ?>" <?= $id === $idType ? 'aria-current="page"' : '' ?>
                   href="<?= TbAdressage::getURLstatic('son', 'editerCategories', $id) ?>"><?= $e($nom) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <form method="post" action="<?= TbAdressage::getURLstatic('son', 'enregistrerCategories', $idType) ?>">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Libellé</th>
                    <th class="text-center">Sous-catégories</th>
                    <th class="text-center">Sons</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td class="text-center"><?= (int)$c['idPersonalise'] ?></td>
                    <td>
                        <input type="text" class="form-control form-control-sm" maxlength="<?= Categorie::LONGUEUR_LIBELLE ?>"
                               name="lignes[<?= (int)$c['id'] ?>][libelle]" value="<?= $e($c['libelle']) ?>" required
                               aria-label="Libellé de la catégorie <?= (int)$c['idPersonalise'] ?>">
                    </td>
                    <td class="text-center">
                        <a class="btn btn-outline-dark btn-sm" title="Sous-catégories"
                           href="<?= TbAdressage::getURLstatic('son', 'editerSsCategories', (int)$c['id']) ?>">
                            <?= (int)$c['nbSousCategories'] ?> <i class="bi bi-chevron-right"></i>
                        </a>
                    </td>
                    <td class="text-center"><?= (int)$c['nbSons'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn btn-dark btn-sm">Enregistrer les libellés</button>
    </form>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
