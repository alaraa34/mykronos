<?php
namespace mykronos\src\php\fichier;
/*******************************************************************************
 * Compte rendu de l'import direct des PCG
 * Variable attendue : $compteRendu (voir ImportPcg)
 ******************************************************************************/
use shared\php\toolbox\Toolbox_adressage as TbAdressage;

$e = fn($valeur) => htmlspecialchars((string)$valeur);
$libelles = [   //compteur => [libellé, couleur]
    'crees'     => ['Sons créés', 'success'],     'modifies' => ['Modifiés', 'primary'],
    'inchanges' => ['Inchangés', 'secondary'],    'supprimes' => ['Supprimés', 'danger'],
    'combis'    => ['Combis composées', 'dark'],  'liens'     => ['Timbres', 'dark'],
    'setlists'  => ['Set lists', 'info'],         'slots'     => ['Emplacements', 'info'],
];
$fichiers  = $compteRendu['fichiers'];
$reussis   = array_filter($fichiers, fn($f) => count($f['anomalies']) === 0);
$enEchec   = array_filter($fichiers, fn($f) => count($f['anomalies']) > 0);
ob_start(); ?>
<section class="py-4">
    <h2 class="h4 mb-3">Import des fichiers PCG</h2>

    <?php foreach ($compteRendu['anomalies'] as $anomalie): ?>
        <div class="alert alert-danger py-2"><?= $e($anomalie) ?></div>
    <?php endforeach; ?>

    <?php if (count($fichiers) === 0 && count($compteRendu['anomalies']) === 0): ?>
        <p class="text-muted">Aucun fichier à importer.</p>
    <?php else: ?>
        <p>
            <span class="badge text-bg-success">Importés <?= count($reussis) ?></span>
            <span class="badge text-bg-danger">En anomalie <?= count($enEchec) ?></span>
        </p>
    <?php endif; ?>

    <?php foreach (array_merge($enEchec, $reussis) as $f): $ok = count($f['anomalies']) === 0; ?>
    <div class="card mb-2 border-<?= $ok ? 'success' : 'danger' ?>">
        <div class="card-header py-1 d-flex justify-content-between flex-wrap gap-2">
            <span class="fw-semibold"><?= $e($f['fichier']) ?></span>
            <span class="small text-muted"><?= $e($f['modele']) ?></span>
        </div>
        <div class="card-body py-2">
            <?php foreach ($f['compteurs'] as $cle => $valeur): [$libelle, $couleur] = $libelles[$cle]; ?>
                <span class="badge text-bg-<?= $couleur ?>"><?= $libelle ?> <?= (int)$valeur ?></span>
            <?php endforeach; ?>
            <?php if (count($f['categories']) > 0): ?>
                <div class="mt-1 small"><strong>Créées « à renseigner » :</strong> <?= $e(implode(" ; ", $f['categories'])) ?></div>
            <?php endif; ?>
            <?php if (!$ok): ?>
                <div class="small text-danger">Rien n'a été enregistré pour ce fichier :</div>
                <?php foreach (array_slice($f['anomalies'], 0, 10) as $anomalie): ?>
                    <div class="small text-danger">• <?= $e($anomalie) ?></div>
                <?php endforeach; ?>
                <?php if (count($f['anomalies']) > 10): ?>
                    <div class="small text-danger">… et <?= count($f['anomalies']) - 10 ?> autres</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <a class="btn btn-dark btn-sm mt-2" href="<?= TbAdressage::getURLstatic('fichier', 'afficherFichierATraiter') ?>">Fichiers à traiter</a>
</section>
<?php
$content = ob_get_clean();
require(TbAdressage::projetGetLayout(__NAMESPACE__));
