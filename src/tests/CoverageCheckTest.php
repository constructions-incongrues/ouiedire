<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../bin/coverage-check.php';

class CoverageCheckTest extends TestCase
{
    /** @var string[] chemins temporaires a delier apres chaque test */
    private $temporaires = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaires as $chemin) {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
        foreach ($this->temporaires as $chemin) {
            if (is_dir($chemin)) {
                rmdir($chemin);
            }
        }
        $this->temporaires = [];
    }

    private function clover(array $files)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><coverage><project>';
        foreach ($files as $name => $m) {
            $xml .= sprintf(
                '<file name="%s"><metrics statements="%d" coveredstatements="%d"/></file>',
                $name, $m[0], $m[1]
            );
        }

        return $xml.'</project></coverage>';
    }

    private function write($xml)
    {
        // tempnam cree deja un fichier ; le rapport en est un second. Les deux
        // sont enregistres, sinon chaque test en laisse une paire dans /tmp.
        $base = tempnam(sys_get_temp_dir(), 'clover');
        $path = $base.'.xml';
        file_put_contents($path, $xml);
        $this->temporaires[] = $base;
        $this->temporaires[] = $path;

        return $path;
    }

    /**
     * Un dossier contenant un clover.xml, pour observer le CLI lance sans
     * aucun argument : le chemin par defaut se resout depuis le dossier courant.
     */
    private function dossierAvecClover($xml)
    {
        $dossier = tempnam(sys_get_temp_dir(), 'cloverdir');
        unlink($dossier);
        mkdir($dossier);
        $this->temporaires[] = $dossier;
        $chemin = $dossier.'/clover.xml';
        file_put_contents($chemin, $xml);
        $this->temporaires[] = $chemin;

        return $dossier;
    }

    /**
     * Appelle le controle en capturant sa ligne de rapport : sans cela, les
     * lignes s'entrelacent avec la sortie de progression de PHPUnit.
     *
     * @return array [code de retour, sortie standard]
     */
    private function appel($cloverPath, $needle, $threshold)
    {
        ob_start();
        $code = coverageCheck($cloverPath, $needle, $threshold);

        return [$code, ob_get_clean()];
    }

    public function testSeuilAtteint()
    {
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 10]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(0, $code);
    }

    public function testSeuilAtteintExactement()
    {
        // La frontiere : 9/10 vaut exactement le seuil, et doit passer.
        // Sans ce test, affaiblir ">=" en ">" laisse la suite verte.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 9]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(0, $code);
    }

    public function testUnEcartDUnUlpNeFaitPasEchouerUnSeuilAtteint()
    {
        // 100*5/6 et 5/6*100 designent le meme pourcentage et ne rendent pas
        // le meme double. Le seuil est atteint : la comparaison ne doit pas
        // trancher sur le dernier bit. C est ce que l epsilon protege.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [6, 5]]));

        list($code) = $this->appel($path, 'audio.php', 5 / 6 * 100.0);

        $this->assertSame(0, $code);
    }

    public function testSeuilDepassantLeRatioDExactementUnEpsilonEstAtteint()
    {
        // 90.000000001 est le double exactement egal a (9/10 en % ) + 1e-9.
        // C'est la seule entree qui separe ">=" de ">" une fois l'epsilon pose,
        // et elle est atteignable : le CLI construit son seuil par (float) $argv[3].
        // Sans ce test, affaiblir ">=" en ">" laisse la suite verte.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 9]]));

        list($code) = $this->appel($path, 'audio.php', 90.000000001);

        $this->assertSame(0, $code);
    }

    public function testUnDixiemeDePointSousLeSeuilEchoue()
    {
        // Les deux tests ci-dessus pinnent que l'epsilon EXISTE, jamais qu'il
        // est petit : leurs contre-exemples sont a 40 points du seuil. Sans
        // cette borne, elargir la tolerance a 0,1 point — ou a 5 — laisse la
        // suite verte, et une couverture de 89,9 % passerait une jauge a 90 %.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [1000, 899]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testDeuxEntreesDeMemeNomSontUneAmbiguite()
    {
        // Un rapport fusionne (phpcov merge, shards paralleles) peut porter
        // deux <file> du MEME chemin. Les indexer par nom les ecraserait l'un
        // l'autre en silence, et l'ordre du document deciderait du verdict.
        $xml = '<?xml version="1.0" encoding="UTF-8"?><coverage><project>'
            .'<file name="/app/src/src/audio.php"><metrics statements="100" coveredstatements="0"/></file>'
            .'<file name="/app/src/src/audio.php"><metrics statements="10" coveredstatements="10"/></file>'
            .'</project></coverage>';
        $path = $this->write($xml);

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testLeNomCherchePeutPorterPlusieursSegments()
    {
        // Le nom cherche est une QUEUE de chemin, pas un simple nom de fichier :
        // « src/src/audio.php » doit designer le meme fichier, et un chemin
        // absolu aussi — c'est ce que le ltrim() du separateur de tete permet.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 10]]));

        list($codeSegments) = $this->appel($path, 'src/src/audio.php', 90.0);
        list($codeAbsolu) = $this->appel($path, '/app/src/src/audio.php', 90.0);
        // Et les segments comptent : les reduire au nom de fichier ferait
        // correspondre n'importe quel dossier.
        list($codeAutreDossier) = $this->appel($path, 'autre/audio.php', 90.0);

        $this->assertSame(0, $codeSegments);
        $this->assertSame(0, $codeAbsolu);
        $this->assertSame(1, $codeAutreDossier);
    }

    public function testFichierImbriqueDansUnPackageEstTrouve()
    {
        // PHPUnit enveloppe les classes a namespace dans un <package>. Restreindre
        // la recherche a //coverage/project/file rendrait ces fichiers introuvables,
        // donc « absents », sur un rapport qui en contient.
        $xml = '<?xml version="1.0" encoding="UTF-8"?><coverage><project><package name="Ouiedire">'
            .'<file name="/app/src/src/audio.php"><metrics statements="10" coveredstatements="10"/></file>'
            .'</package></project></coverage>';
        $path = $this->write($xml);

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(0, $code);
    }

    public function testBlocMetricsSansAttributStatements()
    {
        // Meme degradation qu'un <metrics> absent : sans l'attribut, le compte
        // vaut 0 et devient indiscernable d'un fichier sans instruction. Garder
        // l'une des deux gardes sans l'autre laisse le raisonnement a moitie.
        $xml = '<?xml version="1.0" encoding="UTF-8"?><coverage><project>'
            .'<file name="/app/src/src/audio.php"><metrics/></file>'
            .'</project></coverage>';
        $path = $this->write($xml);

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testSeuilNonAtteint()
    {
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 5]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testFichierPresentMaisSansAucuneInstruction()
    {
        // Present dans le rapport, mais rien a couvrir : ce n est pas un defaut
        // de couverture. Le filtre casse, lui, est attrape par le cas absent.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [0, 0]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(0, $code);
    }

    public function testFichierAbsentDuRapport()
    {
        // Le cas qui compte : filtre casse, aucune entree, le controle doit echouer.
        // La fixture est couverte a 100 % : retirer le filtre du needle rendrait
        // alors 0, et ce test le verrait. Avec un fichier a 0 %, il passerait
        // pour la mauvaise raison.
        $path = $this->write($this->clover(['/app/src/src/bootstrap.php' => [507, 507]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testLeDiagnosticDeLAbsenceNommeLeFichierCherche()
    {
        $path = $this->write($this->clover(['/app/src/src/bootstrap.php' => [507, 507]]));

        list($code, , $err) = $this->runCli([$path, 'audio.php', '90']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('audio.php absent du rapport', $err);
    }

    public function testPlusieursFichiersCorrespondantsSontUnEchec()
    {
        // Le vendor est couvert a 100 %, le fichier reel a 0 %. Rendre le premier
        // match masquerait exactement le filtre casse que ce controle existe pour
        // attraper : une correspondance ambigue est un echec.
        $path = $this->write($this->clover([
            '/app/vendor/x/audio.php' => [10, 10],
            '/app/src/src/audio.php' => [100, 0],
        ]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testLeDiagnosticDAmbiguiteListeLesNomsTrouves()
    {
        $path = $this->write($this->clover([
            '/app/vendor/x/audio.php' => [10, 10],
            '/app/src/src/audio.php' => [100, 0],
        ]));

        list($code, , $err) = $this->runCli([$path, 'audio.php', '90']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('/app/vendor/x/audio.php', $err);
        $this->assertStringContainsString('/app/src/src/audio.php', $err);
    }

    public function testLeNomCherchePorteSurUneQueueDeChemin()
    {
        // « audio.php » ne designe pas « mon_audio.php » : sans ancrage, un
        // homonyme partiel couvert a 100 % validerait le seuil a sa place.
        $path = $this->write($this->clover(['/app/src/src/mon_audio.php' => [10, 10]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testLeNomCherchePorteSurLaFinDuNom()
    {
        // Ni « audio.php.bak », pour la meme raison, du cote du suffixe.
        $path = $this->write($this->clover(['/app/src/src/audio.php.bak' => [10, 10]]));

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testLaLigneDeRapportNommeLeFichierMesure()
    {
        // Le nom affiche est celui du fichier trouve, pas la queue de chemin
        // demandee : l'operateur doit voir SUR QUOI le seuil a ete mesure.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 9]]));

        list($code, $out) = $this->runCli([$path, 'audio.php', '90']);

        $this->assertSame(0, $code);
        $this->assertSame(
            "/app/src/src/audio.php : 90.00 % (9/10), seuil 90.00 %\n",
            $out
        );
    }

    public function testFichierSansBlocMetrics()
    {
        // Un <file> sans <metrics> est un rapport degrade, pas un fichier vide :
        // les deux donnent statements = 0, et seul le premier est un echec.
        $xml = '<?xml version="1.0" encoding="UTF-8"?><coverage><project>'
            .'<file name="/app/src/src/audio.php"/>'
            .'</project></coverage>';
        $path = $this->write($xml);

        list($code, , $err) = $this->runCli([$path, 'audio.php', '90']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('metrics', $err);
    }

    /**
     * Lance le controle comme un operateur le fait, pour observer STDERR :
     * fwrite(STDERR) echappe a la capture de sortie de PHPUnit.
     *
     * @param string[] $arguments arguments de ligne de commande, tels quels
     * @param string   $cwd       dossier courant du processus, ou null
     *
     * @return array [code de sortie, stdout, stderr]
     */
    private function runCli(array $arguments = [], $cwd = null)
    {
        $cmd = 'php '.escapeshellarg(__DIR__.'/../../bin/coverage-check.php');
        foreach ($arguments as $argument) {
            $cmd .= ' '.escapeshellarg($argument);
        }

        $pipes = [];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if ($proc === false) {
            // Sans cette garde, les fclose qui suivent partent en fatal illisible.
            $this->fail('proc_open a echoue : '.$cmd);
        }

        // Lecture non bloquante des deux tuyaux : lire l'un jusqu'au bout avant
        // l'autre interbloque des que le tampon du second se remplit.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $limite = microtime(true) + 10.0;
        $code = -1;
        while (true) {
            $etat = proc_get_status($proc);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if (!$etat['running']) {
                $code = $etat['exitcode'];
                break;
            }
            if (microtime(true) > $limite) {
                proc_terminate($proc);
                $this->fail('le controle ne rend pas la main : '.$cmd);
            }
            usleep(2000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return [$code, $stdout, $stderr];
    }

    public function testSansArgumentLeControleLitCloverXmlDuDossierCourant()
    {
        // Les trois defauts a la fois : clover.xml, audio.php, seuil 90.
        $dossier = $this->dossierAvecClover(
            $this->clover(['/app/src/src/audio.php' => [10, 10]])
        );

        list($code, $out) = $this->runCli([], $dossier);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('/app/src/src/audio.php : 100.00 %', $out);
    }

    public function testSansArgumentLeSeuilParDefautEstDeQuatreVingtDix()
    {
        // Un defaut affaibli a 0 rendrait 0 sur cette meme fixture.
        $dossier = $this->dossierAvecClover(
            $this->clover(['/app/src/src/audio.php' => [10, 5]])
        );

        list($code) = $this->runCli([], $dossier);

        $this->assertSame(1, $code);
    }

    public function testLeSeuilPasseEnArgumentEstPrisEnCompte()
    {
        // 90 % mesures, seuil 100 exige : un CLI qui jette son troisieme
        // argument rendrait 0 en retombant sur le defaut.
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 9]]));

        list($code) = $this->runCli([$path, 'audio.php', '100']);

        $this->assertSame(1, $code);
    }

    public function testLeFichierPasseEnArgumentEstPrisEnCompte()
    {
        // Deux fichiers, on demande l'autre : un CLI qui jette son deuxieme
        // argument mesurerait audio.php et le dirait.
        $path = $this->write($this->clover([
            '/app/src/src/audio.php' => [10, 10],
            '/app/src/src/bootstrap.php' => [10, 9],
        ]));

        list($code, $out) = $this->runCli([$path, 'bootstrap.php', '90']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('/app/src/src/bootstrap.php : 90.00 %', $out);
    }

    public function testLeDiagnosticDistingueLIllisibleDuMalforme()
    {
        // Les deux rendent 1, mais l'operateur doit savoir lequel des deux :
        // un droit d'acces et un XML casse ne se reparent pas pareil.
        list($codeAbsent, , $errAbsent) = $this->runCli(['/nexiste/pas.xml', 'audio.php', '90']);
        list($codeMalforme, , $errMalforme) = $this->runCli([
            $this->write('<coverage><project><file name='),
            'audio.php',
            '90',
        ]);

        $this->assertSame(1, $codeAbsent);
        $this->assertSame(1, $codeMalforme);
        $this->assertStringContainsString('Rapport illisible', $errAbsent);
        $this->assertStringContainsString('Rapport malformé', $errMalforme);
        $this->assertStringNotContainsString('malformé', $errAbsent);
        $this->assertStringNotContainsString('illisible', $errMalforme);
    }

    public function testRapportMalforme()
    {
        // Chemin distinct de l illisible : le fichier existe et se lit, mais
        // n est pas du XML. Sans ce test, un parse rate pourrait rendre 0.
        $path = $this->write('<coverage><project><file name=');

        list($code) = $this->appel($path, 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }

    public function testRapportIllisible()
    {
        list($code) = $this->appel('/nexiste/pas.xml', 'audio.php', 90.0);

        $this->assertSame(1, $code);
    }
}
