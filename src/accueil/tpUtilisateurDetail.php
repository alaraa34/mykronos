<?php
namespace theBand\src\php\accueil;
/*******************************************************************************
 * Formulaire de création / modification d'un utilisateur (administrateur)
 * Variables attendues : $utilisateur (User), $avatars, $motDePasseSuggere, $messageErreur
 ******************************************************************************/
use shared\php\toolbox\Toolbox_html         as TbHtml;
use shared\php\toolbox\Toolbox_adressage    as TbAdressage;
use shared\php\toolbox\Toolbox              as Tbx;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$creation = $utilisateur->id === 0;
ob_start(); ?>
<?php if (strlen($messageErreur) > 0): ?>
    <?= Tbx::messageColorer(false, "", $e($messageErreur)) ?>
<?php endif; ?>
<form method="post" action="<?= TbAdressage::getURLstatic('accueil','utilisateurMAJ')?>" autocomplete="off">
    <section class="py-5">
        <div class="container">
            <input type="hidden" name="idUtilisateur" value="<?= $utilisateur->id ?>" />
            <input type="hidden"  name="referer"  value="<?= TbAdressage::getReferer($utilisateur->id) ?>" />
            <!-- Identité -->
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Nom *</span>
                        <input type="text" name="nom" class="form-control" required maxlength="255" value="<?= $e($utilisateur->nom) ?>"/>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Prénom *</span>
                        <input type="text" name="prenom" class="form-control" required maxlength="255" value="<?= $e($utilisateur->prenom) ?>"/>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Abréviation *</span>
                        <input type="text" name="abrev" class="form-control" required maxlength="2"
                               placeholder="Ex : AL" style="text-transform:uppercase;" value="<?= $e($utilisateur->abrev) ?>"/>
                    </div>
                </div>
            </div>
            <!-- Connexion -->
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Identifiant *</span>
                        <input type="text" name="pseudo" class="form-control" required maxlength="30" value="<?= $e($utilisateur->pseudo) ?>"/>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Mot de passe<?= $creation ? " *" : "" ?></span>
                        <input type="password" name="motDePasse" id="motDePasse" class="form-control" minlength="6"
                               autocomplete="new-password" <?= $creation ? "required" : "" ?>
                               placeholder="<?= $creation ? "6 caractères minimum" : "Laisser vide pour ne pas le changer" ?>"/>
                        <button type="button" class="btn btn-outline-secondary" title="Afficher / masquer" onclick="jsBasculerMotDePasse()">
                            <i class="bi bi-eye" id="iconeMotDePasse"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" title="Proposer un mot de passe"
                                onclick="jsProposerMotDePasse('<?= $e($motDePasseSuggere) ?>')">Générer</button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-10">
                    <p class="form-text mt-0 mb-3">
                        Les mots de passe sont enregistrés chiffrés : l'ancien ne peut pas être réaffiché.
                        Un nouveau mot de passe s'affiche en clair ici avec l'œil, puis une seule fois après l'enregistrement, pour être communiqué.
                    </p>
                </div>
            </div>
            <!-- Contact et avatar -->
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Mail</span>
                        <input type="email" name="mail" class="form-control" maxlength="255" value="<?= $e($utilisateur->mail) ?>"/>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <label class="input-group-text" for="avatar">Avatar</label>
                        <select name="avatar" id="avatar" class="form-select" onchange="document.getElementById('apercuAvatar').src = 'images/' + this.value">
                            <?php foreach ($avatars as $avatar): ?>
                                <option value="<?= $e($avatar) ?>" <?= $avatar === $utilisateur->avatar ? "selected" : "" ?>><?= $e($avatar) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-1">
                    <img id="apercuAvatar" src="images/<?= $e($utilisateur->avatar !== "" ? $utilisateur->avatar : ($avatars[0] ?? "defaut.jpeg")) ?>"
                         alt="" style="width:40px;" class="rounded-pill">
                </div>
            </div>
            <!-- Droits -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="actif" id="actif" <?= $utilisateur->actif ? "checked" : "" ?>>
                        <label class="form-check-label" for="actif">Actif (peut se connecter)</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="administrateur" id="administrateur" <?= $utilisateur->administrateur ? "checked" : "" ?>>
                        <label class="form-check-label" for="administrateur">Administrateur</label>
                    </div>
                </div>
            </div>
            <!-- Soumission formulaire -->
            <?= TbHtml::htmlBoutonValidation()?>
        </div>
    </section>
</form>
<script>
function jsBasculerMotDePasse() {
    const champ = document.getElementById('motDePasse');
    const icone = document.getElementById('iconeMotDePasse');
    champ.type = champ.type === 'password' ? 'text' : 'password';
    icone.className = champ.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
function jsProposerMotDePasse(motDePasse) {
    const champ = document.getElementById('motDePasse');
    champ.value = motDePasse;
    champ.type = 'text';
    document.getElementById('iconeMotDePasse').className = 'bi bi-eye-slash';
}
</script>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));?>
