<?php
namespace theBand\src\php\accueil;
/*******************************************************************************
 * Formulaire des données groupe d'un musicien (table tb_user_theband, administrateur)
 * Variables attendues : $musicien (UserTheBand), $roles
 ******************************************************************************/
use shared\php\toolbox\Toolbox_html         as TbHtml;
use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
use shared\php\toolbox\Toolbox_liste        as TbListe;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
ob_start(); ?>
<form method="post" action="<?= TbAdressage::getURLstatic('accueil','musicienMAJ')?>">
    <section class="py-5">
        <div class="container">
            <input type="hidden" name="idUtilisateur" value="<?= $musicien->id ?>" />
            <input type="hidden"  name="referer"  value="<?= TbAdressage::getReferer($musicien->id) ?>" />
            <!-- Rappel de l'utilisateur (non modifiable ici) -->
            <div class="row mb-3">
                <div class="col-auto">
                    <img src="images/<?= $e($musicien->avatar !== "" ? $musicien->avatar : "defaut.jpeg") ?>" alt="" style="width:40px;" class="rounded-pill">
                </div>
                <div class="col">
                    <strong><?= $e($musicien->prenom . " " . $musicien->nom) ?></strong> (<?= $e($musicien->abrev) ?>)
                </div>
            </div>
            <!-- Données groupe -->
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <label class="input-group-text" for="role">Rôle</label>
                        <select name="role" id="role" class="form-select">
                            <?= TbListe::valeursChoixListe($roles, false, selected: $musicien->role); ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <label class="input-group-text" for="coeff">Coefficient vote maîtrise</label>
                        <input type="number" name="coeff" id="coeff" class="form-control" style="text-align:center;"
                               min="0" max="10" step="0.1" required value="<?= $e($musicien->coeff) ?>"/>
                    </div>
                </div>
            </div>
            <!-- Soumission formulaire -->
            <?= TbHtml::htmlBoutonValidation()?>
        </div>
    </section>
</form>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));?>
