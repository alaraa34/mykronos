<?php
namespace mykronos\src\php\accueil;
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

ob_start(); ?>
<div id="pageAffichee" class="text-center py-5">
    <img src="icons/kronos.png" alt="Korg Kronos" class="img-fluid mx-auto d-block">
    <h1 class="mt-4"><?= NOM_APPLI ?></h1>
</div>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
