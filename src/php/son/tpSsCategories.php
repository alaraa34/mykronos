<?php
namespace mykronos\src\php\son;
/*******************************************************************************
 * Sous-catégories d'une catégorie ; les libellés restent modifiables
 * Variables attendues : $idType, $categories (même type, pour le choix), $categorie, $sousCategories, $message, $succes
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
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

    <?php if (count($sousCategories) === 0): ?>
        <p class="text-muted">Aucune sous-catégorie.</p>
    <?php else: ?>
    <form method="post" action="<?= TbAdressage::getURLstatic('son', 'enregistrerSsCategories', $idCategorie) ?>">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr><th class="text-center">N°</th><th>Libellé</th><th class="text-center">Sons</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sousCategories as $s): ?>
                <tr>
                    <td class="text-center"><?= (int)$s['idPersonalise'] ?></td>
                    <td>
                        <input type="text" class="form-control form-control-sm" maxlength="<?= Categorie::LONGUEUR_LIBELLE ?>"
                               name="lignes[<?= (int)$s['id'] ?>][libelle]" value="<?= $e($s['libelle']) ?>" required
                               aria-label="Libellé de la sous-catégorie <?= (int)$s['idPersonalise'] ?>">
                    </td>
                    <td class="text-center"><?= (int)$s['nbSons'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn btn-dark btn-sm">Enregistrer les libellés</button>
    </form>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$script = "<script>document.getElementById('choixCategorie').addEventListener('change', function () { location.href = this.value; });</script>";
require(TbAdressage::projetGetLayout(__NAMESPACE__));
