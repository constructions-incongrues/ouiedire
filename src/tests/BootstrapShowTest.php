<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ce que bootstrap.php est seul a decider, tenu par la suite.
 *
 * bootstrap.php n'est amorce par aucun autre test : c'est le constat de la
 * Task 5, et il a un cout mesure. Deux mutations y survivaient a la suite
 * entiere — supprimer l'appel a paddedShowNumber() dans getShow(), et batir le
 * prefixe des couvertures sur le numero rempli. Leur seul juge etait un script
 * var_export() NON VERSIONNE, recopie dans un document : la prochaine
 * regression de cette forme aurait ete attrapee par quelqu'un qui pense a
 * lancer un script a la main, ou pas attrapee. Ce fichier verse ce juge dans le
 * depot.
 *
 * Il lit l'archive REELLE, et ce n'est pas un raccourci : le $pathPublic de
 * getShow() est code en dur, il n'existe aucun dossier de fixtures a lui
 * substituer. Poser des fixtures reviendrait a ecrire dans l'arbre versionne des
 * emissions — precisement le geste a ne pas faire. Le releve, lui, ne fait que
 * lire : getShow() ouvre index.json et balaye le dossier.
 *
 * Le releve tourne dans un PROCESSUS FILS. Charger bootstrap.php ici y poserait
 * un $app Silex, une locale et une trentaine de fonctions globales, pour toute
 * la suite. Le procede est celui de CoverageCheckTest::runCli().
 *
 * @see docs de la Task 8 du change audio-sans-convention pour la table de mutation.
 */
class BootstrapShowTest extends TestCase
{
    /** @var array|null le releve, calcule une fois pour tous les tests */
    private static $releve;

    /**
     * Lance le releve et rend son contenu, indexe par nom de dossier.
     *
     * @return array
     */
    private function releve()
    {
        if (null !== self::$releve) {
            return self::$releve;
        }

        $cmd = 'php '.escapeshellarg(__DIR__.'/support/probe-bootstrap.php');
        $pipes = [];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (false === $proc) {
            $this->fail('proc_open a echoue : '.$cmd);
        }
        $sortie = stream_get_contents($pipes[1]);
        $erreurs = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        // Un avertissement PHP sur le chemin de getShow() est un defaut a part
        // entiere, et il ne doit pas se perdre dans un releve par ailleurs vert.
        $this->assertSame('', $erreurs, "Le releve a ecrit sur stderr :\n".$erreurs);
        $this->assertSame(0, $code, 'Le releve a rendu '.$code.' : '.$cmd);

        $releve = json_decode($sortie, true);
        $this->assertIsArray($releve, 'Le releve ne rend pas du JSON : '.substr($sortie, 0, 200));
        $this->assertNotEmpty($releve, "Le releve est vide : l'archive n'a pas ete lue.");

        return self::$releve = $releve;
    }

    public function testLeNumeroAfficheEstRempliATroisChiffresSurToutelArchive()
    {
        $releve = $this->releve();

        $remplis = 0;
        foreach ($releve as $dossier => $show) {
            $this->assertMatchesRegularExpression(
                '/^\d{3}/',
                $show['number'],
                "$dossier : le numero affiche n'est pas rempli a trois chiffres."
            );
            // Et le brut reste lisible : c'est lui qui nomme le dossier, les URL
            // d'assets et les couvertures. Le remplissage ne doit pas l'ecraser.
            $this->assertSame(
                substr($dossier, strrpos($dossier, '-') + 1),
                $show['id'],
                "$dossier : \$show['id'] ne porte pas le numero brut du dossier."
            );
            if ($show['number'] !== $show['id']) {
                ++$remplis;
            }
        }

        // Sans cette borne l'assertion ci-dessus serait vide de sens sur une
        // archive dont tous les numeros feraient deja trois chiffres.
        $this->assertGreaterThan(
            0,
            $remplis,
            'Aucune emission ne voit son numero rempli : la regle ne prouve rien ici.'
        );
    }

    public function testLeNumeroBrutResteLisibleDansId()
    {
        $releve = $this->releve();

        // Les deux temoins du change : un numero a un chiffre, et le seul
        // numero non numerique de l'archive.
        $this->assertArrayHasKey('ailleurs-1', $releve);
        $this->assertArrayHasKey('ailleurs-17bis', $releve);

        $this->assertSame('1', $releve['ailleurs-1']['id']);
        $this->assertSame('001', $releve['ailleurs-1']['number']);
        $this->assertSame('17bis', $releve['ailleurs-17bis']['id']);
        $this->assertSame('017bis', $releve['ailleurs-17bis']['number']);
    }

    public function testLePrefixeDeCouvertureResteBatiSurLeNumeroBrut()
    {
        $releve = $this->releve();

        // Les couvertures de l'archive portent le numero BRUT
        // (ouiedire_bagage-7_cover-1.png), la ou les audios portent le rempli.
        // bagage-7 est le seul dossier ou ce choix soit observable : il porte
        // AUSSI la couverture d'une autre emission. Avec le prefixe brut, la
        // sienne est reconnue et passe devant ; avec un prefixe rempli, plus
        // rien ne la distingue et l'etrangere gagne au tri alphabetique — donc
        // en og:image et dans le flux RSS, qui lisent covers[0] sans garde.
        $this->assertArrayHasKey(
            'bagage-7',
            $releve,
            'bagage-7 a disparu : ce test ne temoigne plus de rien, il faut lui trouver un successeur.'
        );
        $this->assertSame(
            ['ouiedire_bagage-7_cover-1.png', 'ouiedire_bagage-6_cover-2.png'],
            $releve['bagage-7']['covers'],
            "La couverture de bagage-7 ne passe plus devant celle de bagage-6."
        );
    }
}
