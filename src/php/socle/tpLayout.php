<!DOCTYPE html>
<html lang="fr">
<head>
<title>myKronos</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- application installable (PWA) : actif uniquement en https (production) -->
<link rel="manifest" href="index.php?ctr=accueil&amp;fct=manifest">
<meta name="theme-color" content="#2b2d30">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<script>
    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        navigator.serviceWorker.register('sw.js');
    }
</script>
<?php
    include_once (ROOT_PATH . 'shared/includes/bootstrap_jquery_css.php');
?>
<link rel="stylesheet" href="src/css/mykronos.css" type="text/css" media="screen" />
<?php if (isset($css)){echo $css;} ?>
</head>
<body>
    <!-- pas encore de base de données : ni menu (mk_menu), ni connexion, ni paramètres -->
    <nav class="navbar navbar-dark navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">myKronos</a>
            <span class="navbar-text">
                <?php if (isset($messageBarreMenu)){echo ($messageBarreMenu);} ?>
            </span>
        </div>
    </nav>
    <!-- AFFICHAGE PAGE -->
    <main class="container">
         <?= $content ?>
    </main>
    <!-- chargement des scripts -->
    <?php
        include_once (ROOT_PATH . 'shared/includes/bootstrap_scripts.php');
        include_once (ROOT_PATH . 'shared/includes/jquery_ui.php');
        if (isset($script)){echo $script;}
    ?>
</body>
</html>
