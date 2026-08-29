<?php

/**
 * Releve ce que getShow() rend sur chaque emission de l'archive, en JSON.
 *
 * Ce n'est pas un test : c'est le processus fils que BootstrapShowTest lance.
 * bootstrap.php pose un $app Silex, une locale et une trentaine de fonctions
 * globales en se chargeant — le faire dans le processus de la suite polluerait
 * les autres tests. Il ne rend la main a personne : aucun ->run() n'est appele
 * a l'inclusion, seules des routes sont declarees.
 *
 * Trois clefs seulement, celles dont bootstrap.php est le SEUL juge :
 * `id` et `number` pour le remplissage a trois chiffres, `covers` pour le
 * prefixe qui designe la couverture de cette emission-ci.
 *
 * Lecture seule : getShow() ouvre index.json et balaye le dossier, elle n'ecrit
 * rien. C'est ce qui autorise ce releve a viser l'archive versionnee — le
 * $pathPublic de getShow() est code en dur, il n'y a pas de dossier de fixtures
 * a lui substituer.
 */

// bootstrap.php lit $debug a l'inclusion.
$debug = false;
require __DIR__.'/../../src/bootstrap.php';

$base = __DIR__.'/../../public/assets/emission';
$releve = array();
foreach (scandir($base) as $entree) {
    if ('.' === $entree[0] || !is_dir($base.'/'.$entree)) {
        continue;
    }
    try {
        $show = getShow($entree);
    } catch (\Exception $e) {
        // index.json absent ou illisible : l'emission n'est pas servie non plus.
        continue;
    }
    $releve[$entree] = array(
        'id' => $show['id'],
        'number' => $show['number'],
        'covers' => array_map('basename', $show['covers']),
    );
}

echo json_encode($releve), "\n";
