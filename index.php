<?php   

    use shared\php\toolbox\Toolbox_index    as tbIndex;
    
    session_start();
    //ROOT est la racine du projet
    define('__ROOT__', dirname(__FILE__)); //exemple "C:\wamp64\www\theBand"
    define('PREFIXE_BDD','mk_');
    
    //chargement de l'autoload
    define('ROOT_PATH', filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_URL) . '/');
    
    require_once(ROOT_PATH . 'shared/php/autoload.php');
    
    tbIndex::demarrer("mykronos");

    