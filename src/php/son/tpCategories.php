<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Edition des libellés des catégories (racines de mk_cat_sscat)
 * Variables attendues : $types, $idType, $categories, $message, $succes
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$choixFusion = function (int $id) use ($categories, $e): string {
//liste des cibles possibles pour fusionner la ligne $id
    $html = '<select class="form-select form-select-sm fusion" name="lignes[' . $id . '][fusion]" aria-label="Fusionner dans">'
          . '<option value="0">—</option>';
    foreach ($categories as $cible) {
        if ((int)$cible['id'] === $id) {continue;}
        $html .= '<option value="' . (int)$cible['id'] . '">' . (int)$cible['idPersonalise'] . ' - ' . $e($cible['libelle']) . '</option>';
    }
    return $html . '</select>';
};
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Catégories <span class="badge text-bg-secondary"><?= count($categories) ?></span></h2>
    <ul class="nav nav-tabs mb-3">
        <?php foreach ($types as $id => $nom): ?>
            <li class="nav-item">
                <a class="nav-link <?= $id === $idType ? 'active' : '' ?>" <?= $id === $idType ? 'aria-current="page"' : '' ?>
                   href="<?= TbAdressage::getURLstatic('son', 'editerCategories', $id) ?>"><?= $e($nom) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= $succes ? 'success' : 'warning' ?> py-2"><?= $e($message) ?></div>
    <?php endif; ?>
    <p class="small text-muted">N° = numéro de la catégorie sur le Kronos (0 à 17, -1 : sans numéro). Les lignes « à renseigner » ont été créées par l'import.
        Fusionner : les sons et les sous-catégories passent dans la catégorie choisie (sous-catégories de même N° réunies), puis la ligne est supprimée.
        Type : les Programs et les Combis ont chacun leur liste de catégories ; changer le type déplace la catégorie dans l'autre liste.</p>
    <form method="post" action="<?= TbAdressage::getURLstatic('son', 'enregistrerCategories', $idType) ?>">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Libellé</th>
                    <th class="text-center">Sous-catégories</th>
                    <th class="text-center">Sons</th>
                    <th>Fusionner dans</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): $aRenseigner = str_contains((string)$c['libelle'], "à renseigner"); ?>
                <tr class="<?= $aRenseigner ? 'table-warning' : '' ?>">
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center numero" required
                               min="-1" max="<?= Categorie::NUMERO_MAX_CATEGORIE ?>"
                               name="lignes[<?= (int)$c['id'] ?>][numero]" value="<?= (int)$c['idPersonalise'] ?>"
                               aria-label="N° de « <?= $e($c['libelle']) ?> »">
                    </td>
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
                    <td><?= $choixFusion((int)$c['id']) ?></td>
                    <td>
                        <select class="form-select form-select-sm" name="lignes[<?= (int)$c['id'] ?>][type]" aria-label="Type de « <?= $e($c['libelle']) ?> »">
                            <?php foreach ($types as $id => $nom): ?>
                                <option value="<?= $id ?>" <?= $id === $idType ? 'selected' : '' ?>><?= $e($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn btn-dark btn-sm">Enregistrer</button>
    </form>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
