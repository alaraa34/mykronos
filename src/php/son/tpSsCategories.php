<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Edition des libellés des sous-catégories d'une catégorie
 * Variables attendues : $idType, $categories (même type, pour le choix), $categorie, $sousCategories, $message, $succes
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$choixFusion = function (int $id) use ($sousCategories, $e): string {
//liste des cibles possibles pour fusionner la ligne $id
    $html = '<select class="form-select form-select-sm fusion" name="lignes[' . $id . '][fusion]" aria-label="Fusionner dans">'
          . '<option value="0">—</option>';
    foreach ($sousCategories as $cible) {
        if ((int)$cible['id'] === $id) {continue;}
        $html .= '<option value="' . (int)$cible['id'] . '">' . (int)$cible['idPersonalise'] . ' - ' . $e($cible['libelle']) . '</option>';
    }
    return $html . '</select>';
};
$idCategorie = (int)$categorie['id'];
ob_start(); ?>
<section class="py-4">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="h4 mb-0 me-2">Sous-catégories <?= $e(Categorie::nomType($idType)) ?> de</h2>
        <select class="form-select form-select-sm w-auto" id="choixCategorie" aria-label="Catégorie">
            <?php foreach ($categories as $c): ?>
                <option value="<?= $e(TbAdressage::getURLstatic('son', 'editerSsCategories', (int)$c['id'])) ?>"
                        <?= (int)$c['id'] === $idCategorie ? 'selected' : '' ?>>
                    <?= (int)$c['idPersonalise'] ?> - <?= $e($c['libelle']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a class="btn btn-outline-dark btn-sm" href="<?= TbAdressage::getURLstatic('son', 'editerCategories', $idType) ?>">
            <i class="bi bi-chevron-left"></i> Catégories</a>
    </div>
    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= $succes ? 'success' : 'warning' ?> py-2"><?= $e($message) ?></div>
    <?php endif; ?>

    <p class="small text-muted">N° = numéro de la sous-catégorie sur le Kronos (0 à 7, -1 : sans numéro).
        Fusionner : les sons passent dans la sous-catégorie choisie, puis la ligne est supprimée.</p>
    <?php if (count($sousCategories) === 0): ?>
        <p class="text-muted">Aucune sous-catégorie.</p>
    <?php else: ?>
    <form method="post" action="<?= TbAdressage::getURLstatic('son', 'enregistrerSsCategories', $idCategorie) ?>">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr><th class="text-center">N°</th><th>Libellé</th><th class="text-center">Sons</th><th>Fusionner dans</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sousCategories as $s): $aRenseigner = str_contains((string)$s['libelle'], "à renseigner"); ?>
                <tr class="<?= $aRenseigner ? 'table-warning' : '' ?>">
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center numero" required
                               min="-1" max="<?= Categorie::NUMERO_MAX_SSCATEGORIE ?>"
                               name="lignes[<?= (int)$s['id'] ?>][numero]" value="<?= (int)$s['idPersonalise'] ?>"
                               aria-label="N° de « <?= $e($s['libelle']) ?> »">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" maxlength="<?= Categorie::LONGUEUR_LIBELLE ?>"
                               name="lignes[<?= (int)$s['id'] ?>][libelle]" value="<?= $e($s['libelle']) ?>" required
                               aria-label="Libellé de la sous-catégorie <?= (int)$s['idPersonalise'] ?>">
                    </td>
                    <td class="text-center"><?= (int)$s['nbSons'] ?></td>
                    <td><?= $choixFusion((int)$s['id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn btn-dark btn-sm">Enregistrer</button>
    </form>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$script = "<script>document.getElementById('choixCategorie').addEventListener('change', function () { location.href = this.value; });</script>";
require(TbAdressage::projetGetLayout(__NAMESPACE__));
