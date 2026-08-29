<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../src/audio.php';

/**
 * La couture entre getShow() et le balayage du dossier.
 *
 * Ce que ces tests tiennent n'est pas findAudioFile() — AudioTest s'en charge —
 * mais le branchement : l'ordre des arguments, le format retenu, l'encodage de
 * l'URL, l'unite de la taille, et la regle « aucune publication sans audio ».
 * Tant que ce bloc vivait dans bootstrap.php, que la suite n'amorce pas, aucune
 * de ces mutations ne faisait echouer quoi que ce soit.
 */
class AudioDownloadsTest extends TestCase
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
        // scandir() plutot que glob() : un lien symbolique casse est un fixture
        // de ce fichier, et glob() ne le rend pas sur toutes les plateformes.
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

    private function decouvre($number = '331', $authorsSlug = 'dj', $titleSlug = 'titre')
    {
        return audioDownloads($this->dir, $this->urlAssets, 'ailleurs', $number, $authorsSlug, $titleSlug);
    }

    public function testLePrefixeSeTermineParSonSeparateur()
    {
        // Sans le « _ » final, le prefixe de l'emission 17 designerait aussi les
        // fichiers de la 17bis, qui existe. Le fichier au nom libre doit donc
        // gagner : aucun des deux n'est conforme a la convention de la 17.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-17bis_autre_titre.mp3');

        $audio = $this->decouvre('17');

        $this->assertSame($this->urlAssets.'/aaa.mp3', $audio['urlDownloadMp3']);
    }

    public function testLesArgumentsDuNomCanoniqueNeSontPasPermutables()
    {
        // Auteurs et titre occupent deux places distinctes du nom : les
        // permuter au point d'appel rendait le meme nombre d'arguments et
        // passait la suite.
        $audio = $this->decouvre('331', 'rachitik-data', 'la-pompa-calor-vol-3');

        $this->assertSame(
            'ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3',
            $audio['canonicalDownloadName']
        );
    }

    public function testLeNumeroArriveNonSlugifie()
    {
        // Contrat ecrit au docblock de canonicalDownloadName() : la fonction
        // assemble et met en minuscules, elle ne slugifie pas. Le numero vient
        // tel quel du segment d'URL, que la route ne contraint pas. Un
        // slugify() ajoute a la couture doit faire echouer ce test.
        $audio = $this->decouvre('17 BIS');

        // Le numero traverse TEL QUEL — « 17 BIS » — espace et casse compris :
        // ni slugifie, ni rempli. Seul strtolower() le touche.
        $this->assertSame('ouiedire_ailleurs-17 bis_dj_titre', $audio['canonicalDownloadName']);
    }

    public function testLUrlEncodeLeNomRetenu()
    {
        // Un nom libre, avec espace, accent et esperluette. Sans
        // rawurlencode(), l'esperluette brute couperait la flashvars du lecteur
        // (src/views/emission.html.twig:79) et l'espace casserait l'URL.
        // Les deux formats, pas seulement le mp3 : le flac emprunte le meme
        // chemin, et le laisser hors du test y laissait rawurlencode() libre.
        $this->ecritFichier('mix été & co vol 3.mp3');
        $this->ecritFichier('mix été & co vol 3.flac');

        $audio = $this->decouvre();

        $this->assertSame(
            $this->urlAssets.'/mix%20%C3%A9t%C3%A9%20%26%20co%20vol%203.mp3',
            $audio['urlDownloadMp3']
        );
        $this->assertSame(
            $this->urlAssets.'/mix%20%C3%A9t%C3%A9%20%26%20co%20vol%203.flac',
            $audio['urlDownloadFlac']
        );
    }

    public function testLesDeuxFormatsNeSontPasPermutes()
    {
        $this->ecritFichier('a.mp3', 1048576);
        $this->ecritFichier('b.flac', 2097152);

        $audio = $this->decouvre();

        $this->assertSame($this->urlAssets.'/a.mp3', $audio['urlDownloadMp3']);
        $this->assertSame($this->urlAssets.'/b.flac', $audio['urlDownloadFlac']);
        $this->assertSame('1 Mo', $audio['sizeDownloadMp3']);
        $this->assertSame('2 Mo', $audio['sizeDownloadFlac']);
    }

    public function testLaConventionPasseDevantLeNomLibre()
    {
        // Le prefixe est construit par la couture, a partir du type et du
        // numero. Le vider rendrait tout le dossier « conforme » et le tri
        // alphabetique donnerait aaa.mp3.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-331_dj_titre.mp3');

        $audio = $this->decouvre();

        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-331_dj_titre.mp3',
            $audio['urlDownloadMp3']
        );
    }

    public function testLaConventionEstReconnueSurLeNumeroRempli()
    {
        // Le defaut mesure : 136 des 367 emissions portent un audio nomme au
        // numero rempli (ouiedire_ailleurs-001_…) alors que le prefixe etait
        // bati sur le numero brut, tel que le segment d'URL le donne. La regle
        // « la convention passe devant » etait inerte sur plus du tiers du fonds.
        // « aaa.mp3 » et non « zzz.mp3 » : sous zzz, le repli alphabetique
        // rendait deja le bon fichier et le test passait sans rien prouver.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-001_dj_titre.mp3');

        $audio = $this->decouvre('1');

        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-001_dj_titre.mp3',
            $audio['urlDownloadMp3']
        );
    }

    public function testLaConventionResteReconnueSurLeNumeroBrut()
    {
        // L'autre sens, et il n'est pas decoratif : normaliser le numero au lieu
        // d'accepter les deux formes echangerait un angle mort contre l'autre.
        // Les dossiers et les couvertures de l'archive portent la forme brute.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-1_dj_titre.mp3');

        $audio = $this->decouvre('1');

        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-1_dj_titre.mp3',
            $audio['urlDownloadMp3']
        );
    }

    public function testLeNomCanoniquePorteLeNumeroBrut()
    {
        // Le numero reste BRUT, comme le dossier, les couvertures, le segment
        // d'URL et les fichiers audio de production. Le numero rempli n'existe
        // qu'a l'affichage de la page.
        //
        // Une redaction precedente attendait « 001 » ici, au motif que les 367
        // fichiers stockes portaient cette forme. C'etait mesure sur les
        // fixtures de bin/dev-audio-fixtures, qui les nomme APRES le
        // remplissage — pas sur l'archive, dont l'ancien code exigeait la forme
        // brute et qui etait servie.
        $audio = $this->decouvre('1');

        $this->assertSame('ouiedire_ailleurs-1_dj_titre', $audio['canonicalDownloadName']);
    }

    public function testLaFormeRemplieReconnaitAussiUnNumeroNonNumerique()
    {
        // Le prefixe rempli doit reconnaitre « 017bis » ; le nom canonique, lui,
        // reste sur la forme brute du numero. Decouverte et etiquette ne suivent
        // pas la meme regle, et ce test tient les deux a la fois.
        $this->ecritFichier('aaa.mp3');
        $this->ecritFichier('ouiedire_ailleurs-017bis_dj_titre.mp3');

        $audio = $this->decouvre('17bis');

        $this->assertSame(
            $this->urlAssets.'/ouiedire_ailleurs-017bis_dj_titre.mp3',
            $audio['urlDownloadMp3']
        );
        $this->assertSame('ouiedire_ailleurs-17bis_dj_titre', $audio['canonicalDownloadName']);
    }

    public function testLaTailleEstEnMebioctetsArrondieAuCentieme()
    {
        // 1234567 octets font 1,177... Mio : deux decimales sont necessaires
        // pour distinguer l'arrondi demande de l'arrondi au dixieme. Une taille
        // ronde — 1,5 Mio — rendait la meme chaine dans les deux cas, et le
        // test portait un nom qu'il ne tenait pas.
        $this->ecritFichier('mix.mp3', 1234567);

        $audio = $this->decouvre();

        $this->assertSame('1.18 Mo', $audio['sizeDownloadMp3']);
    }

    public function testSansAucunAudioRienNEstPublie()
    {
        $this->ecritFichier('cover.jpg');

        $audio = $this->decouvre();

        $this->assertFalse($audio['hasAudio']);
        $this->assertNull($audio['urlDownloadMp3']);
        $this->assertNull($audio['urlDownloadFlac']);
        $this->assertNull($audio['sizeDownloadMp3']);
        $this->assertNull($audio['sizeDownloadFlac']);
    }

    public function testUnMp3SeulSuffitAPublier()
    {
        // La regle est « aucune publication sans audio », pas « publication
        // seulement avec les deux formats ». La plupart des emissions n'ont
        // que le MP3.
        $this->ecritFichier('mix.mp3');

        $this->assertTrue($this->decouvre()['hasAudio']);
    }

    public function testUnFlacSeulSuffitAPublier()
    {
        $this->ecritFichier('mix.flac');

        $this->assertTrue($this->decouvre()['hasAudio']);
    }

    public function testUnLienCasseAbandonneLeNom()
    {
        // Un stat qui echoue dit que le fichier n'est pas la. Sans cet abandon,
        // l'emission se publiait avec « 0 Mo » et un lien mort.
        symlink('/nexiste/pas.mp3', $this->dir.'/mix.mp3');

        $audio = $this->decouvre();

        $this->assertNull($audio['urlDownloadMp3']);
        $this->assertNull($audio['sizeDownloadMp3']);
        $this->assertFalse($audio['hasAudio']);
    }
}
