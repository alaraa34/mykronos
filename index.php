<?php   

    use shared\php\toolbox\Toolbox_index    as tbIndex;
    
    //session propre au projet : sur alaraa.fr les projets partagent le même domaine,
    //sans nom distinct ils partageraient aussi la session (configuration ENVIR et menu d'un autre projet)
    session_name('mykronos');
    session_start();
    //ROOT est la racine du projet
    define('__ROOT__', dirname(__FILE__)); //exemple "C:\wamp64\www\theBand"
    define('PREFIXE_BDD','mk_');
    
    //chargement de l'autoload
    define('ROOT_PATH', filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_URL) . '/');
    
    require_once(ROOT_PATH . 'shared/php/autoload.php');
    
    tbIndex::demarrer("mykronos");

    