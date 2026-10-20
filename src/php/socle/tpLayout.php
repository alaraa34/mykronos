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
<!-- ?v= date du fichier : le navigateur recharge la feuille de style dès qu'elle change -->
<link rel="stylesheet" href="src/css/mykronos.css?v=<?= @filemtime(__DIR__ . '/../../css/mykronos.css') ?>" type="text/css" media="screen" />
<?php if (isset($css)){echo $css;} ?>
</head>
<body>
    <!-- menu construit depuis mk_menu (pas encore de connexion : table user absente) -->
    <nav class="navbar navbar-expand-md navbar-dark navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">myKronos</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav">
                    <?= (new \shared\php\classes\socle\Menu())->myhtml ?>
                </ul>
            </div>
            <span class="navbar-text">
                <?php if (isset($messageBarreMenu)){echo ($messageBarreMenu);} ?>
            </span>
        </div>
    </nav>
    <!-- indicateur "traitement en cours" : affiché si la page suivante tarde (import, détection...) -->
    <div id="traitementEnCours" class="traitement-en-cours d-none" role="status" aria-live="polite">
        <div class="traitement-boite">
            <div class="spinner-border" aria-hidden="true"></div>
            <div class="mt-2" id="traitementTexte">Traitement en cours…</div>
        </div>
    </div>
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
    <script>
    //indicateur de traitement : sur un lien de l'appli ou un formulaire, affiché après un court délai
    //(pas sur les téléchargements, ni sur les liens qui ouvrent un menu ou un repli)
    (function () {
        const voile = document.getElementById('traitementEnCours');
        let minuteur = null;
        function afficher(texte) {
            clearTimeout(minuteur);
            minuteur = setTimeout(function () {
                document.getElementById('traitementTexte').textContent = texte || 'Traitement en cours…';
                voile.classList.remove('d-none');
            }, 400);
        }
        document.addEventListener('click', function (evt) {
            const lien = evt.target.closest('a[href]');
            if (!lien || evt.defaultPrevented || evt.ctrlKey || evt.metaKey || evt.shiftKey || lien.target === '_blank') {return;}
            const href = lien.getAttribute('href');
            if (href.startsWith('#') || lien.hasAttribute('data-bs-toggle') || href.includes('fct=telecharger')) {return;}
            afficher(lien.dataset.attente);
        });
        document.addEventListener('submit', function (evt) {
            if (!evt.defaultPrevented) {afficher(evt.target.dataset.attente);}
        });
        //retour arrière (page restaurée depuis le cache) : on masque
        window.addEventListener('pageshow', function () {clearTimeout(minuteur); voile.classList.add('d-none');});
    })();
    </script>
</body>
</html>
