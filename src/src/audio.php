<?php

/**
 * Trouve le fichier audio d'une emission dans son dossier.
 *
 * Le nom du fichier n'est pas une donnee : n'importe quel fichier de
 * l'extension demandee fait l'affaire. Voir le change audio-sans-convention.
 *
 * @param string $directory        dossier de l'emission
 * @param string $extension        'mp3' ou 'flac', sans point
 * @param string $conventionPrefix prefixe de la convention historique,
 *                                 p.ex. 'ouiedire_ailleurs-331_'
 *
 * @return string|null nom du fichier retenu, ou null si le dossier n'en porte aucun
 */
function findAudioFile($directory, $extension, $conventionPrefix)
{
    if (!is_dir($directory)) {
        return null;
    }

    $conventional = array();
    $others = array();

    foreach (scandir($directory) as $name) {
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $extension) {
            continue;
        }
        if (strpos($name, $conventionPrefix) === 0) {
            $conventional[] = $name;
        } else {
            $others[] = $name;
        }
    }

    // Tri par octets, indispensable : scandir() trie avec strcoll(), sensible a
    // LC_COLLATE, alors que sort() compare des octets. Sous une collation non-C
    // les deux ordres divergent et le fichier retenu change. bootstrap.php ne
    // pose aujourd'hui que LC_CTYPE : la divergence est a un LC_ALL pres, pas
    // impossible. Voir AudioTest::testLOrdreRetenuEstCeluiDesOctetsPasCeluiDeLaCollation.
    sort($conventional);
    sort($others);
    $found = array_merge($conventional, $others);

    return $found ? $found[0] : null;
}
