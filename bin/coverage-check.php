<?php

/**
 * Verifie la couverture d'UN fichier dans un rapport Clover.
 *
 * --coverage-text ne rend qu'un total, domine par bootstrap.php, et n'affiche
 * pas les fonctions libres. sdr-004 demande un seuil sur le code touche : il
 * faut donc lire Clover, qui donne le detail par fichier.
 *
 * Un fichier absent du rapport est un ECHEC, pas un succes : c'est ce qui
 * attrape un filtre de couverture casse. Une correspondance AMBIGUE l'est
 * aussi : rendre le premier match laisserait un homonyme du vendor, couvert a
 * 100 %, valider le seuil a la place du fichier reel.
 *
 * @return int 0 si le seuil est atteint, 1 sinon
 */
function coverageCheck($cloverPath, $needle, $threshold)
{
    if (!is_readable($cloverPath)) {
        fwrite(STDERR, sprintf("Rapport illisible : %s\n", $cloverPath));

        return 1;
    }

    $xml = @simplexml_load_file($cloverPath);
    if ($xml === false) {
        fwrite(STDERR, sprintf("Rapport malformé : %s\n", $cloverPath));

        return 1;
    }

    // Ancrage en QUEUE DE CHEMIN, pas en sous-chaine : Clover nomme ses fichiers
    // par un chemin absolu, et les appelants passent toujours une fin de ce
    // chemin. Sans l'ancrage, « audio.php » designerait aussi « mon_audio.php »
    // et « audio.php.bak ».
    $suffixe = '/'.ltrim($needle, '/');
    $trouves = [];
    foreach ($xml->xpath('//file') as $file) {
        $name = (string) $file['name'];
        if (substr($name, -strlen($suffixe)) === $suffixe) {
            // Liste, pas index par nom : un rapport fusionne peut porter deux
            // fois le meme chemin, et les ecraser rendrait l'ambiguite muette.
            $trouves[] = ['name' => $name, 'file' => $file];
        }
    }

    if (count($trouves) === 0) {
        fwrite(STDERR, sprintf(
            "%s absent du rapport de couverture — filtre casse ou fichier jamais charge.\n",
            $needle
        ));

        return 1;
    }

    if (count($trouves) > 1) {
        fwrite(STDERR, sprintf(
            "%s correspond a %d fichiers du rapport : %s — la mesure serait ambigue.\n",
            $needle,
            count($trouves),
            implode(', ', array_column($trouves, 'name'))
        ));

        return 1;
    }

    $name = $trouves[0]['name'];
    $file = $trouves[0]['file'];
    if (!isset($file->metrics['statements'])) {
        // Un <file> sans <metrics> — ou un <metrics> sans son compte — donne les
        // memes zeros qu'un fichier sans instruction. Le second est legitime, le
        // premier est un rapport degrade : les distinguer est tout l'interet du
        // controle. Une seule condition couvre les deux formes : sur SimpleXML,
        // isset() est faux des que l'un des deux maillons manque.
        fwrite(STDERR, sprintf("%s sans compte d'instructions dans <metrics> — rapport degrade.\n", $name));

        return 1;
    }

    $statements = (int) $file->metrics['statements'];
    $covered = (int) $file->metrics['coveredstatements'];
    $ratio = $statements > 0 ? 100.0 * $covered / $statements : 100.0;
    printf("%s : %.2f %% (%d/%d), seuil %.2f %%\n",
        $name, $ratio, $covered, $statements, $threshold);

    return $ratio + 1e-9 >= $threshold ? 0 : 1;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(coverageCheck(
        $argv[1] ?? 'clover.xml',
        $argv[2] ?? 'audio.php',
        isset($argv[3]) ? (float) $argv[3] : 90.0
    ));
}
