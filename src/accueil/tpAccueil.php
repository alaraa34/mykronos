<?php 
namespace theBand\src\php\accueil;
//lien vers namaspace
use shared\php\classes\personalisation\Parametre    as Parametre;
use shared\php\toolbox\Toolbox_adressage            as TbAdressage;

if (isset($mode) && $mode == "enConstruction"){
    $content = "<div id=\"pageAffichee\" class = \"d-block mx-auto img-fluid\">
	<br><br><img src='images/EnConstruction.png' alt=\"image affiche \" class=\"img-fluid  mx-auto d-block\">
    </div>";
}
else {
    $content = "<div id=\"pageAffichee\" class = \"d-block mx-auto img-fluid\">
	<br><br><img src='images/" . Parametre::mdParametreGetDetail("IMAGE", "ACCUEIL", "valeurA") . "' alt=\"logo groupe\" class=\"img-fluid  mx-auto rounded-circle d-block\">
    </div>";} 
require(TbAdressage::projetGetLayout(__NAMESPACE__));