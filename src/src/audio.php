<?php

/**
 * Trouve le fichier audio d'une emission dans son dossier.
 *
 * Le nom du fichier n'est pas une donnee : n'importe quel fichier de
 * l'extension demandee fait l'affaire. Voir le change audio-sans-convention.
 *
 * L'archive n'ecrit pas le numero d'une seule facon : ses dossiers et ses
 * couvertures portent la forme brute (ailleurs-1), ses audios la forme remplie
 * a trois chiffres (ouiedire_ailleurs-001_). La fonction recoit donc PLUSIEURS
 * prefixes et les traite a egalite — n'en honorer qu'un rendait la regle
 * inerte sur 136 des 367 emissions. Voir
 * AudioTest::testLesDeuxFormesDuNumeroSontReconnues.
 *
 * @param string $directory          dossier de l'emission
 * @param string $extension          'mp3' ou 'flac', sans point
 * @param array  $conventionPrefixes prefixes de la convention historique,
 *                                   p.ex. ['ouiedire_ailleurs-1_',
 *                                   'ouiedire_ailleurs-001_']
 *
 * @return string|null nom du fichier retenu, ou null si le dossier n'en porte aucun
 */
function findAudioFile($directory, $extension, array $conventionPrefixes)
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
        // Pas de `break` : sur un tableau de deux prefixes il ne gagne rien
        // d'observable, et une ligne qu'aucun test ne peut tuer n'a pas sa place.
        $suitLaConvention = false;
        foreach ($conventionPrefixes as $prefix) {
            if (strpos($name, $prefix) === 0) {
                $suitLaConvention = true;
            }
        }
        if ($suitLaConvention) {
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
 * Numero d'emission tel que le site l'affiche : rempli a trois chiffres.
 *
 * Cette regle etait ecrite dans getShow(), APRES le bloc audio — le prefixe de
 * la convention se batissait donc sur « 1 » quand le fichier portait « 001 ».
 * Elle vit ici, et getShow() la lit au meme endroit : deux exemplaires
 * divergeraient sans bruit, et c'est exactement ce qui s'etait produit.
 *
 * Elle ne peut pas etre appliquee plus tot dans getShow() : les dossiers de
 * l'archive et les URL d'assets sont nommes au numero BRUT, et remplir en
 * amont casserait les 136 URL concernees.
 *
 * Les comparaisons sont volontairement laches, comme dans getShow() d'ou elles
 * viennent : sous PHP 7.4 « 17bis » vaut 17 face a un entier, donc le dossier
 * ailleurs-17bis rend « 017bis » — la forme exacte de son mp3. Voir
 * AudioTest::testLeRemplissageNeTronquePasUnNumeroNonNumerique.
 *
 * @param string $number numero brut, tel qu'il vient du segment d'URL
 *
 * @return string numero rempli
 */
function paddedShowNumber($number)
{
    // Decoupage explicite plutot que « $number < 10 » : la comparaison lache
    // compare des NOMBRES en PHP 7.4 et des CHAINES en PHP 8, si bien que
    // « 17bis » cesserait d'etre rempli a la montee de version, sans un mot.
    // Isoler les chiffres de tete ne depend d'aucune des deux, et rend la
    // fonction idempotente — elle est appelee depuis getShow() ET depuis
    // audioDownloads().
    if (preg_match('/^(\d+)(.*)$/', $number, $parts)) {
        return str_pad($parts[1], 3, '0', STR_PAD_LEFT).$parts[2];
    }

    return $number;
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
 * Elle ne decide pas de la publication : elle rend `hasAudio`, et
 * applyAudioDownloads() ci-dessous en tire la degradation.
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
    // Les deux formes du numero, a egalite.
    //
    // La remplie est celle des 367 audios de l'archive : mesure, AUCUN fichier
    // audio n'emploie la forme brute la ou les deux different. La brute n'est
    // donc pas justifiee par les audios — c'est la forme que portent le dossier
    // et les couvertures, et une provision pour un depot futur sous cette
    // forme, que l'outil d'edition rend possible. C'est un pari sur demain,
    // enonce comme tel, et testLaConventionResteReconnueSurLeNumeroBrut le rend
    // opposable.
    //
    // Aucune garde contre le doublon quand les deux coincident (numeros >= 100) :
    // un prefixe repete n'est observable par rien, donc aucun test ne peut tuer
    // la ligne qui l'evite — meme raison qui a fait retirer le `break` ci-dessus.
    $conventionPrefixes = array(
        sprintf('ouiedire_%s-%s_', $typeSlug, $number),
        sprintf('ouiedire_%s-%s_', $typeSlug, paddedShowNumber($number)),
    );
    $paddedNumber = paddedShowNumber($number);
    $nameMp3 = findAudioFile($directory, 'mp3', $conventionPrefixes);
    $nameFlac = findAudioFile($directory, 'flac', $conventionPrefixes);

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
        // Le numero y est REMPLI : depuis que ce nom atterrit sur le disque de
        // qui telecharge, il doit dire ce que la page affiche (« 001 ») et non
        // le brut du segment d'URL. Le remplissage se fait ici, pas dans
        // canonicalDownloadName(), dont le contrat reste « assemble et met en
        // minuscules, ne slugifie pas ».
        'canonicalDownloadName' => canonicalDownloadName($typeSlug, $paddedNumber, $authorsSlug, $titleSlug),
        // Aucune publication sans audio : un seul des deux formats suffit.
        'hasAudio' => $sizeMp3 !== false || $sizeFlac !== false,
    );
}

/**
 * Applique a une emission ce que son dossier porte, et la regle de publication.
 *
 * C'est le dernier morceau qui vivait au point d'appel, dans getShow(), ou la
 * suite n'entre pas : la fusion des cinq cles, le retrait de `hasAudio`, et la
 * garde « aucune publication sans audio ». Sept mutations y survivaient.
 *
 * `hasAudio` est une reponse interne, pas une cle d'emission : elle ne ressort
 * pas de la forme rendue, et l'appelant n'a plus rien a defaire.
 *
 * **La fonction ne fait que degrader, jamais promouvoir.** L'objection est
 * naturelle — `isPublic` vient aussi du manifeste, de quel droit l'ecraser ? —
 * mais l'exigence « Aucune publication sans audio » du spec est litteralement
 * « quelle que soit la valeur de son champ `isPublic` ». Une emission publiee
 * sans audio devient non publiee ; une emission non publiee avec audio reste
 * non publiee. Les deux sens ont leur test.
 *
 * Les trois slugs arrivent sous des cles NOMMEES, et ce n'est pas du confort :
 * PHP 7.4 n'a pas d'arguments nommes, et quatre chaines de meme type a la file
 * sont exactement ce qui a produit les mutants de permutation. Sous des cles,
 * la permutation n'est plus representable a l'appel.
 *
 * Le numero, lui, n'est pas dans $slugs : il se lit dans `$show['number']`,
 * tel quel du segment d'URL, non slugifie — le contrat de
 * canonicalDownloadName().
 *
 * @param array  $show      l'emission, telle que getShow() la tient
 * @param string $directory dossier de l'emission
 * @param string $urlAssets URL absolue de ce dossier, sans barre finale
 * @param array  $slugs     `type`, `authors`, `title`, deja translitteres
 *
 * @return array l'emission fusionnee, sans `hasAudio`
 * $show['number'] et les trois cles de $slugs — type, authors, title — sont
 * REQUISES : une cle absente vaut null en PHP 7.4, sur une simple notice, et
 * produirait un nom canonique tronque du genre « ouiedire_-331_ ». Le contrat
 * est ici parce que rien dans le code ne l'oppose.
 *
 * La fusion va dans ce sens et pas dans l'autre : ce qui a ete mesure sur le
 * disque gagne sur ce qu'un manifeste declare. Voir
 * ApplyAudioDownloadsTest::testLesValeursCalculeesGagnentSurCellesDuManifeste.
 *
 */
function applyAudioDownloads(array $show, $directory, $urlAssets, array $slugs)
{
    $audio = audioDownloads(
        $directory,
        $urlAssets,
        $slugs['type'],
        $show['number'],
        $slugs['authors'],
        $slugs['title']
    );

    $hasAudio = $audio['hasAudio'];
    unset($audio['hasAudio']);
    $show = array_merge($show, $audio);

    // Aucune publication sans audio. Degradation seule : le `if` ne pose jamais
    // true, donc une emission hors ligne au manifeste le reste.
    if (!$hasAudio) {
        $show['isPublic'] = false;
    }

    return $show;
}
