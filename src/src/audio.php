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

/**
 * Nom sous lequel le public telecharge l'audio, quel que soit le nom stocke.
 *
 * Cette fonction assemble et met en minuscules ; elle ne slugifie pas. La
 * transliteration est le travail de slugify(), qui vit dans bootstrap.php et
 * que l'appelant applique aux trois slugs — audio.php n'en depend pas.
 *
 * Le strtolower() n'est donc pas mort : getShow() ne slugifie pas $number, qui
 * arrive tel quel du segment d'URL — et la route ne contraint pas ce segment.
 * Voir AudioTest::testLeNomCanoniqueMetLeNumeroEnMinuscules.
 *
 * Il travaille sur des octets, pas sur des caracteres : sous les locales C et
 * UTF-8, une entree accentuee ressort avec ses accents et leur casse intacts.
 * L'absolu serait faux — jusqu'en PHP 8.1, strtolower() suit LC_CTYPE, et une
 * locale mono-octet mutilerait l'UTF-8. Voir
 * AudioTest::testLeNomCanoniqueLaisseLesAccentsIntacts. Par le chemin d'appel
 * de getShow(), les trois slugs sont translitteres en amont : l'entree
 * accentuee ne peut atteindre cette fonction que par $number.
 *
 * @param string $typeSlug    type de l'emission, deja slugifie
 * @param string $number      numero de l'emission, tel quel
 * @param string $authorsSlug auteurs, deja slugifies
 * @param string $titleSlug   titre, deja slugifie
 *
 * @return string nom sans extension
 */
function canonicalDownloadName($typeSlug, $number, $authorsSlug, $titleSlug)
{
    return strtolower(sprintf('ouiedire_%s-%s_%s_%s', $typeSlug, $number, $authorsSlug, $titleSlug));
}
