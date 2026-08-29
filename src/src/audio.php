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

    // scandir() rend false sur un dossier illisible, et emet deux avertissements
    // avant que le foreach en emette un troisieme. Le resultat etait deja bon —
    // aucun fichier, donc null — mais il l'est maintenant sans bruit sur le
    // chemin de requete. Un seul terme : pas de branche non couverte de plus.
    foreach (@scandir($directory) ?: array() as $name) {
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

/**
 * Proprietes de telechargement d'une emission, deduites du dossier.
 *
 * C'est la couture entre getShow() et le balayage : elle construit le prefixe
 * de la convention historique, retient un fichier par format, en lit la taille,
 * et assemble l'etiquette d'enregistrement. Elle vit ici et non dans
 * bootstrap.php parce que la suite charge ce fichier et que la jauge le mesure
 * — le meme code dans getShow() n'etait tenu par rien.
 *
 * Elle ne decide pas de la publication : elle rend `hasAudio`, et l'appelant en
 * tire ce qu'il veut. `isPublic` vient aussi du manifeste, ce n'est pas a cette
 * fonction de l'ecraser.
 *
 * $number arrive tel quel, non slugifie : c'est le contrat de
 * canonicalDownloadName() ci-dessus, et le segment d'URL d'ou il vient n'est
 * pas contraint par la route.
 *
 * @param string $directory   dossier de l'emission
 * @param string $urlAssets   URL absolue de ce dossier, sans barre finale
 * @param string $typeSlug    type de l'emission, deja slugifie
 * @param string $number      numero de l'emission, tel quel
 * @param string $authorsSlug auteurs, deja slugifies
 * @param string $titleSlug   titre, deja slugifie
 *
 * @return array les cinq cles que getShow() fusionne, plus hasAudio
 */
function audioDownloads($directory, $urlAssets, $typeSlug, $number, $authorsSlug, $titleSlug)
{
    $conventionPrefix = sprintf('ouiedire_%s-%s_', $typeSlug, $number);
    $nameMp3 = findAudioFile($directory, 'mp3', $conventionPrefix);
    $nameFlac = findAudioFile($directory, 'flac', $conventionPrefix);

    // Un stat qui echoue dit que le fichier n'est pas la : lien symbolique
    // casse, fichier retire entre le balayage et ici. Le nom est alors
    // abandonne, sinon l'emission se publierait avec « 0 Mo » et un lien mort.
    // Ce n'est pas un retour au critere de lisibilite que ce change retire : un
    // fichier present mais non lisible se stat tres bien, et reste publie.
    $sizeMp3 = $nameMp3 ? @filesize($directory.'/'.$nameMp3) : false;
    $sizeFlac = $nameFlac ? @filesize($directory.'/'.$nameFlac) : false;

    return array(
        'sizeDownloadMp3' => $sizeMp3 === false ? null : round($sizeMp3 / (1024 * 1024), 2).' Mo',
        'sizeDownloadFlac' => $sizeFlac === false ? null : round($sizeFlac / (1024 * 1024), 2).' Mo',
        'urlDownloadMp3' => $sizeMp3 === false ? null : sprintf('%s/%s', $urlAssets, rawurlencode($nameMp3)),
        'urlDownloadFlac' => $sizeFlac === false ? null : sprintf('%s/%s', $urlAssets, rawurlencode($nameFlac)),
        // Etiquette d'enregistrement, independante du nom du fichier stocke.
        'canonicalDownloadName' => canonicalDownloadName($typeSlug, $number, $authorsSlug, $titleSlug),
        // Aucune publication sans audio : un seul des deux formats suffit.
        'hasAudio' => $sizeMp3 !== false || $sizeFlac !== false,
    );
}
