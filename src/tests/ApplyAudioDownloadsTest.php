<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../src/audio.php';

/**
 * La fusion et la regle de publication, une fois sorties de bootstrap.php.
 *
 * Ce que ces tests tiennent n'est ni la decouverte (AudioTest) ni la couture
 * avec le dossier (AudioDownloadsTest), mais ce qui restait au point d'appel :
 * quelles cles la forme rendue porte, laquelle elle ne porte pas, ou chaque
 * slug atterrit, et dans quel sens la publication se degrade. Tant que ces six
 * lignes vivaient dans getShow(), sept mutations y survivaient — la suite
 * n'amorce pas bootstrap.php.
 */
class ApplyAudioDownloadsTest extends TestCase
{
    private $dir;
    private $urlAssets = 'https://exemple.test/assets/emission/ailleurs-331';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/ouiedire-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) as $nom) {
            if ('.' === $nom || '..' === $nom) {
                continue;
            }
            unlink($this->dir.'/'.$nom);
        }
        rmdir($this->dir);
    }

    private function ecritFichier($nom, $octets = 0)
    {
        file_put_contents($this->dir.'/'.$nom, str_repeat('x', $octets));
    }

    /**
     * Une emission telle que getShow() la tient au moment de l'appel : le
     * numero y est deja, les slugs arrivent a cote, deja translitteres.
     */
    private function emission(array $ecrasements = array())
    {
        return array_merge(array(
            'number' => '331',
            'type' => 'Ailleurs',
            'authors' => 'Rachitik Data',
            'title' => 'La Pompa Calor Vol 3',
            'isPublic' => true,
        ), $ecrasements);
    }

    private function slugs(array $ecrasements = array())
    {
        return array_merge(array(
            'type' => 'ailleurs',
            'authors' => 'rachitik-data',
            'title' => 'la-pompa-calor-vol-3',
        ), $ecrasements);
    }

    private function applique(array $show, array $slugs = null)
    {
        return applyAudioDownloads(
            $show,
            $this->dir,
            $this->urlAssets,
            null === $slugs ? $this->slugs() : $slugs
        );
    }

    public function testLaFormeRendueNePortePasHasAudio()
    {
        // hasAudio est une reponse a l'appelant, pas une cle d'emission. La
        // laisser fuir ajouterait a getShow() une cle qu'elle ne posait pas.
        $this->ecritFichier('mix.mp3');

        $this->assertArrayNotHasKey('hasAudio', $this->applique($this->emission()));
    }

    public function testLesCinqClesDeTelechargementSontFusionneesSansPerdreLesAutres()
    {
        $this->ecritFichier('mix.mp3', 1048576);

        $show = $this->applique($this->emission());

        $this->assertSame($this->urlAssets.'/mix.mp3', $show['urlDownloadMp3']);
        $this->assertNull($show['urlDownloadFlac']);
        $this->assertSame('1 Mo', $show['sizeDownloadMp3']);
        $this->assertNull($show['sizeDownloadFlac']);
        $this->assertSame(
            'ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3',
            $show['canonicalDownloadName']
        );
        // Ce que l'emission portait deja reste : la fonction fusionne, elle ne
        // remplace pas.
        $this->assertSame('Rachitik Data', $show['authors']);
        $this->assertSame('331', $show['number']);
    }

    public function testLesValeursCalculeesGagnentSurCellesDuManifeste()
    {
        // Le sens de la fusion decide aussi de la PRECEDENCE, et c'est la le
        // vrai invariant : un manifeste est une donnee externe non validee, et
        // rien ne lui interdit de porter une de ces cinq cles. Ce qui a ete
        // mesure sur le disque doit gagner sur ce qu'un fichier declare.
        // Mutation qui survivait avant ce test : conserver l'ordre mais rendre
        // au manifeste la priorite sur le calcul.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission(array(
            'urlDownloadMp3' => 'https://menteur.test/faux.mp3',
            'canonicalDownloadName' => 'mensonge',
        )));

        $this->assertSame($this->urlAssets.'/mix.mp3', $show['urlDownloadMp3']);
        $this->assertSame(
            'ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3',
            $show['canonicalDownloadName']
        );
    }

    public function testLesClesAjouteesViennentApresCellesDeLEmission()
    {
        // Detecteur de changement sur le SENS de la fusion, et rien de plus :
        // array_merge($audio, $show) rend les memes cles et les memes valeurs
        // dans un autre ordre, et aucune assertion de valeur ne l'attrape.
        //
        // Une premiere redaction pretendait que cet ordre etait observable dans
        // les gabarits et le flux. C'est FAUX, verifie consommateur par
        // consommateur : les gabarits lisent show.foo par son nom, la route RSS
        // construit chaque champ, oEmbed batit son propre tableau, getShows() ne
        // lit que isPublic. Les 3 670 lignes de l'instantane sont un artefact de
        // var_export(), pas une sortie. Ce test est donc justifie par la
        // mutation, pas par un effet visible — et le dire evite de le prendre
        // pour ce qu'il n'est pas. L'invariant qui compte, lui, est teste juste
        // en dessous.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission());

        $this->assertSame(
            array(
                'number', 'type', 'authors', 'title', 'isPublic',
                'sizeDownloadMp3', 'sizeDownloadFlac',
                'urlDownloadMp3', 'urlDownloadFlac', 'canonicalDownloadName',
            ),
            array_keys($show)
        );
    }

    public function testUneEmissionPublieeSansAudioEstDepubliee()
    {
        // Le sens qui motive la regle : « quelle que soit la valeur de son
        // champ isPublic » (specs/emission-contenu, « Aucune publication sans
        // audio »). Le dossier ne porte qu'une pochette.
        $this->ecritFichier('cover.jpg');

        $show = $this->applique($this->emission(array('isPublic' => true)));

        $this->assertFalse($show['isPublic']);
    }

    public function testUneEmissionNonPublieeAvecAudioResteNonPubliee()
    {
        // L'autre sens, et la reponse a l'objection : la fonction DEGRADE, elle
        // ne promeut jamais. isPublic vient du manifeste, et un audio present ne
        // publie pas ce que la redaction a laisse hors ligne.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission(array('isPublic' => false)));

        $this->assertFalse($show['isPublic']);
    }

    public function testUneEmissionPublieeAvecAudioRestePubliee()
    {
        // Sans ce test, une garde inconditionnelle — isPublic mis a false a tous
        // les coups — laisserait les deux tests ci-dessus au vert.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission(array('isPublic' => true)));

        $this->assertTrue($show['isPublic']);
    }

    public function testLeDossierEtLUrlNeSontPasPermutables()
    {
        // Deux chaines de meme type a la file : les echanger rendait le meme
        // nombre d'arguments et passait la suite. Permutes, le balayage porte
        // sur une URL — aucun fichier — et l'emission se depublie.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission());

        $this->assertSame($this->urlAssets.'/mix.mp3', $show['urlDownloadMp3']);
        $this->assertTrue($show['isPublic']);
    }

    public function testChaqueSlugNommeAtterritASaPlace()
    {
        // Les trois parts sont distinctes et reconnaissables : une cle lue a la
        // place d'une autre deplace visiblement le nom canonique. C'est ce que
        // les cles nommees rendent non representable a l'appel, et ce test le
        // tient du cote de la fonction.
        $this->ecritFichier('mix.mp3');

        $show = $this->applique($this->emission(), $this->slugs(array(
            'type' => 'bagage',
            'authors' => 'aaa',
            'title' => 'zzz',
        )));

        $this->assertSame('ouiedire_bagage-331_aaa_zzz', $show['canonicalDownloadName']);
    }

    public function testLeTypeSlugSertAussiAuPrefixeDeConvention()
    {
        // Le slug de type ne sert pas qu'au nom canonique : il construit le
        // prefixe qui fait passer la convention devant. Le lire ailleurs — ou
        // ne le passer qu'au nom — laisserait le nom libre gagner.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-331_dj_titre.mp3');

        $show = $this->applique($this->emission());

        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-331_dj_titre.mp3',
            $show['urlDownloadMp3']
        );
    }

    public function testLeNumeroSeLitDansLEmissionEtNEstPasSlugifie()
    {
        // Le numero ne vient pas des slugs : il est deja dans $show, tel quel du
        // segment d'URL que la route ne contraint pas. Le slugifier ici serait
        // un changement de comportement, et le prendre ailleurs qu'a
        // $show['number'] casserait le prefixe de convention.
        // « 17 bis » n'est pas un temoin choisi au hasard : slugify() en ferait
        // « 17-bis », donc les deux assertions ci-dessous separent reellement le
        // numero tel quel de sa version slugifiee.
        $this->ecritFichier('ouiedire_ailleurs-17 bis_dj_titre.mp3');
        $this->ecritFichier('aaa.mp3');

        $show = $this->applique($this->emission(array('number' => '17 bis')));

        $this->assertSame(
            'ouiedire_ailleurs-17 bis_rachitik-data_la-pompa-calor-vol-3',
            $show['canonicalDownloadName']
        );
        // Et le prefixe construit sur ce meme numero fait bien passer le fichier
        // conforme devant le nom libre.
        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-17%20bis_dj_titre.mp3',
            $show['urlDownloadMp3']
        );
    }
}
