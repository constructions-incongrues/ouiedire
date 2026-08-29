<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../src/audio.php';

class AudioTest extends TestCase
{
    private $dir;
    private $collation;

    protected function setUp(): void
    {
        $this->collation = setlocale(LC_COLLATE, 0);
        $this->dir = sys_get_temp_dir().'/ouiedire-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        setlocale(LC_COLLATE, $this->collation);
        foreach (glob($this->dir.'/*') as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    }

    private function touchFiles(array $names)
    {
        foreach ($names as $name) {
            touch($this->dir.'/'.$name);
        }
    }

    /**
     * Pose les fichiers sous une collation non-C, et refuse de continuer si
     * cette collation ne diverge pas reellement de l'ordre des octets.
     *
     * Deux garde-fous, pas un : setlocale() peut accepter le nom de la locale
     * et collationner quand meme par octets — c'est le cas d'une base musl,
     * p.ex. php:7.4-alpine. Le test passerait alors sans rien prouver. On
     * verifie donc la premisse observable : scandir() ne rend pas deja l'ordre
     * des octets. Un echec ici, jamais un skip : c'est le silence qu'on traque.
     */
    private function exigeUneCollationQuiDiffereDesOctets(array $names)
    {
        $obtenue = setlocale(LC_COLLATE, 'fr_FR.UTF-8');

        $this->assertNotFalse(
            $obtenue,
            "La locale fr_FR.UTF-8 est absente de cette image : ce test ne peut pas "
            ."prouver ce qu'il affirme. Voir docker/php-test.Dockerfile."
        );

        $this->touchFiles($names);

        $brut = array_values(array_filter(scandir($this->dir), function ($n) {
            return '.' !== $n[0];
        }));
        $octets = $brut;
        sort($octets);

        // La tete, pas le tableau entier : findAudioFile() ne rend que $found[0].
        // Deux ordres peuvent diverger en queue en s'accordant en tete, et un
        // fixture pareil passerait le garde en ne prouvant rien.
        $this->assertNotSame(
            $octets[0],
            $brut[0],
            "Sous \"$obtenue\", scandir() rend deja le meme premier fichier que l'ordre "
            ."des octets : la collation ne les departage pas et le test ne prouverait "
            ."rien. Base musl, ou fixture mal choisi ?"
        );
    }

    public function testTrouveUnFichierAuNomLibre()
    {
        $this->touchFiles(['mix final.mp3']);

        $this->assertSame('mix final.mp3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testNeTrouveRienQuandLeDossierNaPasCeFormat()
    {
        $this->touchFiles(['mix.flac']);

        $this->assertNull(findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testDistingueLesFormats()
    {
        $this->touchFiles(['a.mp3', 'b.flac']);

        $this->assertSame('a.mp3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
        $this->assertSame('b.flac', findAudioFile($this->dir, 'flac', 'ouiedire_ailleurs-331_'));
    }

    public function testDossierInexistant()
    {
        $this->assertNull(findAudioFile($this->dir.'/absent', 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testLaConventionPasseDevant()
    {
        $this->touchFiles(['aaa.mp3', 'ouiedire_ailleurs-331_dj_titre.mp3']);

        $this->assertSame(
            'ouiedire_ailleurs-331_dj_titre.mp3',
            findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_')
        );
    }

    public function testAlphabetiqueDepartageDansChaqueGroupe()
    {
        $this->touchFiles(['zzz.mp3', 'aaa.mp3']);

        $this->assertSame('aaa.mp3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testUnFichierQuiMentionneLaConventionSansCommencerParElleNePassePasDevant()
    {
        $this->touchFiles(['aaa.mp3', 'copie_ouiedire_ailleurs-331_dj_titre.mp3']);

        $this->assertSame('aaa.mp3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testLaConventionDuneAutreEmissionNeGagnePas()
    {
        $this->touchFiles(['ouiedire_ailleurs-182_dj_titre.mp3', 'ouiedire_ailleurs-331_dj_titre.mp3']);

        $this->assertSame(
            'ouiedire_ailleurs-331_dj_titre.mp3',
            findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_')
        );
    }

    public function testLOrdreRetenuEstCeluiDesOctetsPasCeluiDeLaCollation()
    {
        // En fr_FR.UTF-8, scandir() rend a-b.mp3 en tete (strcoll ignore le tiret
        // au premier niveau) la ou sort() rend B.mp3 (l'octet 'B' precede 'a').
        $this->exigeUneCollationQuiDiffereDesOctets(['a-b.mp3', 'ab.mp3', 'B.mp3', 'a.mp3']);

        $this->assertSame('B.mp3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }

    public function testDeuxFichiersConformesSontDepartagesParLesOctets()
    {
        // Le scenario meme du change : un titre est corrige, le fichier au nom
        // canonique d'hier reste a cote du nouveau. Les deux portent le prefixe,
        // et c'est le tri du groupe conforme qui decide — pas celui des autres.
        $this->exigeUneCollationQuiDiffereDesOctets([
            'ouiedire_ailleurs-331_a-b.mp3',
            'ouiedire_ailleurs-331_B.mp3',
        ]);

        $this->assertSame(
            'ouiedire_ailleurs-331_B.mp3',
            findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_')
        );
    }

    public function testLExtensionEstReconnueQuelleQueSoitLaCasse()
    {
        // Le nom du fichier n'est pas une donnee : la casse de l'extension non plus.
        $this->touchFiles(['MIX.MP3']);

        $this->assertSame('MIX.MP3', findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_'));
    }
}
