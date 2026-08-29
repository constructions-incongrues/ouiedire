# Découverte du fichier audio par balayage — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `getShow()` cesse de calculer le nom du fichier audio d'une émission et le découvre en balayant son dossier, de sorte que corriger un titre ne dépublie plus l'émission.

**Architecture:** La découverte, la couture et la règle de publication sont extraites dans `src/src/audio.php` — quatre fonctions libres sans dépendance à Silex ni à `slugify()` : les slugs leur arrivent déjà translittérés, sous des clés nommées. `bootstrap.php` n'en appelle plus qu'une. Cette extraction n'introduit ni port ni adaptateur : elle rend la logique testable sans monter l'application, ce que `sdr-002` (`monolithic`) n'interdit pas.

**Tech Stack:** PHP 7.4, PHPUnit 9.6, pcov pour la couverture, Twig 1.x, Docker (`php:7.4-cli`).

---

## Task 1: Poser la suite de tests

**Files:**
- Modify: `src/composer.json`
- Create: `docker/php-test.Dockerfile`
- Create: `src/tests/SmokeTest.php`
- Modify: `src/phpunit.xml` (création)
- Modify: `CLAUDE.local.md`

- [ ] **Step 1: Ajouter PHPUnit en dépendance de développement**

Dans `src/composer.json`, ajouter après le bloc `require` :

```json
    "require-dev": {
        "phpunit/phpunit": "^9.6"
    },
    "autoload-dev": {
        "psr-4": { "Ouiedire\\Tests\\": "tests/" }
    }
```

- [ ] **Step 2: Installer**

```bash
docker run --rm -v .:/app -w /app/src php:7.4-cli \
  sh -c "apt-get update -qq && apt-get install -y -qq unzip && \
         curl -sS https://getcomposer.org/installer | php -- \
           --install-dir=/usr/local/bin --filename=composer --version=2.8.12 && \
         composer install --no-interaction"
```

Attendu : `phpunit/phpunit (9.6.x)` dans la sortie, `src/vendor/bin/phpunit` présent.

Deux détails sans lesquels la commande échoue, découverts à l'exécution :
`php:7.4-cli` ne fournit ni `unzip` ni `git`, et Composer à partir de 2.9 refuse
par défaut les paquets visés par un avis de sécurité — ce dépôt en compte 23,
suivis dans le change `dependances-vulnerables`.

- [ ] **Step 3: Créer l'image de test avec pilote de couverture**

`docker/php-test.Dockerfile` :

```dockerfile
FROM php:7.4-cli
RUN pecl install pcov && docker-php-ext-enable pcov
# fr_FR.UTF-8 sert a un seul test : scandir() trie avec strcoll() (sensible a la
# collation) la ou sort() compare des octets. Sans une locale non-C dans l'image,
# ce test ne pourrait rien prouver. Voir AudioTest et src/src/audio.php.
RUN apt-get update \
    && apt-get install -y --no-install-recommends locales \
    && localedef -i fr_FR -f UTF-8 fr_FR.UTF-8 \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app/src
```

La locale est ajoutee ici plutot que dans la Task 2 parce que l'image est posee
ici. Elle n'a d'utilite qu'a partir de la Task 2.

```bash
docker build -t ouiedire-test -f docker/php-test.Dockerfile .
```

Attendu : `Successfully tagged ouiedire-test:latest`.

- [ ] **Step 4: Configurer PHPUnit**

`src/phpunit.xml` :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="ouiedire">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 5: Écrire un test qui valide vraiment la chaîne**

`src/tests/SmokeTest.php` :

```php
<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testLePiloteDeCouvertureEstCharge()
    {
        $this->assertTrue(
            extension_loaded('pcov'),
            "L'extension pcov est absente : la couverture ne serait pas mesurée."
        );
    }

    public function testLAutoloaderDeDevResoutLeNamespaceDeTest()
    {
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require __DIR__.'/../vendor/autoload.php';

        $this->assertNotFalse(
            $loader->findFile('Ouiedire\\Tests\\SmokeTest'),
            "Le mapping PSR-4 autoload-dev ne résout pas le namespace de test."
        );
    }
}
```

Un `assertTrue(true)` ne prouverait rien. Ces deux tests vérifient les deux
choses dont le reste de la suite dépend : le pilote de couverture est chargé,
et l'autoloader `autoload-dev` résout bien le namespace de test. Le second
passe par `ClassLoader::findFile()`, l'API publique de Composer — un
`class_exists(self::class)` serait une tautologie, la classe étant déjà chargée.

- [ ] **Step 6: Lancer**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
```

Attendu : `OK (2 tests, 2 assertions)`.

La suite exige **pcov** : sur une image `php:7.4-cli` nue, `SmokeTest` échoue
volontairement. Le runner documenté est l'image `ouiedire-test`.

- [ ] **Step 7: Déclarer le runner dans `CLAUDE.local.md`**

Ajouter à `CLAUDE.local.md` — **pas dans `CLAUDE.md`**, que la mise à jour de la méthode réécrit :

```markdown
## Testing (ce dépôt)

Runner : PHPUnit 9.6
Image : `docker build -t ouiedire-test -f docker/php-test.Dockerfile .`
Commande : `docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit`
Couverture : `docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --coverage-text`
Sortie attendue : `OK (N tests, M assertions)`
```

- [ ] **Step 8: Commit**

```bash
git add src/composer.json src/composer.lock src/phpunit.xml src/tests/SmokeTest.php docker/php-test.Dockerfile CLAUDE.local.md
git commit -m "test: pose PHPUnit et le pilote de couverture"
```

---

## Task 2: Découverte du fichier audio et ordre de sélection

**Files:**
- Create: `src/src/audio.php`
- Create: `src/tests/AudioTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

`src/tests/AudioTest.php` :

```php
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
```

`setUp()` relève `setlocale(LC_COLLATE, 0)` et `tearDown()` le repose, pour que les
tests de collation ne fuitent pas leur état dans les autres.

Les quatre premiers tests pilotent la découverte ; les suivants pilotent la
branche « convention d'abord » et les deux tris. Sans eux dans cette tâche,
l'implémentation de l'étape 3 contiendrait du code qu'aucun test rouge n'aurait
exigé — ce que `sdr-004` interdit sans exception.

Cinq d'entre eux viennent de tests de mutation passés après coup, la couverture
de ligne ne les ayant pas réclamés : `audio.php` affichait 14/14 alors que cinq
mutations laissaient la suite verte — supprimer `sort($others)`, supprimer
`sort($conventional)`, le passer en `SORT_LOCALE_STRING`, affaiblir `=== 0` en
`!== false`, et retirer le `strtolower()` de l'extension. La couverture de ligne
compte les lignes exécutées, pas les lignes dont la suppression change un
résultat ; elle ne pouvait pas voir ça.

Deux points de méthode que ces tests portent, et qu'il ne faut pas défaire :

- Le test « deux fichiers conformes » est le scénario même du change — un titre
  corrigé laisse l'ancien fichier canonique à côté du nouveau. Tant qu'aucun test
  ne mettait **deux** fichiers préfixés dans un dossier, `sort($conventional)`
  n'était tenu par rien.
- Les tests de collation passent par `exigeUneCollationQuiDiffereDesOctets()`, qui
  vérifie deux choses avant d'affirmer quoi que ce soit : que `setlocale()` a
  réussi, et que `scandir()` ne rend pas **déjà** le même premier fichier que
  l'ordre des octets. La comparaison porte sur les têtes, pas sur les tableaux
  entiers : `findAudioFile()` ne rend que `$found[0]`, et deux ordres peuvent
  diverger en queue en s'accordant en tête — un fixture pareil passerait le
  garde-fou sans rien prouver. Le premier contrôle seul ne suffit pas : sur une base musl (`php:7.4-alpine`), `setlocale()`
  accepte le nom de la locale et collationne par octets — le test passerait sans
  rien prouver. Un échec bruyant, jamais un `markTestSkipped` : un skip est un
  vert silencieux, et repérer le silence est précisément la raison d'être de ces
  tests.

- [ ] **Step 2: Vérifier que ça échoue pour la bonne raison**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter AudioTest
```

Attendu : `Error: Failed to open stream: No such file or directory` sur `src/audio.php`.

- [ ] **Step 3: Écrire l'implémentation minimale**

`src/src/audio.php` :

```php
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
```

- [ ] **Step 4: Vérifier que ça passe**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter AudioTest
```

Attendu : `OK (11 tests, 16 assertions)`.

- [ ] **Step 5: Commit**

```bash
git add src/src/audio.php src/tests/AudioTest.php docker/php-test.Dockerfile
git commit -m "feat: decouvre le fichier audio par balayage du dossier"
```

---

## Task 3: Contrôle de couverture par fichier

`--coverage-text` ne sait pas rendre ce que `sdr-004` demande. Mesuré : il
n'affiche que `Classes`, `Methods` et un total de lignes. `audio.php` ne portera
que des fonctions libres — jamais des méthodes — donc il n'apparaîtra à aucun
niveau de couverture. Et le total est capturé par les 507 instructions non
couvertes de `bootstrap.php` : un `audio.php` parfaitement testé porterait le
global à ~9 %, jamais à 90 %.

Clover, lui, donne le détail par fichier. Ce contrôle existe donc pour que la
Task 3 ait quelque chose à appeler — et il ferme au passage le trou du filtre
silencieux : si le filtre casse, l'entrée du fichier est absente et le contrôle
échoue, au lieu de passer sur un chiffre manquant.

**Files:**
- Create: `bin/coverage-check.php`
- Create: `src/tests/CoverageCheckTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

`src/tests/CoverageCheckTest.php` :

```php
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
```

- [ ] **Step 2: Vérifier que ça échoue**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter CoverageCheck
```

Attendu : `Failed to open stream` sur `bin/coverage-check.php`.

- [ ] **Step 3: Implémenter**

`bin/coverage-check.php` :

```php
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
```

- [ ] **Step 4: Vérifier que ça passe**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
```

Attendu : tous les tests au vert, dont les 26 de `CoverageCheckTest`.

- [ ] **Step 5: Vérifier le contrôle de bout en bout sur le vrai rapport**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test \
  sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml 2>/dev/null && \
         php /app/bin/coverage-check.php /app/clover.xml audio.php 90"
echo "code de sortie : $?"
```

Attendu : `/app/src/src/audio.php : 100.00 % (N/N), seuil 90.00 %` et code de
sortie `0`. Le nom affiché est celui du fichier trouvé dans le rapport, pas la
queue de chemin demandée.

- [ ] **Step 6: Déclarer le contrôle dans `CLAUDE.local.md`**

Ajouter à la section `## Testing (ce dépôt)` :

```markdown
Seuil par fichier : `docker run --rm -v .:/app -w /app/src ouiedire-test sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml 2>/dev/null && php /app/bin/coverage-check.php /app/clover.xml <fichier> 90"`

`--coverage-text` ne sert qu'à l'œil : il ne rend ni les fonctions libres ni le
détail par fichier, et son total est dominé par `bootstrap.php`. C'est
`coverage-check.php` qui fait foi pour le seuil de `sdr-004`.
```

- [ ] **Step 7: Commit**

```bash
git add bin/coverage-check.php src/tests/CoverageCheckTest.php CLAUDE.local.md
git commit -m "test: controle de couverture par fichier via Clover"
```

---

## Task 4: Nom canonique de téléchargement

**Files:**
- Modify: `src/src/audio.php`
- Modify: `src/tests/AudioTest.php`

- [x] **Step 1: Écrire les tests qui échouent**

Ajouter dans `src/tests/AudioTest.php` :

```php
    public function testLeNomCanoniqueNeDependPasDuFichierStocke()
    {
        $this->assertSame(
            'ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3',
            canonicalDownloadName('ailleurs', '331', 'rachitik-data', 'la-pompa-chalor-vol-3')
        );
    }

    public function testLeNomCanoniqueEstEnMinuscules()
    {
        // Le contrat retenu : la fonction assemble et met en minuscules, elle ne
        // slugifie pas. Le strtolower() n'est pas decoratif — getShow() slugifie
        // les trois slugs mais pas $number, qui vient tel quel du segment d'URL.
        $this->assertSame(
            'ouiedire_ailleurs-331_dj_titre',
            canonicalDownloadName('Ailleurs', '331', 'DJ', 'Titre')
        );
    }

    public function testLeNomCanoniqueMetLeNumeroEnMinuscules()
    {
        // Le seul argument que getShow() ne slugifie pas, donc le seul dont la
        // mise en minuscules soit observable en production. Sans ce test, ne
        // baisser que les trois autres passe la suite : le strtolower() serait
        // argumente au docblock et tenu par rien.
        // « 17BIS » n'est pas invente : le dossier ailleurs-17bis existe, et la
        // route /emission/{type}-{id} ne contraint pas {id}.
        $this->assertSame(
            'ouiedire_ailleurs-17bis_dj_titre',
            canonicalDownloadName('ailleurs', '17BIS', 'dj', 'titre')
        );
    }

    public function testLeNomCanoniqueLaisseLesAccentsIntacts()
    {
        // strtolower() compare des octets : il ne touche pas a l'UTF-8. Ce depot
        // s'est deja fait mordre par la (84 artistes perdus au change precedent),
        // donc le comportement est constate ici plutot que suppose. Ce n'est pas
        // une lacune : la transliteration est le travail de slugify(), que
        // l'appelant applique en amont et qui vit dans bootstrap.php.
        $this->assertSame(
            'ouiedire_ailleurs-331_dj_Été',
            canonicalDownloadName('Ailleurs', '331', 'DJ', 'Été')
        );
    }
```

**Le contrat, tranché.** Le squelette de cette tâche se contredisait : son
docblock annonçait « les arguments arrivent déjà slugifiés », et son second test
passait `'Ailleurs'`, `'DJ'`, `'Titre'` en attendant une mise en minuscules. Les
deux ne peuvent pas être vrais — si tout arrive slugifié, `strtolower()` est mort
et le test le certifie sans rien exiger.

Ce qui est retenu : **la fonction assemble et met en minuscules ; elle ne
slugifie pas.** Trois faits l'imposent, tous vérifiés dans le code :

1. `slugify()` vit dans `bootstrap.php`, et `audio.php` n'en dépend pas — c'est
   `bootstrap.php` qui requiert `audio.php` (Task 5), jamais l'inverse. La
   fonction ne peut donc pas slugifier, et n'a pas à le faire.
2. `getShow()` slugifie bien `type`, `authors` et `title`, mais **pas**
   `$show['number']`, qui vaut `explode('-', $id)[1]` — le second segment de
   l'URL, tel quel. Le `strtolower()` porte donc sur un argument réellement non
   normalisé : il n'est pas décoratif.
3. C'est exactement ce que faisait `slugDownload`, qui enveloppait le même
   `sprintf()` complet dans `strtolower()`. Retirer l'appel serait un changement
   de comportement que ce change n'a pas demandé.

Le troisième test tient la tension 1 par sa mesure : `$number` est le seul
argument que `getShow()` ne slugifie pas, donc le seul dont la mise en minuscules
soit observable en production. Sans lui, ne baisser que les trois autres passe la
suite (M11 dans la table plus bas), et le `strtolower()` reste argumenté au
docblock sans être tenu par quoi que ce soit. `17BIS` n'est pas inventé : le
dossier `ailleurs-17bis` existe, et la route `/emission/{type}-{id}` ne pose
aucune contrainte sur `{id}`.

Le quatrième test vient de la seconde tension : `strtolower()`
compare des **octets**, pas de l'UTF-8. Ce dépôt s'est déjà fait mordre par là au
change précédent (84 artistes perdus). Le comportement est donc **constaté**
plutôt que supposé : une entrée accentuée ressort avec ses accents et leur casse
intacts — `'Été'` reste `'Été'`, le `É` de tête compris. Ce n'est pas une lacune
sous le contrat retenu : la translittération est le travail de `slugify()`, que
l'appelant applique en amont. C'est une lacune **écrite**, donc opposable, et qui
échouera bruyamment si quelqu'un décide un jour l'inverse.

- [x] **Step 2: Vérifier que ça échoue**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter canonique
```

Mesuré : `Error: Call to undefined function Ouiedire\Tests\canonicalDownloadName()`
sur les trois tests. Le nom est résolu dans le namespace du test — d'où le
préfixe `Ouiedire\Tests\`, que PHP ajoute avant de retomber sur l'espace global.

- [x] **Step 3: Implémenter**

Ajouter à la fin de `src/src/audio.php` :

```php
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
```

- [x] **Step 4: Vérifier**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
```

Mesuré : `OK (43 tests, 62 assertions)` — 2 `SmokeTest` + 11 `AudioTest`
(Task 2) + 26 `CoverageCheckTest` (Task 3, décompte revérifié : inchangé) + les 4
ajoutés au Step 1. Le plan annonçait `41 / 60`, calculé sur 2 tests ; les deux de
plus viennent des deux tensions, une chacune.

Seuil de couverture sur le fichier touché :

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml 2>/dev/null && php /app/bin/coverage-check.php /app/clover.xml audio.php 90"
```

Mesuré : `/app/src/src/audio.php : 100.00 % (15/15), seuil 90.00 %`.

**Table de mutation.** Chaque mutation a été **appliquée au fichier et la suite
relancée** — la couverture de ligne ne voit pas ce genre de trou, et deux
« mutants équivalents » raisonnés de tête se sont déjà révélés faux dans ce
change. Onze mutations appliquées, onze tuées — l'espace n'est pas épuisé pour
autant, et M11 ci-dessous a d'abord survécu à la première table de dix.

| # | Mutation appliquée | Tuée par |
| --- | --- | --- |
| M1 | `strtolower()` retiré | `…EstEnMinuscules`, `…LaisseLesAccentsIntacts` |
| M2 | `$typeSlug` ignoré (`''`) | les trois |
| M3 | `$number` ignoré (`''`) | les trois |
| M4 | `$authorsSlug` ignoré (`''`) | les trois |
| M5 | `$titleSlug` ignoré (`''`) | les trois |
| M6 | `$typeSlug` et `$number` permutés | les trois |
| M7 | `$authorsSlug` et `$titleSlug` permutés | les trois |
| M8 | séparateur type/numéro : `-` devient `_` | les trois |
| M9 | préfixe `ouiedire_` devient `ouiedire-` | les trois |
| M10 | préfixe `ouiedire_` devient `radio_` | les trois |
| M11 | `strtolower()` sur les trois slugs, **pas** sur `$number` | `…MetLeNumeroEnMinuscules` |

**M11 est la ligne qui compte, et la première table la manquait.** M1 ne
sépare pas les deux contrats : il n'est tué que par des entrées dont le docblock
dit qu'elles arrivent *déjà* slugifiées, donc déjà en minuscules — les seuls cas
que la production ne produit pas. Le seul argument dont la mise en minuscules
soit observable est `$number`, que `getShow()` ne slugifie pas ; M11 le montre en
n'épargnant que lui. Sans le test qui le tue, le `strtolower()` était argumenté
au docblock et tenu par rien.

Ce n'est pas hypothétique : le dossier `ailleurs-17bis` existe, et la route
`/emission/{type}-{id}` ne pose aucun `assert()` sur `{id}`.

- [x] **Step 5: Commit**

```bash
git add src/src/audio.php src/tests/AudioTest.php
git commit -m "feat: nom canonique de telechargement independant du fichier stocke"
```

---

## Task 5: Brancher dans getShow()

**Files:**
- Modify: `src/src/bootstrap.php` — le `require` de tête, et le bloc audio de
  `getShow()`. Les numéros de ligne que ce plan portait (`:3`, `:225-250`) ont
  dérivé au fil des tâches ; les ancres ci-dessous sont des **chaînes**, dont
  l'unicité a été vérifiée avant chaque édition.

- [x] **Step 1: Charger le nouveau fichier**

Juste après `require_once __DIR__.'/../vendor/autoload.php';` dans
`src/src/bootstrap.php`, ajouter :

```php
require_once __DIR__.'/audio.php';
```

- [x] **Step 2: Remplacer le bloc audio**

Remplacer intégralement, dans `src/src/bootstrap.php`, depuis `// Guess show audio properties (MP3 and FLAC)` jusqu'à la ligne fermante du second `if ($fileFlac->isReadable()) { ... }`.

**Le bloc ci-dessous est régénéré depuis le fichier final, après le Step 9** — ni
le calcul ni la fusion ne vivent plus ici, ils vivent dans `audio.php` :

```php
    // Guess show audio properties (MP3 and FLAC).
    // Le nom du fichier n'est pas une donnee : on balaye le dossier, la
    // convention historique d'abord. Voir le change audio-sans-convention.
    // Tout le calcul vit dans audio.php, la fusion et la regle de publication
    // comprises : ici, la suite n'entre pas et la jauge ne mesure rien. Les
    // slugs partent sous des cles NOMMEES — PHP 7.4 n'a pas d'arguments nommes,
    // et trois chaines de meme type a la file se permutent sans que rien ne le
    // voie. Le numero se lit dans $show, non slugifie.
    // Voir AudioDownloadsTest et ApplyAudioDownloadsTest.
    $show = applyAudioDownloads($show, $pathPublicEmission, $urlAssets, array(
        'type' => slugify($show['type']),
        'authors' => slugify($show['authors']),
        'title' => slugify($show['title']),
    ));

    $show['slugDownload'] = strtolower(sprintf('%s/ouiedire_%s-%s_%s_%s', $urlAssets, slugify($show['type']), $show['number'], slugify($show['authors']), slugify($show['title'])));
```

Le retrait de `hasAudio` n'est pas une coquetterie : c'est une **réponse
interne**, pas une clé d'émission. La laisser fuir ajouterait à `getShow()` une
clé qu'elle ne posait pas, et la contrainte de cette tâche est que le jeu de clés
reste identique — vérifié, pas supposé (Step 7). Depuis le Step 9 ce retrait est
fait par `applyAudioDownloads()` et tenu par un test (`M14` meurt) ; l'appelant
n'a plus rien à défaire.

**La ligne `slugDownload` n'était pas dans le squelette de cette tâche, et elle y
est maintenant.** `slugDownload` reste
lu par `src/views/emission.html.twig:63`, le bouton « Copier le nom de fichier
attendu ». C'est la Task 6 qui retire les deux ensemble ; le squelette ci-dessus
l'avait déjà supprimée, une tâche trop tôt.

**Rectification, mesurée en relecture :** le retirer ici n'aurait *rien cassé*.
La ligne 63 est dans la branche `{% else %}` de `{% if show.urlDownloadFlac %}
… {% elseif show.urlDownloadMp3 %} … {% else %}` — elle ne rend que pour une
émission **sans aucun audio**, qui depuis ce change est aussi dépubliée. Et
`strict_variables` vaut `false` chez Silex (vérifié :
`$app['twig']->isStrictVariables()`), donc une clé absente rendrait `href=""`,
pas une exception. Garder la ligne reste le bon geste — le retrait et celui du
bouton forment un seul diff cohérent — mais c'est un **choix de découpage**, pas
une casse évitée. Dans un change dont toute l'histoire de relecture porte sur des
affirmations qui se dissolvent à la mesure, la nuance n'est pas décorative.

Le numéro passé à `audioDownloads()` est `$show['number']`, **non slugifié**, et
c'est délibéré : c'est le contrat écrit au docblock de `canonicalDownloadName()`
(Task 4), et `slugDownload` ne le slugifiait pas non plus. Le slugifier serait un
changement de comportement que ce change n'a pas demandé. Depuis le Step 6 ce
contrat est tenu par un test (`testLeNumeroArriveNonSlugifie`) — et depuis le
Step 9 il n'est plus contournable au point d'appel : le numéro **n'y est plus un
argument**, `applyAudioDownloads()` le lit dans `$show['number']`. La mutation
M1b, qui survivait à tout, n'est plus représentable.

- [x] **Step 3: Vérifier que le site répond**

```bash
docker run --rm -v .:/app -w /app/src -p 8123:80 php:7.4-cli php -S 0.0.0.0:80 -t /app/src/public &
bin/dev-audio-fixtures
for u in / /artists /feed /emission/ailleurs-331; do
  curl -s -o /dev/null -w "$u -> %{http_code}\n" "http://127.0.0.1:8123$u"
done
```

Mesuré : `200` sur les quatre. La page d'émission ne suffit pas — un oubli de ce
genre a déjà mis `/` en 500 dans ce change, le `Finder` cherchant encore
l'ancien nom de fichier.

**Et la même mesure avant le change, pour que « 200 » veuille dire quelque
chose.** Avec le patch remisé (`git stash`), les quatre routes rendent `200` et
la page d'accueil liste **365** pages d'émission distinctes ; avec le patch,
`200` sur les quatre et la même liste, **identique au diff près** :

```bash
diff <(grep -oE '"/emission/[a-zA-Z0-9-]+"' avant.html | sort -u) \
     <(grep -oE '"/emission/[a-zA-Z0-9-]+"' apres.html | sort -u)
```

Mesuré : aucune différence. Et au niveau de `getShow()`, sur les 367 dossiers :
`367 emissions, 0 sans audio`.

- [x] **Step 4: Vérifier le scénario qui motive le change**

`index.json` d'`ailleurs-331` est un **vrai fichier versionné**. Le script du
squelette le restaurait par une ligne posée *après* l'appel à `getShow()` : un
`Fatal error` entre les deux laissait la modification en place. Ici la
restauration est **inconditionnelle** — l'octet-à-octet original est capturé
avant toute écriture et reposé par `register_shutdown_function()`, qui court
aussi sur une erreur fatale — et une copie hors dépôt double la garantie.

```bash
cp src/public/assets/emission/ailleurs-331/index.json /tmp/index-331.bak
docker run --rm -v "$PWD":/app -w /app/src php:7.4-cli php -r '
$debug=false; require("/app/src/src/bootstrap.php");
$p="/app/src/public/assets/emission/ailleurs-331/index.json";
$orig_raw=file_get_contents($p);
register_shutdown_function(function() use ($p,$orig_raw) { file_put_contents($p, $orig_raw); });
$d=json_decode($orig_raw, true); $d["title"]="La Pompa Calor Vol 3";
file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$s=getShow("ailleurs-331"); echo $s["isPublic"] ? "PUBLIEE\n" : "DEPUBLIEE\n";
echo "fichier servi : ".$s["urlDownloadMp3"]."\n";
echo "nom canonique : ".$s["canonicalDownloadName"]."\n";'
cp /tmp/index-331.bak src/public/assets/emission/ailleurs-331/index.json
git status --short
```

Mesuré :

```
PUBLIEE
fichier servi : …/ailleurs-331/ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3
nom canonique : ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3
```

Les deux dernières lignes disent tout le change en une paire : le fichier
**stocké** garde la coquille d'hier (`chalor`), l'étiquette **proposée** au
public suit le titre corrigé (`calor`), et l'émission reste publiée.

La même commande, le patch remisé, affiche `DEPUBLIEE` : le contraste est
mesuré, pas supposé. `git status --short` ne rend que
`M src/src/bootstrap.php` après coup.

**Un fichier au nom libre, tant qu'à mesurer.** Les fixtures locales portent
toutes le nom de la convention : le scénario ci-dessus ne prouve donc pas que
l'URL survit à un nom quelconque. En posant `mix été & co vol 3.mp3` dans
`ailleurs-330` (le fichier conforme écarté le temps de la mesure, reposé
ensuite), `getShow()` rend :

```
url  : …/ailleurs-330/mix%20%C3%A9t%C3%A9%20%26%20co%20vol%203.mp3
pub  : PUBLIEE
```

L'espace, l'accent et surtout l'esperluette sont encodés — c'est ce que le
`rawurlencode()` du bloc paie : un `&` brut couperait la `flashvars` du lecteur
Flash à `emission.html.twig:79`, et le nom canonique, lui, reste celui du titre.

- [x] **Step 5: Commit**

```bash
git add src/src/bootstrap.php
git commit -m "feat: getShow decouvre l'audio au lieu de calculer son nom"
```

- [x] **Step 6: Sortir la couture de `bootstrap.php` vers `audio.php`**

Ajouté après la relecture, sur décision de la personne qui tient le dépôt. Le
bloc branché aux Steps 1–5 n'était tenu par rien (onze mutations, onze
survivantes) ; il vit désormais là où la suite le charge et où la jauge le
mesure. `$pathPublicEmission` et `$urlAssets` étant déjà des locales au point
d'appel, l'extraction n'a demandé aucune modification de `$pathPublic`.

**En TDD, dans l'ordre.** `src/tests/AudioDownloadsTest.php` d'abord, dix tests
tirés du tableau des mutations, un par comportement. Rouge mesuré :

```
Tests: 53, Assertions: 62, Errors: 10.
Error: Call to undefined function Ouiedire\Tests\audioDownloads()
```

Puis la fonction, ajoutée en queue de `src/src/audio.php` :

```php
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
```

Trois choix de signature, et leur raison :

- **Le préfixe de convention est construit dedans.** Le laisser à l'appelant
  laissait vivant le mutant qui le vide ; construit ici, il meurt sur
  l'assertion d'ordre (M5b).
- **La règle de publication entre, mais pas `isPublic`.** La fonction rend
  `hasAudio` et rien d'autre : `isPublic` vient aussi du manifeste, et l'écraser
  depuis `audio.php` mettrait deux décisions au même endroit. C'est la
  composition `||` qui devait être testable, et elle l'est.

  **Ce choix a été renversé au Step 9, et la raison mérite d'être lue.**
  L'objection ne tient pas : `applyAudioDownloads()` ne fait que **dégrader**,
  jamais promouvoir, ce qui est la formulation littérale du spec — « quelle que
  soit la valeur de son champ `isPublic` ». Il n'y a donc pas deux décisions au
  même endroit, il y en a une seule, et laisser la garde au point d'appel la
  laissait hors de toute mesure : M8b, M13 et N4 y survivaient. `audioDownloads()`
  rend toujours `hasAudio` et rien d'autre ; c'est la fonction du dessus qui en
  tire la dégradation.
- **`$number` n'est pas slugifié**, ici pas plus qu'avant : c'est le contrat du
  docblock de `canonicalDownloadName()`, et il a maintenant son test.

Vert mesuré : `OK (53 tests, 81 assertions)` — 43 avant, 53 après.

Jauge, à `90` de seuil :

```
/app/src/src/audio.php : 100.00 % (28/28), seuil 90.00 %
/app/src/src/bootstrap.php : 0.00 % (0/505), seuil 90.00 %
```

`audio.php` passe de 15 à 28 instructions, toutes couvertes. `bootstrap.php`
reste à zéro : ce n'est pas ce que cette extraction corrige, elle **réduit** ce
qui y est exposé (508 → 505 instructions, et surtout six lignes de branchement
au lieu d'un calcul complet).

- [x] **Step 7: Vérifier que `getShow()` ne bouge pas**

La contrainte est que les clés posées et leurs valeurs soient **identiques**, la
garde de publication comprise. Vérifié plutôt que supposé, sur les 367 dossiers
d'émission, par `var_export()` de la valeur de retour complète — l'ordre des clés
y compris :

Le script tient en dix lignes ; il n'est pas versionné, le voici en entier pour
que la mesure se rejoue :

```php
<?php
$debug = false;
require('/app/src/src/bootstrap.php');
$base = '/app/src/public/assets/emission';
$out = array();
foreach (scandir($base) as $e) {
    if ($e[0] === '.' || !is_dir($base.'/'.$e)) { continue; }
    try { $s = getShow($e); } catch (\Exception $ex) { $out[$e] = 'EXCEPTION: '.$ex->getMessage(); continue; }
    $out[$e] = $s;
}
echo var_export($out, true);
```

```bash
docker run --rm -v "$PWD":/app -v "$TMP":/snap -w /app/src php:7.4-cli \
  php /snap/snapshot.php > avant.txt      # avant l'extraction
# … extraction …
docker run --rm -v "$PWD":/app -v "$TMP":/snap -w /app/src php:7.4-cli \
  php /snap/snapshot.php > apres.txt
diff avant.txt apres.txt
```

Mesuré : **aucune différence**, sur 53 184 lignes, et `stderr` identique lui
aussi (vide dans les deux cas). C'est le même instantané qui sert de juge aux
mutations posées dans `bootstrap.php`, plus bas.

- [x] **Step 8: Commit**

```bash
git add src/src/audio.php src/src/bootstrap.php src/tests/AudioDownloadsTest.php
git commit -m "refactor: la couture audio de getShow passe dans audio.php"
```

Commit séparé de celui du Step 5 : le branchement était juste, ce qui manquait
était sa place. Un refactor tracé, pas une correction du précédent.

- [x] **Step 9: Faire entrer la fusion et la règle de publication**

Ajouté après la relecture du Step 6, sur décision de la personne qui tient le
dépôt. Le Step 6 avait sorti le **calcul** ; il restait au point d'appel la
**fusion**, le retrait de `hasAudio` et la **garde de publication** — six lignes
que la suite n'atteint pas, et où **sept** mutations survivaient.

**La forme, et pourquoi les slugs sont nommés.** Une seconde fonction mince, en
queue de `src/src/audio.php` :

```php
function applyAudioDownloads(array $show, $directory, $urlAssets, array $slugs)
```

`$slugs` porte les trois parts déjà translittérées sous les clés `type`,
`authors`, `title`. Ce n'est pas du confort : PHP 7.4 n'a pas d'arguments nommés,
et **quatre chaînes de même type à la file** sont exactement ce qui a produit
M4b. Sous des clés, la permutation d'`authors` et de `title` n'est plus
*représentable* à l'appel — pas seulement non testée. Le numéro, lui, n'est pas
dans `$slugs` : il se lit dans `$show['number']`, tel quel, ce qui supprime M1b
par construction — l'argument où l'on ajoutait un `slugify()` n'existe plus.

**En TDD, dans l'ordre.** `src/tests/ApplyAudioDownloadsTest.php` d'abord, neuf
tests, un par comportement (deux autres viendront plus bas, pilotés par la
mutation et déclarés comme tels). Rouge mesuré :

```
Tests: 63, Assertions: 83, Errors: 9.
Error: Call to undefined function Ouiedire\Tests\applyAudioDownloads()
```

Puis la fonction, puis le point d'appel réduit à l'appel seul (bloc régénéré au
Step 2 ci-dessus). Vert mesuré : `OK (63 tests, 100 assertions)`.

**Un dixième test, ajouté après coup et assumé comme tel.** La campagne de
mutations ci-dessous a fait apparaître `N1` — `array_merge($audio, $show)` au
lieu de `array_merge($show, $audio)` — qui rend **les mêmes clés et les mêmes
valeurs dans un autre ordre**, et passait la suite entière. L'instantané des 367
aucune assertion de valeur ne pouvait le distinguer.
`testLesClesAjouteesViennentApresCellesDeLEmission` épingle `array_keys()`.
C'est un test piloté par la mutation, pas par un rouge préalable — même régime
que les cinq de la Task 2, et il est écrit ici plutôt que déguisé.

**Sa justification première était fausse, et la relecture l'a défaite.** Elle
disait que l'ordre des clés « se voit dans les gabarits et le flux ». Vérifié
consommateur par consommateur : rien ne l'observe (voir la section du trou
résiduel, plus bas). Les 3 670 lignes de l'instantané sont un artefact de
`var_export()`. Le test reste, requalifié en détecteur de changement.

**Un onzième test, celui-là pour un invariant réel.** La même relecture a
construit une mutation qui **conserve** l'ordre mais rend au manifeste la
priorité sur le calcul — elle passait la suite entière, dans le fichier certifié
à `100 % (42/42)`. Le sens de `array_merge` décide aussi de la **précédence**, et
un manifeste est une donnée externe non validée : rien ne lui interdit de porter
une de ces cinq clés.
`testLesValeursCalculeesGagnentSurCellesDuManifeste` la tue.

Vert final : `OK (65 tests, 103 assertions)` — 2 `SmokeTest` + 15 `AudioTest` +
26 `CoverageCheckTest` + 11 `AudioDownloadsTest` + 11 `ApplyAudioDownloadsTest`.

**L'objection levée, et ses deux tests.** « `isPublic` vient aussi du manifeste,
ce n'est pas à cette fonction de l'écraser. » Elle ne tient pas : la fonction ne
fait que **dégrader**, jamais promouvoir — c'est la formulation littérale du
spec (`specs/emission-contenu/spec.md`, « Aucune publication sans audio » :
« quelle que soit la valeur de son champ `isPublic` »). Les deux sens sont
mesurés séparément : `testUneEmissionPublieeSansAudioEstDepubliee` et
`testUneEmissionNonPublieeAvecAudioResteNonPubliee`. Un troisième —
`testUneEmissionPublieeAvecAudioRestePubliee` — interdit la garde
inconditionnelle qui aurait laissé les deux premiers au vert (`N4`).

`slugify()` reste dans `bootstrap.php`, et `audio.php` n'en dépend toujours pas :
c'est précisément pour cela que les slugs arrivent déjà translittérés.

Jauge, à `90` de seuil :

```
/app/src/src/audio.php : 100.00 % (42/42), seuil 90.00 %
/app/src/src/bootstrap.php : 0.00 % (0/497), seuil 90.00 %
```

`audio.php` passe de 28 à 42 instructions, toutes couvertes ; `bootstrap.php` de
505 à 497, et surtout d'un branchement en six lignes à **un appel**.

**`getShow()` ne bouge pas**, revérifié par le même instantané `var_export()` du
Step 7, `a2c8a381` contre cette branche : **aucune différence** sur 53 184
lignes, `stderr` vide dans les deux cas.

**Bout en bout, remesuré** : `200` sur `/`, `/artists`, `/feed` et
`/emission/ailleurs-331` ; et le scénario du change (titre d'`ailleurs-331`
corrigé, restauration inconditionnelle par `register_shutdown_function()`,
`git status --short` vérifié après) :

```
PUBLIEE
fichier servi : …/ailleurs-331/ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3
nom canonique : ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3
cle hasAudio  : absente
```

La dernière ligne est nouvelle et c'est celle du Step 9 : la clé ne fuit plus
jusqu'à l'appelant, et ce n'est plus un `unset()` posé à la main qui l'en empêche.

- [x] **Step 10: Commit**

```bash
git add src/src/audio.php src/src/bootstrap.php src/tests/ApplyAudioDownloadsTest.php
git commit -m "refactor: la fusion audio et la regle de publication passent dans audio.php"
```

Commit séparé de `3e152d0c`, non amendé : le Step 6 était juste, il s'arrêtait un
cran trop tôt.

### Ce qui tient ce branchement, et ce qui ne le tient pas

`sdr-004` demande 90 % sur le code touché. Depuis le Step 6, le code touché est
`audio.php`, à `100.00 % (42/42)`. Ce qui reste dans `bootstrap.php` — que la
suite **ne charge toujours pas**, `0.00 % (0/497)` — tient en **un appel** et son
tableau de slugs. Le trou n'est pas comblé, il est réduit au câblage ; le dire
franchement vaut mieux que le maquiller.

**Tenu par des tests unitaires.** Les quatre fonctions du fichier —
`findAudioFile()`, `canonicalDownloadName()`, `audioDownloads()`,
`applyAudioDownloads()` — sont à `100.00 % (42/42)`, vérifié par
`bin/coverage-check.php` sur le rapport Clover. L'ordre de sélection, la casse,
la tolérance aux formats, le dossier absent (Task 2 et Task 4) ; l'ordre des
arguments, l'encodage de l'URL, l'unité de la taille, la distinction des deux
formats et le lien symbolique cassé (Task 5, Step 6) ; et depuis le Step 9 la
fusion, l'ordre des clés rendues, le retrait de `hasAudio` et les **deux sens**
de la règle de publication.

**Tenu par une mesure, pas par un test.** Ce qui reste au point d'appel : le
`require_once`, et le câblage des trois slugs et des deux chemins depuis `$show`.
Vérifié par l'instantané des 367 émissions (Steps 7 et 9) et par les mesures des
Steps 3 et 4 — reproductibles à la main, mais rien ne les rejoue.

**Les mutations, rejouées sur le nouveau code après le Step 9.** Chacune
réellement appliquée au fichier où le code vit désormais, la suite relancée, et
l'instantané des 367 émissions passé en second juge. Le pilote de la campagne
refuse une ancre qui ne correspond pas à exactement une occurrence, pour qu'une
mutation « appliquée » ne puisse pas être en réalité un fichier intact.

Les seize d'origine d'abord ; puis les sept qui survivaient au point d'appel, à
l'endroit où le Step 9 les fait atterrir ; puis six que la nouvelle forme rend
possibles et qu'il aurait été malhonnête de ne pas chercher.

| # | Mutation | Où elle atterrit | Verdict |
| --- | --- | --- | --- |
| M1 | `$number` slugifié dans `audioDownloads()` | `audio.php` | **meurt** — 2 échecs |
| M2 | `rawurlencode()` retiré sur le **mp3** | `audio.php` | **meurt** — 2 échecs |
| M3 | arguments `mp3` / `flac` permutés | `audio.php` | **meurt** — 9 échecs |
| M4 | `$authorsSlug` / `$titleSlug` permutés | `audio.php` | **meurt** — 5 échecs |
| M5 | `$conventionPrefix` mis à `''` | `audio.php` | **meurt** — 16 erreurs, mais voir M5b |
| M5b | `$conventionPrefix` mis à `'zzz_'` | `audio.php` | **meurt** — 3 échecs |
| M6 | `/(1024*1024)` devient `/1024` | `audio.php` | **meurt** — 3 échecs |
| M7 | `' Mo'` devient `' Go'` | `audio.php` | **meurt** — 3 échecs |
| M8 | `hasAudio` : `\|\|` devient `&&` | `audio.php` | **meurt** — 4 échecs |
| M9 | `canonicalDownloadName` rendu `''` | `audio.php` | **meurt** — 5 échecs |
| M10 | `require_once __DIR__.'/audio.php';` supprimé | `bootstrap.php` | **survit** à la suite — chaque page en erreur fatale |
| M11 | `urlDownloadFlac` forcé à `null` | `audio.php` | **meurt** — 2 échecs |
| M12 | stat raté rendu `0` au lieu de `false` | `audio.php` | **meurt** — 1 échec |
| M16 | `rawurlencode()` retiré sur le **flac** seul | `audio.php` | **meurt** — 1 échec |
| M17 | `round(…, 2)` devient `round(…, 1)` | `audio.php` | **meurt** — 1 échec |
| M18 | `_` final du préfixe retiré | `audio.php` | **meurt** — 1 échec |
| M1b | `slugify()` sur le numéro passé à l'appel | — | **non représentable** : le numéro n'est plus un argument |
| M1b-fn | `$show['number']` slugifié **dans** `applyAudioDownloads()` | `audio.php` | **meurt** — 1 échec |
| M4b | auteurs / titre permutés **par position** à l'appel | — | **non représentable** : les slugs sont sous clés nommées |
| M4b-fn | `$slugs['authors']` / `$slugs['title']` permutés dans l'appel interne | `audio.php` | **meurt** — 3 échecs |
| M4b-appel | `'authors' => slugify($show['title'])`, et l'inverse | `bootstrap.php` | **survit** à la suite — instantané : 732 lignes |
| M8b | garde inversée : `if ($hasAudio)` | `audio.php` | **meurt** — 3 échecs |
| M13 | `$hasAudio` forcé à `true` | `audio.php` | **meurt** — 1 échec |
| M14 | le retrait de `hasAudio` supprimé | `audio.php` | **meurt** — 2 échecs |
| M15-fn | `$directory` / `$urlAssets` permutés dans l'appel interne | `audio.php` | **meurt** — 5 échecs |
| M15-appel | `$pathPublicEmission` / `$urlAssets` permutés à l'appel | `bootstrap.php` | **survit** à la suite — instantané : 2 320 lignes |
| N1 | `array_merge($audio, $show)` : sens de la fusion inversé | `audio.php` | **meurt** — 1 échec (test ajouté pour elle) |
| N2 | `return $audio;` au lieu de `return $show;` | `audio.php` | **meurt** — 6 échecs |
| N3 | `$slugs['type']` lu comme `$slugs['authors']` | `audio.php` | **meurt** — 4 échecs |
| N4 | garde rendue inconditionnelle : `$show['isPublic'] = false;` | `audio.php` | **meurt** — 2 échecs |
| N5 | `$show['number']` lu comme `$slugs['type']` | `audio.php` | **meurt** — 4 échecs |
| N6 | `'type' => slugify($show['title'])` au point d'appel | `bootstrap.php` | **survit** à la suite — instantané : 732 lignes |

**Trente mutations appliquées, vingt-six meurent sur la suite.** Deux des sept qui survivaient
au point d'appel n'existent même plus comme mutations : M1b et M4b ne sont pas
« tuées », elles sont **non représentables**, et c'est un résultat plus fort. Les
cinq autres — M4b-fn, M8b, M13, M14, M15-fn — ont suivi le code dans `audio.php`
et y meurent.

**M10 n'a pas bougé et ne le pouvait pas.** Sa suppression ne change rien à la
suite, qui charge `audio.php` par son propre `require_once`. Elle reste ce
qu'elle était : chaque page en erreur fatale — mesuré à nouveau sur l'instantané,
qui part en `Fatal error` (`audio.php` n'est pas dans l'autoload `files` de
Composer, vérifié).

**M5 meurt, mais pas pour la bonne raison, et c'est M5b qui le montre.** Avec le
préfixe vide, PHP 7.4 émet `strpos(): Empty needle`, que PHPUnit convertit en
erreur : sept tests tombent sans qu'aucune assertion d'ordre ait parlé. Le même
mutant sur PHP 8 ne préviendrait pas. M5b — un préfixe non vide qu'aucun fichier
ne porte — fait tomber `testLaConventionPasseDevantLeNomLibre` sur son
assertion : c'est celui-là qui prouve que la règle est tenue.

**Trois mutations tenaient encore par un test qui passait pour la mauvaise
raison — la relecture les a trouvées, elles sont corrigées.** M16 : le test
d'encodage n'écrivait qu'un `.mp3`, laissant `rawurlencode()` libre sur le flac,
qui emprunte pourtant le même chemin. M17 : la taille de la fixture valait
exactement 1,5 Mio, où `round(…, 2)` et `round(…, 1)` rendent la même chaîne — le
test portait « arrondie au centième » dans son nom sans le tenir. M18 : le `_`
final du préfixe n'était épinglé par rien, alors que c'est lui qui sépare la 17
de la 17bis, qui existe.

**Ce que la couture laisse encore à découvert, et il faut le nommer sans
compter.** Ce qui reste n'est plus du calcul ni de la décision : c'est le
**câblage** de l'appel, et il est **non testé, point**. Écrire « il reste quatre
survivantes » donnerait à lire un résidu mesuré là où il n'y a qu'une liste :
une seconde relecture en a trouvé **trois de plus** sans chercher longtemps —
`'title' => $show['title']` et `'authors' => $show['authors']` (slugify sauté,
668 et 562 lignes d'écart), et surtout l'appel **dont on jette la valeur de
retour**, `applyAudioDownloads($show, …);` au lieu de `$show = …` : 1 835 lignes
d'écart, et la suite verte.

**Cette dernière est une fragilité que ce Step a créée**, et elle mérite d'être
posée à côté des gains : un seul jeton perdu abandonne désormais la fusion
*et* toute la règle de publication, là où il fallait auparavant toucher
plusieurs instructions pour faire autant de dégâts. C'est le prix de la
concentration.

Les quatre nommées ci-dessous ne sont donc pas *le* résidu — ce sont celles
qu'on a écrites.

- **M4b-appel** et **N6** sont des valeurs mal branchées sur des clés
  correctement nommées : `'authors' => slugify($show['title'])`,
  `'type' => slugify($show['title'])`. Les clés nommées interdisent la
  permutation *positionnelle* (M4b, M1b), pas le fait de lire le mauvais champ
  de `$show`. **Aucune signature ne peut fermer ça** — seul un test qui appelle
  `getShow()` le verrait.
- **M15-appel** est, lui, une vraie permutation positionnelle :
  `$pathPublicEmission` et `$urlAssets` restent deux chaînes de même type à la
  file. Elle se fermerait en les nommant comme les slugs. **Ce n'est délibérément
  pas fait** : le point d'appel resterait non testé de toute façon, à cause des
  deux ci-dessus, et échanger une survivante contre un second tableau littéral
  achèterait un chiffre, pas une garantie. Si la relecture préfère l'inverse,
  c'est une ligne à changer et la mesure est déjà là pour l'arbitrer.
- **M10**, le `require_once`, reste hors de portée d'une suite qui charge
  `audio.php` elle-même.

**Aucune de celles-ci n'est équivalente, et ce n'est pas une opinion.**
L'instantané des 367 émissions le mesure : 732 lignes pour M4b-appel, 732 pour
N6, ~2 300 pour M15-appel (deux comptages, même conclusion), et M10 met chaque
page en `Fatal error`. Toutes
changent la sortie **sur les données d'aujourd'hui** — il n'y a même pas de
témoin à construire, les émissions existantes suffisent. C'est ce qui les sépare
de l'ancienne M1b, qui elle survivait aussi à l'instantané.

Aucune ne se tue sans tester `getShow()` elle-même, c'est-à-dire sans monter un
harnais Silex. Le Step 9 a poussé la couture aussi loin qu'elle peut aller sans
ce harnais ; ce qui reste est le prix de ne pas l'avoir monté, et ce prix se
nomme mais ne se chiffre pas.

**Une survivante trouvée *dans* le fichier certifié à 100 %, et corrigée.** La
relecture a construit une mutation qui conserve l'ordre des clés mais rend au
manifeste la priorité sur le calcul — elle passait la suite entière. Le sens de
`array_merge` ne décide pas que de l'ordre : il décide de la **précédence**, et
un manifeste est une donnée externe non validée que rien n'empêche de porter une
de ces cinq clés. `testLesValeursCalculeesGagnentSurCellesDuManifeste` l'épingle
désormais. C'est le rappel utile que 100 % de lignes couvertes n'est pas une
preuve : c'est un plancher.

**Et le test d'ordre des clés était justifié par une affirmation fausse.** Sa
première rédaction disait que cet ordre « se voit dans les gabarits et le flux ».
Vérifié consommateur par consommateur : les gabarits lisent `show.foo` par son
nom, la route RSS construit chaque champ, oEmbed bâtit son propre tableau,
`getShows()` ne lit que `isPublic`. **Rien n'observe l'ordre des clés de
`$show`** ; les 3 670 lignes de l'instantané sont un artefact de `var_export()`.
Le test est conservé comme ce qu'il est — un détecteur de changement sur le sens
de la fusion, justifié par la mutation et non par un effet visible — et son
commentaire le dit.

**Note sur l'ancienne M1b, conservée pour le choix de ses témoins.** Elle n'est
plus représentable, mais le raisonnement sur son témoin reste utile ailleurs :
`17BIS` ne sépare pas `slugify()` de `strtolower()`, puisque les deux rendent
`17bis`. Les témoins qui les séparent réellement sont `17 BIS`, `17_bis`, `17.5`,
`17é` — c'est `17 bis` qu'emploie `testLeNumeroSeLitDansLEmissionEtNEstPasSlugifie`.
Un numéro portant un tiret ne peut pas arriver jusque-là : `explode('-')`
l'aurait coupé avant. `17BIS` reste le bon témoin pour la mutation *voisine* —
`strtolower()` retiré — et c'est à ce titre qu'il figure au test de la Task 4.

**Deux constats de relecture, pour la Task 6 :**

- `$show['canonicalDownloadName']` n'est **consommé par rien** aujourd'hui — la
  clé est prête, aucun gabarit ne la lit. C'est l'attribut `download=` de la
  Task 6 qui la rendra vivante, et c'est là que l'exigence « nom canonique au
  téléchargement » du spec se ferme. Elle est **préparée**, pas tenue.
- `slugify('Ouïedire')` mange le `ï` : le nom canonique proposé pour ces
  émissions est `ouiedire_ouedire-001_…`. Aujourd'hui cette faute ne fait que
  coïncider avec ce qui est sur le disque. Dès que la Task 6 en fera un
  `download=`, ce sera un nom que le site **assigne délibérément** à un fichier
  enregistré. À trancher là-bas.

**Un gain non prévu, relevé en relecture.** L'ancien code faisait
`strtolower($urlAssets.'/'.….'.mp3')` : un fichier portant une majuscule sur le
disque recevait une URL en minuscules, donc un 404. Le nouveau bloc n'abaisse
plus l'URL, seulement l'étiquette d'enregistrement. C'est une correction, pas
une régression.

---

## Task 6: Retirer ce qui n'a plus d'objet

**Files:**
- Modify: `src/src/bootstrap.php` (`slugDownload`)
- Modify: `src/views/emission.html.twig`

C'est la tâche qui **ferme** l'exigence « Nom canonique au téléchargement » du
spec. Depuis la Task 4, `canonicalDownloadName` est calculé et fusionné dans
`$show` — et consommé par rien. L'exigence était *préparée*, pas tenue ; c'est
l'attribut `download` posé ici qui la tient.

- [x] **Step 1: Supprimer `slugDownload`**

Supprimée de `src/src/bootstrap.php`, avec la ligne vide qui la précédait :

```php
    $show['slugDownload'] = strtolower(sprintf('%s/ouiedire_%s-%s_%s_%s', $urlAssets, slugify($show['type']), $show['number'], slugify($show['authors']), slugify($show['title'])));
```

Bloc final, régénéré depuis le fichier :

```php
    $show = applyAudioDownloads($show, $pathPublicEmission, $urlAssets, array(
        'type' => slugify($show['type']),
        'authors' => slugify($show['authors']),
        'title' => slugify($show['title']),
    ));

    // Guess covers URL. Toute image du dossier compte, pour qu'une couverture
```

**Une redondance disparaît, et elle est mesurée.** Sur les 367 émissions,
`$show['slugDownload'] === strtolower($urlAssets).'/'.$show['canonicalDownloadName']` —
**zéro divergence**. La même règle vivait à deux endroits : une copie testée,
dans `audio.php`, et une copie non testée au point d'appel. C'est la seconde qui
part.

La mesure ne tient qu'à une condition, et il faut l'écrire : le témoin doit
reconstruire `$urlAssets` avec `$show['id']`, **pas** avec `$show['number']`.
`getShow()` remet le numéro à trois chiffres (`'00'.$show['id']`) **après** le
bloc audio ; comparer avec le numéro rendu fait apparaître 136 fausses
divergences. Première mesure faite ainsi, et corrigée.

- [x] **Step 2: Réécrire les boutons de téléchargement**

Bloc final, régénéré depuis `src/views/emission.html.twig` :

```twig
            {% if show.urlDownloadFlac %}
                <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadFlac }}" download="{{ show.canonicalDownloadName }}.flac" title="Télécharger l'émission au format FLAC ({{ show.sizeDownloadFlac }})"><i class="icon-download" style="color:#{% if app.request.cookies.get('night') %}555{% else %}fff{% endif %}"></i></a></h3>
            {% elseif show.urlDownloadMp3 %}
                <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadMp3 }}" download="{{ show.canonicalDownloadName }}.mp3" title="Télécharger l'émission au format MP3 ({{ show.sizeDownloadMp3 }})"><i class="icon-download" style="color:#{% if app.request.cookies.get('night') %}555{% else %}fff{% endif %}"></i></a></h3>
            {% endif %}
```

Le troisième bouton, `title="Copier le nom de fichier attendu"`, disparaît :
depuis la Task 5, une émission sans audio est dépubliée, et sa branche ne rendait
plus que pour une page atteinte en URL directe.

**L'extension est concaténée ici, pas rendue par la fonction.**
`canonicalDownloadName()` rend un nom **sans extension**, c'est son contrat
(Task 4) ; le `.mp3` / `.flac` appartient au format que le lien sert, et seul le
gabarit sait lequel des deux il vient de choisir.

- [x] **Step 3: Vérifier qu'aucune référence ne survit**

```bash
grep -rn "slugDownload" src/ --exclude-dir=vendor
```

Mesuré : **aucune sortie**, code de retour `1`. Élargi au dépôt entier
(`--exclude-dir=.git --exclude-dir=vendor`), il ne reste que les artefacts
`openspec/` de ce change, qui parlent du retrait. Aucun gabarit, aucun script,
aucun JS.

**À ne pas confondre avec `urlDownload`** — sans `slug`, au singulier. Cette
clé-là existe toujours, vaut `null` depuis toujours (posée à `null` en tête de
`getShow()`, jamais réassignée), et trois consommateurs la lisent :
`embed.html.twig`, `window.MejsOuiedireDownloadUrl` dans `emission.html.twig`, et
le `urlencode()` d'oEmbed. C'est un câblage mort **antérieur** à ce change, hors
de son périmètre, et qui mérite son propre change.

- [x] **Step 4: Vérifier l'attribut dans la page rendue**

```bash
docker run -d --name ouiedire-dev -v "$PWD":/app -w /app/src -p 8123:80 \
  php:7.4-cli php -S 0.0.0.0:80 -t /app/src/public
rm -rf src/cache/*
curl -s 'http://127.0.0.1:8123/emission/ailleurs-331' | grep -o 'download="[^"]*"'
```

**Le `rm -rf src/cache/*` n'est pas décoratif** : l'application monte un cache
HTTP dans `src/cache`, et une seconde requête sur la même URL est servie depuis
le disque. Une mesure faite sans ce nettoyage a d'abord montré la branche MP3
là où un fichier FLAC venait d'être posé.

Mesuré, `ailleurs-331` :

```html
<a href="http://127.0.0.1:8123/assets/emission/ailleurs-331/ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3"
   download="ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3"
```

`chalor` est la coquille du titre au manifeste : le nom canonique suit le titre,
c'est exactement ce que le spec demande.

**Cinq autres émissions, rendues et relevées** — une par branche de type :

| Émission | `href` (fichier stocké) | `download=` |
| --- | --- | --- |
| `ouiedire-1` | `ouiedire_ouedire-001_bozoo-valkiri_no-theme.mp3` | `ouiedire_ouedire-1_bozoo-valkiri_no-theme.mp3` |
| `ouiedire-6` | `ouiedire_ouedire-006_…_altration-auditive.mp3` | `ouiedire_ouedire-6_…_altration-auditive.mp3` |
| `ailleurs-1` | `ouiedire_ailleurs-001_dj-damie-boy_sans-thme.mp3` | `ouiedire_ailleurs-1_dj-damie-boy_sans-thme.mp3` |
| `bagage-1` | `ouiedire_bagage-001_mutant-swing_….mp3` | `ouiedire_bagage-1_mutant-swing_….mp3` |
| `bureau-1` | `ouiedire_bureau-001_de-traviole_….mp3` | `ouiedire_bureau-1_de-traviole_….mp3` |

**La branche FLAC n'a aucun témoin dans le dépôt** — `find . -name '*.flac'` rend
zéro, contre 367 `.mp3`. Elle a donc été mesurée en posant un `.flac` temporaire
au nom libre dans `ailleurs-331`, puis en le retirant :

```html
<a href="…/ailleurs-331/mon-mix-libre.flac"
   download="ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.flac"
```

C'est le change entier en une ligne : le fichier stocké s'appelle
`mon-mix-libre.flac`, il s'enregistre sous le nom canonique.

- [x] **Step 4bis : `download` n'est honoré que sur la même origine**

L'attribut est ignoré par les navigateurs dès que la cible est d'une autre
origine — l'exigence serait alors fausse sans que rien n'échoue. Vérifié plutôt
que supposé : `$urlAssets` est bâti **depuis la requête courante** (schéma, hôte,
chemin de base) chaque fois qu'il y a une requête, donc par construction sur
l'origine servante. Mesuré en dev : la page est servie sur
`http://127.0.0.1:8123`, le `href` pointe sur `http://127.0.0.1:8123/assets/…`.
La branche sans requête (CLI) code en dur `https://www.ouiedire.net/assets`,
l'origine de production.

- [x] **Step 4ter : l'échappement Twig n'altère aucun nom**

L'autoéchappement HTML est actif — mesuré sur `ailleurs-126`, dont le titre porte
une esperluette et une apostrophe : `&amp;` et `&#039;` dans le rendu. Le nom
canonique, lui, ne peut pas en souffrir : sur les 367 émissions,
`htmlspecialchars($nom, ENT_QUOTES)` rend **le nom inchangé, 367 fois sur 367**,
et l'alphabet complet des noms produits est `[a-z0-9_-]`.

- [x] **Step 4quater : le scénario complet du spec, mesuré**

« Téléchargement après correction du titre ». Le titre d'`ailleurs-331` est
corrigé au manifeste (`Chalor` → `Chaleur`), la page rerendue, puis `index.json`
restauré **inconditionnellement** (`trap … EXIT`, copie de sûreté *et*
`git checkout --`), empreinte `git hash-object` vérifiée identique après coup et
`git status` relu.

| | avant | après correction |
| --- | --- | --- |
| `href` | `…_la-pompa-chalor-vol-3.mp3` | `…_la-pompa-chalor-vol-3.mp3` — **inchangé** |
| `download=` | `…_la-pompa-chalor-vol-3.mp3` | `…_la-pompa-chaleur-vol-3.mp3` — **suit le titre** |
| page | `200` | `200` |
| listée en accueil | oui | oui |

Les trois clauses tiennent ensemble : le nom d'enregistrement suit la correction,
l'émission n'est pas dépubliée, et le fichier servi ne bouge pas.

- [x] **Step 4quinquies : non-régression sur les 367 émissions**

Même instantané `var_export()` qu'au Step 7 de la Task 5, `bfbe9c21` contre cet
état. Mesuré : **367 suppressions, 0 ajout, 0 modification**, toutes de la forme
`'slugDownload' => …` — une par émission, et rien d'autre. `stderr` vide dans les
deux passes. Les quatre routes (`/`, `/artists`, `/feed`,
`/emission/ailleurs-331`) rendent `200`.

Suite complète : `OK (65 tests, 103 assertions)`, décompte inchangé — aucun test
ne portait sur `slugDownload`, et c'est précisément ce qui rendait cette copie
non tenue. Jauge par fichier : `/app/src/src/audio.php : 100.00 % (42/42),
seuil 90.00 %`. `bootstrap.php` passe de **497 à 496 instructions** — mesuré des
deux côtés, l'écart vaut exactement l'instruction retirée.

### La question du `ï` mangé, tranchée

`slugify('Ouïedire')` rend `ouedire` : le nom canonique de ces émissions est
`ouiedire_ouedire-1_…`. Tant que la clé n'était lue par personne, la faute ne
faisait que coïncider avec ce qui est sur le disque ; l'attribut `download` en
fait un nom que le site **assigne** à un fichier enregistré chez la personne qui
le télécharge. La question ne pouvait donc pas passer en silence.

**La mesure d'abord, et elle est plus large que la question posée.** Ce n'est pas
le `ï` d'`Ouïedire` qui est en cause, c'est **tout caractère non-ASCII** :

- `iconv('utf-8', 'us-ascii//TRANSLIT', 'ï')` rend l'octet `0x3f`, c'est-à-dire
  `?`, que le `preg_replace('#[^-\w]+#', '')` suivant efface. Aucune
  translittération n'a lieu.
- La cause est nommée dans le code lui-même : `slugify()` pose
  `setlocale(LC_CTYPE, "en_US.utf8")`, et cette locale **n'existe pas** dans
  l'image (`locale -a` rend `C`, `C.UTF-8`, `POSIX`). Sans elle, le `//TRANSLIT`
  de la glibc retombe sur `?`. C'est exactement le lien StackOverflow que le
  commentaire de la fonction porte depuis toujours.
- Portée : **154 émissions sur 367** (42 %) portent un caractère non-ASCII dans
  leur type, leurs auteurices ou leur titre, et voient donc leur nom canonique
  amputé. `Sans thème` → `sans-thme`, `Épisodique` → `pisodique`, `Gerçure` →
  `gerure`. Les 12 émissions en `ouedire` en sont un cas particulier, pas le
  sujet.

**Ce qui tranche : les fichiers déjà sur le disque portent ces mêmes noms.**
`ouiedire_ailleurs-001_dj-damie-boy_sans-thme.mp3`,
`ouiedire_ailleurs-110_johan_pisodique.mp3`,
`ouiedire_ouedire-006_bozoo-valkiri_altration-auditive.mp3` — vérifié fichier par
fichier. La production ampute donc depuis toujours, et l'attribut `download`
n'invente rien : il propose le nom que l'archive donne déjà à ses propres
fichiers, et que le bouton « Copier le nom de fichier attendu » affichait en
toutes lettres par `slugDownload`.

**Décision : poser l'attribut avec le nom tel quel, et sortir la réparation de ce
change.** Trois raisons, dans cet ordre :

1. **Le périmètre.** Réparer, c'est toucher `slugify()`, que le dépôt emploie
   aussi pour la convention des couvertures et pour les pages d'artistes. Le
   rayon de souffle dépasse de loin ce change, et `sdr-004` demanderait des tests
   sur tout ce qui bouge.
2. **La direction.** Un correctif étroit existe — lire `$show['typeSlug']`, déjà
   ASCII, au lieu de `slugify($show['type'])` au point d'appel — mais il ne
   réparerait que 6 des 154 émissions et laisserait les 148 autres amputées. Un
   demi-correctif qui masque la mesure est pire que la mesure.
3. **Le coût du statu quo est connu et nul.** Un nom amputé est une verrue
   d'orthographe sur un fichier qui se télécharge, s'ouvre et se lit. Rien ne
   casse, et ce change n'aggrave rien.

**Ce qu'il reste à faire, et qui n'est pas fait ici** : un change dédié, qui
répare `slugify()` — locale installée dans l'image, ou vraie translittération
sans dépendance à `iconv` — et qui mesure ce que le renommage déplace pour les
154 émissions, la convention des couvertures comprise.

### Deux constats de rendu, relevés au passage

- **Le numéro perd son remplissage à zéro dans le nom proposé.** Le fichier
  stocké s'appelle `ouiedire_ailleurs-054_…`, le `download=` propose
  `ouiedire_ailleurs-54_…`. Sur les 367, **136 noms canoniques diffèrent du nom
  du fichier stocké**, et c'est très majoritairement cela. Ce n'est pas une
  régression : `slugDownload` proposait déjà le numéro non rempli, et la Task 4 a
  épinglé ce contrat. C'est simplement, maintenant, un nom qui atterrit sur un
  disque.

  > **Ce constat a été renversé, et le tableau de rendu ci-dessus avec lui.** Ce
  > qu'il décrivait comme un écart acceptable était le symptôme visible d'un
  > défaut : le préfixe de convention était bâti sur le numéro brut, et **136
  > émissions sur 367 n'étaient pas reconnues comme conformes**. Voir
  > « Correctif post-Task 6 », plus bas. Depuis, le nom proposé porte le numéro
  > rempli et coïncide avec le fichier stocké sur les 367 : le `download=` de
  > `ailleurs-54` vaut `ouiedire_ailleurs-054_…`.
- **Une correction héritée, relevée en Task 5, devient visible ici.** L'ancien
  code abaissait toute l'URL ; le nouveau n'abaisse que l'étiquette
  d'enregistrement. Un fichier portant une majuscule sur le disque ne reçoit plus
  une URL en minuscules, donc plus un 404.

- [x] **Step 5: Commit**

```bash
git add src/src/bootstrap.php src/views/emission.html.twig
git commit -m "refactor: le nom canonique devient l'attribut download"
```

---

## Task 7: Documentation

**Files:**
- Modify: `README.md`
- Modify: `.github/workflows/emission.yml` (prose du corps de Pull Request, pas de logique)

**La phrase proposée par le squelette de ce plan était fausse, et n'a pas été
reprise.** Elle disait « le site retient le premier fichier audio qu'il trouve »
et « un nom construit à partir du titre et des auteurices ». Deux erreurs, l'une
et l'autre écrites avant l'implémentation : l'ordre n'est pas celui du système de
fichiers mais un tri — les fichiers suivant la convention historique d'abord,
l'alphabétique par octets départageant chaque groupe (`findAudioFile()`) ; et le
nom canonique se construit sur quatre parts, le type, le numéro rempli à trois
chiffres, les auteurices et le titre (`canonicalDownloadName()`, appelée par
`audioDownloads()` avec `paddedShowNumber($number)`).

- [x] **Step 1: Dire que le nom du fichier est libre**

Ajouté à `README.md`, section « Publier une nouvelle émission », à la suite du
paragraphe sur le dépôt du MP3 :

```markdown
Le nom du fichier audio est libre : le site retient un fichier par format (MP3,
FLAC) dans le dossier de l'émission, quel que soit son nom. Une faute de frappe
dans le titre ne dépublie plus l'émission.

Le téléchargement, lui, propose toujours un nom canonique, construit à partir du
type, du numéro rempli à trois chiffres, des auteurices et du titre :
`ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3.mp3`. Ce nom est
recalculé depuis le manifeste à chaque visite : corriger un titre depuis
`/admin/` change le nom proposé sans toucher au fichier déposé. Limite connue,
et défaut à réparer : les caractères accentués y sont supprimés au lieu d'être
translittérés, si bien que « Sans thème » donne `sans-thme`.

Déposer plusieurs fichiers d'un même format dans un dossier reste possible, mais
exceptionnel. Le site retient alors en premier ceux qui suivent la convention
historique `ouiedire_<collection>-<numéro>_`, où le numéro est reconnu sous sa
forme brute (`ailleurs-1`) comme sous sa forme remplie (`ailleurs-001`) ;
l'ordre alphabétique départage à l'intérieur de chaque groupe.
```

La limite `slugify()` est nommée comme limite, pas comme comportement voulu : sa
réparation est hors du périmètre de ce change, et touche 154 des 367 émissions.

L'attribut `download` n'est honoré que sur la même origine ; les audios sont
servis depuis `urlAssets`, sous le domaine du site. La condition est donc
toujours remplie ici, et le README n'en parle pas.

- [x] **Step 2: Relire, et chercher ce qui prescrivait encore un nom**

La recherche prescrite au groupe 5.2 a trouvé une seconde page : le corps de la
Pull Request produit par `.github/workflows/emission.yml` demandait « obtenir le
nom de fichier attendu pour le MP3 en cliquant sur le bouton de téléchargement du
morceau ». Ce bouton a été supprimé en Task 6, et la phrase était donc devenue un
mensonge — et le seul endroit du dépôt qui prescrivait encore un nom de fichier
au déposant. La ligne est retirée ; le dépôt du MP3 précise « sous le nom de son
choix ».

Aucune autre occurrence : `README.md` n'en portait pas d'autre, il n'y a pas de
`docs/`, et les seules mentions restantes de `ouiedire_…` hors tests concernent
les **couvertures** (`bin/migration-prepare`, spec `emission-contenu`), dont la
convention n'est pas touchée par ce change.

- [x] **Step 3: Commit**

```bash
git add README.md .github/workflows/emission.yml openspec/changes/audio-sans-convention
git commit -m "docs: le nom du fichier audio n'a plus d'importance"
```

---

## Task 8: Vérification d'ensemble et bascule

- [x] **Step 1: Aucune émission ne change de fichier servi**

```bash
docker run --rm -v .:/app -w /app/src php:7.4-cli php -r '
$debug=false; require("/app/src/src/bootstrap.php");
$base="/app/src/public/assets/emission"; $n=0; $ko=0;
foreach (scandir($base) as $d) {
  if ($d[0]===".") continue;
  try { $s=getShow($d); } catch (\Exception $e) { continue; }
  $n++; if (!$s["urlDownloadMp3"] && !$s["urlDownloadFlac"]) { $ko++; echo "sans audio: $d\n"; }
}
echo "$n emissions, $ko sans audio\n";'
```

Attendu : `367 emissions, 0 sans audio` avec les fixtures posées par `bin/dev-audio-fixtures`.

**Relevé, tel quel :** `367 emissions, 0 sans audio`.

**Et ce chiffre ne dit pas ce qu'on aimerait qu'il dise.** Les 367 fichiers audio
présents dans l'arbre font **tous zéro octet** — mesuré : `find … -size 0` en
compte 367, `! -size 0` en compte 0. Ce sont les fixtures de
`bin/dev-audio-fixtures`, gitignorées (`.gitignore:17 *.mp3`), et le script les
nomme en appelant l'application elle-même : `ouiedire_<type>-<number>_<authors>_<title>.mp3`
avec le `number` **déjà rempli** par `getShow()`. **Sans ces fixtures, le même
relevé rendrait `367 emissions, 367 sans audio`** : le dépôt ne contient aucun
vrai fichier audio, ils vivent sur Nextcloud.

Conséquence à porter en revue : ce Step vérifie que le balayage **trouve** un
fichier et que la règle de publication ne dégrade personne. Il ne vérifie pas
que le fichier trouvé est le même qu'avant sur l'archive réelle, et il ne le
peut pas depuis ce dépôt. La même réserve vaut, rétroactivement, pour les
mesures « 231/367 » et « 367/367 » consignées plus bas : elles portent sur des
fixtures nommées par la convention **par construction**, pas sur les noms
réellement déposés sur Nextcloud. Ce qu'elles établissent reste vrai — que les
deux formes du préfixe se comportent comme annoncé sur des noms canoniques —
mais elles ne mesurent pas le désordre de l'archive réelle, qui est le motif du
change.

- [x] **Step 2: Suite complète et couverture**

**Ce step prescrivait `--coverage-text`, et c'est un défaut du plan.** C'est le
constat même qui a motivé la Task 3 : `--coverage-text` ne rend ni les fonctions
libres ni le détail par fichier, et son total est capturé par `bootstrap.php`.
Il ne peut pas exprimer le seuil que `sdr-004` demande. La commande retenue est
celle documentée dans `CLAUDE.local.md` :

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
docker run --rm -v .:/app -w /app/src ouiedire-test sh -c \
  "vendor/bin/phpunit --coverage-clover /app/clover.xml 2>/dev/null && \
   php /app/bin/coverage-check.php /app/clover.xml src/src/audio.php 90"
```

Relevé, tel quel :

```
OK (78 tests, 871 assertions)
/app/src/src/audio.php : 100.00 % (53/53), seuil 90.00 %
```

78 tests et non 75 : les trois du harnais `BootstrapShowTest`, versé plus bas.

**Et la même commande sur `bootstrap.php` rend `0.00 % (0/492)`, code 1.** Ce
n'est pas une régression, c'est le constat de la Task 5 chiffré : la suite
n'amorce pas `bootstrap.php`, et le harnais qui le juge tourne dans un
**processus fils**, invisible de pcov. Sa garantie est de mutation, pas de
couverture de ligne — les deux mesures ne se remplacent pas, et prétendre le
contraire serait exactement le silence que ce change traque.

- [x] **Step 3: Valider le change**

```bash
openspec validate audio-sans-convention --type change --strict
```

Attendu : `Change 'audio-sans-convention' is valid`.

Relevé, tel quel : `Change 'audio-sans-convention' is valid`, code de retour `0`.

- [ ] **Step 4: Après déploiement, relever le gain réel**

```bash
curl -s https://www.ouiedire.net/ | grep -oE 'emission/[a-z]+-[0-9a-z]+' | sed 's#emission/##' | sort -u > /tmp/apres.txt
for s in ailleurs-97 ailleurs-115 ailleurs-234 ailleurs-304 ailleurs-316; do
  grep -qx "$s" /tmp/apres.txt && echo "  $s : REAPPARUE" || echo "  $s : toujours absente"
done
```

Chaque `RÉAPPARUE` est une émission dont l'audio existait sous un autre nom. Les absentes n'ont jamais eu de fichier — ce change n'y peut rien, et c'est la mesure qui le dit.

**Ce step reste décoché : il se lance après déploiement, et rien ici ne le
remplace.** Ce qui a été fait, c'est le préparer.

**Les cinq dossiers existent, et portent chacun un `index.json`.** Relevé :

```
ailleurs-97    dossier=oui  index.json=oui  audios=1
ailleurs-115   dossier=oui  index.json=oui  audios=1
ailleurs-234   dossier=oui  index.json=oui  audios=1
ailleurs-304   dossier=oui  index.json=oui  audios=1
ailleurs-316   dossier=oui  index.json=oui  audios=1
```

**Les cinq sont déjà servies en local, et cela change le sens de la mesure
d'après déploiement.** Relevé par `getShow()` :

```
ailleurs-97    isPublic=true  mp3=ouiedire_ailleurs-097_sammy-stein_un-nouveau-dpart.mp3
ailleurs-115   isPublic=true  mp3=ouiedire_ailleurs-115_fabien-aka-terrificolor_paiens-paillette.mp3
ailleurs-234   isPublic=true  mp3=ouiedire_ailleurs-234_silicate_original-motion-picture-soundtrack.mp3
ailleurs-304   isPublic=true  mp3=ouiedire_ailleurs-304_damien-schultz_n-a.mp3
ailleurs-316   isPublic=true  mp3=ouiedire_ailleurs-316_ff-jean-et-johan_la-zone-en-96--30-doigts.mp3
```

L'unique audio de chacun des cinq est une **fixture de zéro octet** posée par
`bin/dev-audio-fixtures`, sous le nom canonique que l'application calcule
elle-même. Le local ne peut donc **rien** dire de ces cinq émissions : il répond
à la question « le balayage trouve-t-il un fichier canonique que j'ai moi-même
posé ? », jamais « un fichier existe-t-il sur Nextcloud sous un autre nom ? ».

Ce que le Step 4 mesurera après déploiement garde donc tout son sens, et lui
seul : un `RÉAPPARUE` dira qu'un audio existait sous un nom que l'ancienne
convention refusait ; un `toujours absente` dira qu'il n'y a pas de fichier du
tout. Aucune des deux réponses n'est prédite ici.

---

## Ce que la relecture a ajouté au périmètre

### `bootstrap.php` était hors de toute suite, et c'est là que le dernier défaut vivait

Le correctif post-Task 6 le disait déjà pour M14 : `bootstrap.php` n'est pas
amorcé par PHPUnit, et son seul juge était un script `var_export()` **non
versionné**, recopié dans ce document. Une garantie qui tient à ce que
quelqu'un pense à lancer un script à la main n'en est pas une.

**Trois mutations ont été réellement appliquées à `bootstrap.php`, la suite
relancée, puis le fichier restauré** — restauration vérifiée par `md5` après
chacune (`93783f321473d3dddf85976b0f2e0196`), `diff` étant proxifié dans cet
environnement.

| # | Mutation dans `bootstrap.php` | Effet mesuré sur l'archive | Verdict |
| --- | --- | --- | --- |
| B1 | `getShow()` n'appelle plus `paddedShowNumber()` | **136 émissions divergent** sur la clé `number` | tuable, et tué |
| B2 | `paddedShowNumber()` appliquée **deux fois** | **0 émission ne diverge** | **équivalent réel** |
| B3 | le préfixe des couvertures passe au numéro **rempli** | **1 émission diverge** : `bagage-7` | tuable, et tué |

**B2 est un mutant équivalent, et le brief de relecture le supposait tuable.**
Il ne l'est plus depuis le commit `b8e1a9e1` : `paddedShowNumber()` découpe les
chiffres de tête par expression régulière et est donc **idempotente**. Écrire un
test contre B2 reviendrait à tenir une différence qui n'existe pas. Mesuré, pas
argumenté : le relevé sur les 367 émissions est identique octet pour octet.

**B3 n'est observable que sur `bagage-7`, et il fallait le chercher.** Sur les
367 dossiers, 7 portent plusieurs couvertures ; 4 d'entre eux ont un numéro dont
les formes brute et remplie diffèrent ; et un seul — `bagage-7` — porte **aussi
la couverture d'une autre émission** (`ouiedire_bagage-6_cover-2.png`). Avec le
préfixe brut, la sienne est reconnue et passe devant. Avec un préfixe rempli,
plus rien ne la distingue, et l'étrangère gagne au tri alphabétique — donc en
`og:image` et dans le flux RSS, qui lisent `covers[0]` sans garde. Sur les six
autres dossiers, la mutation ne change rien.

### La forme retenue : un test, pas un script sous `bin/`

Les deux options étaient ouvertes. **Retenu : un test**,
`src/tests/BootstrapShowTest.php`, appuyé sur
`src/tests/support/probe-bootstrap.php`.

- **Un script sous `bin/` aurait le défaut qu'on cherche à corriger** : son seul
  juge resterait quelqu'un qui pense à le lancer. Le versionner rend le script
  relisible, pas la vérification obligatoire.
- **Le test tourne avec la commande déjà documentée**, `vendor/bin/phpunit`. Il
  n'y a rien de nouveau à retenir.

**Le risque d'écriture signalé en relecture a été mesuré, et il ne se réalise
pas ici.** Il venait de l'idée d'un *dossier de fixtures* : `$pathPublic` est
codé en dur dans `getShow()`, donc un tel dossier devrait être posé **dans
l'arbre versionné des émissions**. Le test ne pose rien. Il lit l'archive
réelle, et `getShow()` n'écrit pas : elle ouvre `index.json` en lecture et
balaye le dossier avec `Finder`. Vérifié par ailleurs que `bootstrap.php`
n'appelle aucun `->run()` à l'inclusion — c'est `src/public/index.php` qui le
fait — donc l'inclure ne sert aucune requête.

**Le relevé tourne dans un processus fils**, par le procédé de
`CoverageCheckTest::runCli()`. Charger `bootstrap.php` dans le processus de la
suite y poserait un `$app` Silex, une locale et une trentaine de fonctions
globales, pour tous les autres tests. Le prix est que **pcov ne voit pas ce
processus** : `bootstrap.php` reste à `0.00 % (0/492)` de couverture de ligne.
C'est dit plutôt que masqué — la garantie de ce fichier est de mutation.

Le test refuse aussi la moindre ligne sur `stderr` du relevé : un avertissement
PHP sur le chemin de `getShow()` est un défaut, et il ne doit pas se perdre dans
un relevé par ailleurs vert.

**Commande** : celle de la suite. Elle est déclarée dans `CLAUDE.local.md`,
section `## Testing (ce dépôt)`, avec un paragraphe sur ce que ce harnais est
seul à tenir.

### Conformité au spec, exigence par exigence

Ce qui suit oppose `specs/emission-contenu/spec.md` à ce que le dépôt tient
réellement. Un test nommé, une mesure, ou rien.

#### Requirement: Découverte du fichier audio

| Énoncé | Ce qui le tient |
| --- | --- |
| tout fichier au format attendu est l'audio, quel que soit son nom | `AudioTest::testTrouveUnFichierAuNomLibre`, `::testLExtensionEstReconnueQuelleQueSoitLaCasse` |
| ne pas exiger un nom dérivé du titre / des auteurices / du type / du numéro | `AudioTest::testTrouveUnFichierAuNomLibre` (aucun préfixe dans le nom retenu) ; et le retrait de `slugDownload`, vérifié Task 6 |
| chercher **séparément** chaque format | `AudioTest::testDistingueLesFormats`, `::testNeTrouveRienQuandLeDossierNaPasCeFormat` ; `AudioDownloadsTest::testLesDeuxFormatsNeSontPasPermutes` |
| plusieurs fichiers d'un même format : la convention d'abord, puis l'alphabétique, dans chaque groupe | `::testLaConventionPasseDevant`, `::testAlphabetiqueDepartageDansChaqueGroupe`, `::testDeuxFichiersConformesSontDepartagesParLesOctets`, `::testLaConventionDuneAutreEmissionNeGagnePas`, `::testUnFichierQuiMentionneLaConventionSansCommencerParElleNePassePasDevant` |

| Scénario | Ce qui le tient |
| --- | --- |
| Audio déposé sous un nom libre | `AudioTest::testTrouveUnFichierAuNomLibre` + `AudioDownloadsTest::testLUrlEncodeLeNomRetenu` (le nom libre produit bien une URL de téléchargement) |
| Dossier comportant plusieurs fichiers d'un même format | `AudioTest::testLaConventionPasseDevant` |
| Même émission servie depuis deux environnements | `AudioTest::testLOrdreRetenuEstCeluiDesOctetsPasCeluiDeLaCollation`, avec son garde-fou `exigeUneCollationQuiDiffereDesOctets()` — c'est le seul scénario du spec dont le témoin est une **collation** et non un jeu de fichiers |

**Réserve, et elle est réelle :** l'énoncé « chercher séparément chaque format »
est tenu **par le code de découverte**, pas par ce que la page offre. Voir
l'observation sur le gabarit, ci-dessous.

#### Requirement: Nom canonique au téléchargement

| Énoncé | Ce qui le tient |
| --- | --- |
| nom canonique dérivé du type, du numéro, des auteurices et du titre | `AudioDownloadsTest::testLeNomCanoniquePorteLeNumeroRempli`, `::testLeNumeroArriveNonSlugifie`, `::testLesArgumentsDuNomCanoniqueNeSontPasPermutables` |
| indépendant du nom du fichier stocké | `AudioTest::testLeNomCanoniqueNeDependPasDuFichierStocke` |
| suit une correction du titre ou des auteurices | `ApplyAudioDownloadsTest::testLeNumeroSeLitDansLEmissionEtNEstPasSlugifie` et la fusion `applyAudioDownloads()`, qui lit les slugs **à chaque appel** — le nom n'est jamais mémorisé |

| Scénario | Ce qui le tient |
| --- | --- |
| Téléchargement d'un fichier au nom libre | L'attribut `download` du gabarit, vérifié dans le HTML rendu (Task 6, Step 4) — **pas** l'enregistrement effectif sur le disque du visiteur |
| Téléchargement après correction du titre | Même chose, plus la relecture du chemin d'appel |

**Ce qui ne tient pas, et le dire est le point :** `tasks.md` 3.3 reste décochée.
L'attribut `download` est vérifié dans le HTML ; qu'un navigateur enregistre
réellement le fichier sous ce nom n'a été observé par personne dans ce dépôt.
C'est une vérification manuelle, et elle n'a pas eu lieu.

#### Requirement: Aucune publication sans audio (MODIFIED)

| Énoncé | Ce qui le tient |
| --- | --- |
| ne pas rendre visible une émission sans audio, quel que soit `isPublic` | `ApplyAudioDownloadsTest::testUneEmissionPublieeSansAudioEstDepubliee` |
| ne dégrade que, ne promeut jamais | `ApplyAudioDownloadsTest::testUneEmissionNonPublieeAvecAudioResteNonPubliee` face à `::testUneEmissionPublieeSansAudioEstDepubliee` — les deux sens, chacun son test |
| un dossier contenant un fichier au format attendu n'est pas « dépourvu d'audio » | `AudioDownloadsTest::testUnMp3SeulSuffitAPublier`, `::testUnFlacSeulSuffitAPublier` ; et `::testUnLienCasseAbandonneLeNom` pour la limite |
| corriger le titre ou les auteurices ne retire pas du public | **Aucun test direct.** Le raisonnement tient : `hasAudio` ne dépend plus d'aucun slug depuis que `findAudioFile()` balaye. Il est **structurel**, pas mesuré par un cas nommé. |

| Scénario | Ce qui le tient |
| --- | --- |
| Émission complète mais sans audio | `AudioDownloadsTest::testSansAucunAudioRienNEstPublie` + `ApplyAudioDownloadsTest::testUneEmissionPublieeSansAudioEstDepubliee` |
| Dépôt de l'audio | `ApplyAudioDownloadsTest::testUneEmissionPublieeAvecAudioRestePubliee` |
| Correction du titre d'une émission publiée | **Rien de nommé.** Voir ci-dessus : la garantie est structurelle. Un test qui appellerait `applyAudioDownloads()` deux fois avec deux titres et vérifierait `isPublic` inchangé la rendrait opposable — il n'existe pas. |
| Correction des auteurices d'une émission publiée | Idem. |

**C'est le trou le plus net de ce bilan**, et c'est celui du scénario qui
**motive** le change. Il n'est pas dangereux — la dépendance au slug a été
retirée, pas contournée — mais il n'est tenu par aucun test nommé, et un
relecteur a le droit de l'exiger.

### Constat de relecture, antérieur à ce change : le gabarit n'offre qu'un format, et aucun sur mobile

`src/views/emission.html.twig`, lignes 58-62 :

```twig
{% if show.urlDownloadFlac %}
    <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadFlac }}" download="…flac" …>
{% elseif show.urlDownloadMp3 %}
    <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadMp3 }}" download="…mp3" …>
{% endif %}
```

Deux faits mesurés :

1. **`{% if %} / {% elseif %}` :** une émission qui porte les deux formats
   n'offre **que le FLAC**. Le MP3 n'a pas de lien de téléchargement.
2. **`hide-on-mobile` :** dans
   `src/public/assets/css/unsemantic-grid-responsive.css` (celle que
   `layout.default.html.twig` charge, variante `-tablet`), la règle est
   `display: none !important;` sous la requête média mobile. **Aucun
   téléchargement n'est offert sur mobile**, dans aucun format.

Le spec dit pourtant : « un fichier compressé et un fichier sans perte sont
**deux téléchargements distincts, pas deux candidats pour un même rôle** ». Le
code de découverte l'honore — `findAudioFile()` est appelée une fois par format,
et `audioDownloads()` rend les deux URL. Le gabarit ne l'honore pas.

**C'est antérieur à ce change** : la structure `if/elseif` et `hide-on-mobile`
existaient avant, la Task 6 n'a fait qu'ajouter l'attribut `download` dans les
deux branches. **Consigné, pas corrigé** — le corriger serait un autre change,
et il aurait à trancher ce que la page doit montrer sur mobile.

**Second constat de la même famille, également antérieur :** `show.urlDownload`
— sans suffixe de format — est initialisée à `null` dans `getShow()` et
**jamais assignée**, avant comme après ce change (vérifié sur
`git show <merge-base>:src/src/bootstrap.php`). Elle est pourtant lue par
`emission.html.twig:129` (`window.MejsOuiedireDownloadUrl`), par
`embed.html.twig:21` et `:25` (la source du lecteur embarqué), et par
`bootstrap.php:668` (`urlencode()`). Consigné, pas corrigé.

---

## Correctif post-Task 6 : le préfixe de convention se bâtissait sur le numéro brut

**Le défaut.** `getShow()` remplit le numéro à trois chiffres **après** le bloc
audio. Le préfixe de la convention était donc bâti sur `1` quand le fichier sur
le disque porte `001` :

```
préfixe construit  : ouiedire_ailleurs-1_
fichier sur disque : ouiedire_ailleurs-001_dj-damie-boy_sans-thme.mp3
```

Mesuré avant correctif : **231 des 367 émissions** voyaient leur audio reconnu
comme conforme. Sur les **136 autres — 37 % du fonds** — la règle « la
convention passe devant », cœur de la Task 2 et défendue par onze mutations,
était **inerte**. Rien n'était cassé pour autant : un seul audio par dossier,
donc le repli alphabétique trouvait le fichier. C'est la garantie qui manquait,
pas le résultat.

### Trois retouches apportées en relecture

1. **`paddedShowNumber()` ne compare plus « `$number < 10` ».** Cette comparaison
   compare des **nombres** sous PHP 7.4 et des **chaînes** sous PHP 8 : à la
   montée de version, `17bis` aurait cessé d'être rempli, sans un mot. Le
   découpage par expression régulière n'en dépend pas — et il rend la fonction
   **idempotente**, ce qui compte depuis qu'elle est appelée à deux endroits
   (`getShow()` et `audioDownloads()`) : l'ancienne forme rendait `'007'` →
   `'00007'`. Cinq mutations appliquées sur la nouvelle, cinq mortes. Le témoin
   de la divergence PHP 8 n'est pas constructible ici, l'image étant épinglée à
   7.4 : le motif est au docblock, et le test le **dit** plutôt que de prétendre
   le montrer.
2. **La garde `if ($paddedNumber !== $number)` est retirée.** Un préfixe répété
   n'est observable par rien, donc aucun test ne peut tuer la ligne qui l'évite —
   exactement le raisonnement qui a fait retirer le `break`. Appliquer le principe
   à une ligne et pas à l'autre n'était pas tenable.
3. **Le commentaire qui justifiait le préfixe brut disait faux.** Il invoquait
   « le dossier et les couvertures » comme s'ils étaient une preuve sur le nommage
   des **audios**. Mesure : **aucun** des 367 audios n'emploie la forme brute là
   où les deux diffèrent. Le préfixe brut est une **provision** pour un dépôt
   futur sous cette forme, pas un constat — et c'est ce que le commentaire dit
   maintenant.

Comportement inchangé sur les 367 émissions après ces retouches, vérifié par
instantané (`md5` et `cmp` — voir l'avertissement sur `diff`, ci-dessous).

**Avertissement d'outillage, valable au-delà de ce change.** `diff` et `git`
sont proxifiés dans cet environnement et leur sortie est réécrite. Mesuré :
`diff` a rendu `rc=0` et « Files are identical » sur deux fichiers de `md5`
différents, et `git status --porcelain` a rapporté un fichier modifié sur un
arbre propre. Ce n'est pas déterministe. Toute vérification passe par
`/usr/bin/diff`, `cmp -s` ou `md5`.

### Ce qui a été écarté, et pourquoi

**Déplacer le remplissage avant le bloc audio.** Vérifié : les dossiers de
l'archive sont nommés **sans** remplissage (`assets/emission/ailleurs-1`), et
`$urlAssets` est bâti sur ce numéro brut. Le déplacer casserait les URL
d'assets des 136 émissions concernées — couvertures comprises.

**Toucher au préfixe des couvertures.** Vérifié : les images suivent la forme
**brute** (`ouiedire_ailleurs-1_cover-1.png`). L'archive est incohérente avec
elle-même — dossier brut, couverture brute, audio rempli — et c'est un fait à
constater, pas à normaliser ici.

### La forme retenue : deux préfixes acceptés, pas une normalisation

Les deux options rendent **exactement le même résultat sur l'archive
d'aujourd'hui** (367/367, mesuré plus bas) : aucune émission ne porte un audio
au numéro brut là où brut et rempli diffèrent. L'équivalence est donc réelle sur
les données, et c'est précisément pourquoi elle ne tranche rien.

Ce qui tranche est ce que chaque option **garantit demain**. Normaliser au seul
numéro rempli échangerait un angle mort contre l'autre : un audio déposé au
numéro brut — la forme que le dossier et la couverture de la même émission
emploient — cesserait d'être reconnu, sans un mot. Accepter les deux formes n'en
retire aucune. Le coût est un `strpos` de plus par fichier, et une ligne de
construction.

`AudioDownloadsTest::testLaConventionResteReconnueSurLeNumeroBrut` existe pour
ça : il échoue sur la normalisation (mutant M12 ci-dessous), et c'est le seul
endroit où ce choix est opposable.

### La règle de remplissage devient une fonction, lue au même endroit par les deux

Le défaut n'est pas un oubli, c'est une **duplication implicite** : la règle
d'affichage vivait dans `getShow()`, et le bloc audio en avait besoin sans
l'avoir. Elle est extraite dans `audio.php`, où la suite l'atteint et où la
jauge la mesure — et `getShow()` l'y lit désormais aussi.

```php
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
    if ($number < 10) {
        return '00'.$number;
    }
    if ($number < 100) {
        return '0'.$number;
    }

    return $number;
}
```

Les comparaisons restent **lâches**, comme dans le `getShow()` d'où elles
viennent : sous PHP 7.4, `'17bis'` vaut 17 face à un entier, donc `ailleurs-17bis`
rend `017bis` — la forme exacte de son mp3
(`ouiedire_ailleurs-017bis_dj-gum_rebondir.mp3`). Vérifié plutôt que supposé, par
`AudioTest::testLeRemplissageNeTronquePasUnNumeroNonNumerique`. Sur PHP 8 la
comparaison changerait de sens ; ce dépôt est en 7.4, et reproduire la règle
existante à l'identique était l'objectif.

### Le code

`audioDownloads()` construit les deux formes :

```php
    // Les deux formes du numero, a egalite. La brute est celle du dossier et
    // des couvertures ; la remplie est celle des 367 audios de l'archive, sans
    // exception. Le `if` evite un doublon quand elles coincident (numeros >= 100).
    $paddedNumber = paddedShowNumber($number);
    $conventionPrefixes = array(sprintf('ouiedire_%s-%s_', $typeSlug, $number));
    if ($paddedNumber !== $number) {
        $conventionPrefixes[] = sprintf('ouiedire_%s-%s_', $typeSlug, $paddedNumber);
    }
    $nameMp3 = findAudioFile($directory, 'mp3', $conventionPrefixes);
    $nameFlac = findAudioFile($directory, 'flac', $conventionPrefixes);
```

`findAudioFile()` les traite à égalité :

```php
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
```

Pas de `break` dans cette boucle : sur un tableau de deux préfixes il ne gagne
rien d'observable, et il a **survécu** comme mutant (M6). Une ligne qu'aucun test
ne peut tuer ne reste pas.

`getShow()` ne porte plus la règle, seulement son appel — et il reste **après**
le bloc audio, pour la raison écartée plus haut :

```php
    // Pretty show number. La regle vit dans audio.php, ou la suite l'atteint et
    // ou le bloc audio ci-dessus la lit deja pour batir le prefixe de la
    // convention. Deux exemplaires ont diverge une fois : le prefixe se
    // construisait sur « 1 » quand le fichier portait « 001 ».
    // Elle reste APRES le bloc audio : $urlAssets et les dossiers de l'archive
    // sont nommes au numero brut.
    $show['id'] = $show['number'];
    $show['number'] = paddedShowNumber($show['id']);
```

### La seconde moitié : le nom canonique de téléchargement

**Mesuré :** 136 des 367 émissions ont un numéro dont la forme brute et la forme
remplie diffèrent — les mêmes 136. Pour elles, le nom canonique disait
`ouiedire_ailleurs-1_…` alors que la page affiche `001` et que le fichier stocké
s'appelle `ouiedire_ailleurs-001_…`.

| Option | Ce qu'elle coûte |
| --- | --- |
| **Garder le brut** | Gratuit, aucun test à changer. Mais depuis la Task 6 ce nom atterrit sur le disque de qui télécharge : 136 fichiers y arriveraient sous un numéro que ni la page, ni le fichier d'origine, ni le reste de l'archive n'emploient. |
| **Remplir** *(retenu)* | Deux attentes de test à mettre à jour, et l'instantané bouge sur 136 émissions. En échange le nom téléchargé dit ce que la page affiche, et **coïncide avec le fichier stocké sur les 367**. |

Tranché : **remplir**. Ce n'était pas une régression de la Task 6 —
`slugDownload` faisait déjà pareil — mais ce n'est une raison de le garder que
tant que le nom ne sort pas du serveur, et depuis la Task 6 il en sort.

Le remplissage se fait **au point d'appel**, dans `audioDownloads()`, pas dans
`canonicalDownloadName()`, dont le contrat reste « assemble et met en minuscules,
ne slugifie pas ». Les deux attentes mises à jour
(`AudioDownloadsTest::testLeNumeroArriveNonSlugifie`,
`ApplyAudioDownloadsTest::testLeNumeroSeLitDansLEmissionEtNEstPasSlugifie`) ne
perdent rien de ce qu'elles tenaient : l'espace et la casse de `'17 BIS'` les
traversent toujours intacts, et un `slugify()` ajouté à la couture les fait
toujours échouer.

### La suite et la jauge

```
OK (73 tests, 118 assertions)
/app/src/src/audio.php : 100.00 % (54/54), seuil 90.00 %
```

Contre `OK (65 tests, 103 assertions)` et `100.00 % (42/42)` avant le correctif :
huit tests de plus, douze instructions de plus, toutes couvertes.

### Table de mutation

Chaque mutation a été **réellement appliquée** au fichier, la suite relancée,
puis le fichier restauré. Rien n'est argumenté depuis le fauteuil.

| # | Mutation | Résultat | Tué par |
| --- | --- | --- | --- |
| M1 | `paddedShowNumber` : `'00'.` devient `'0'.` | 3 échecs | `AudioTest::testLeNumeroEstRempliATroisChiffres` |
| M2 | `paddedShowNumber` : `< 10` devient `<= 10` | **survivant**, puis 1 échec | `testLeNumeroEstRempliATroisChiffres`, **après ajout des bornes 9/10 et 99/100** |
| M2b | `paddedShowNumber` : `< 100` devient `<= 100` | 1 échec | `testLeNumeroEstRempliATroisChiffres` |
| M3 | `paddedShowNumber` : `< 100` devient `< 1000` | 5 échecs | `testLeNumeroEstRempliATroisChiffres` + 4 |
| M4 | `paddedShowNumber` : les deux remplissages permutés | 7 échecs | `testLaConventionEstReconnueSurLeNumeroRempli` + 6 |
| M5 | `paddedShowNumber` : ne remplit plus rien | 7 échecs | `testLeNomCanoniquePorteLeNumeroRempli` + 6 |
| M6 | `findAudioFile` : le `break` disparaît | **survivant** | aucun — **équivalence réelle**, le `break` a donc été retiré du code |
| M7 | `findAudioFile` : plus rien n'est conforme | 10 échecs | `testLaConventionPasseDevantLeNomLibre` + 9 |
| M8 | `findAudioFile` : seul le premier préfixe compte | 3 échecs | `AudioTest::testLesDeuxFormesDuNumeroSontReconnues` + 2 |
| M9 | `findAudioFile` : `=== 0` devient `!== false` | 1 échec | `testUnFichierQuiMentionneLaConventionSansCommencerParElleNePassePasDevant` |
| M10 | `audioDownloads` : le préfixe rempli n'est plus ajouté *(= le défaut d'origine)* | 2 échecs | `testLaConventionEstReconnueSurLeNumeroRempli`, `testLeNumeroNonNumeriqueEstRempliLuiAussi` |
| M11 | `audioDownloads` : `!==` devient `!=` | 1 échec | `testLaConventionEstReconnueSurLeNumeroRempli` |
| M12 | `audioDownloads` : seule la forme remplie est acceptée *(= la normalisation écartée)* | 2 échecs | `testLaConventionResteReconnueSurLeNumeroBrut` + 1 |
| M13 | `audioDownloads` : le nom canonique reprend le numéro brut | 4 échecs | `testLeNomCanoniquePorteLeNumeroRempli` + 3 |
| M14 | `bootstrap` : `getShow()` n'appelle plus le remplissage | **survivant à la suite** | l'instantané `var_export()` : 136 lignes divergentes, sur la clé `number` |

**M14 n'est pas tenu par la suite**, et le dire est le seul traitement honnête :
`bootstrap.php` n'est pas amorcé par PHPUnit — c'est le constat de la Task 5, pas
une nouveauté. Son juge est l'instantané, et il le tue.

**Deux mutants ont d'abord survécu, et trois tests neufs passaient pour une
raison qui n'était pas la leur** : écrits avec `zzz.mp3` comme témoin non
conforme, le repli alphabétique rendait déjà le bon fichier (`o` < `z`).
Corrigés en `aaa.mp3`, ils sont passés au rouge — c'est-à-dire qu'ils se sont mis
à tenir ce que leur nom annonce. Le rouge relevé après correction des témoins :

```
Tests: 73, Assertions: 107, Errors: 4, Failures: 3.
```

### La mesure, rejouable

Le script n'est pas versionné ; le voici en entier. Il appelle le code de
production — `paddedShowNumber()` et `slugify()` — plutôt que de le
réimplémenter, et refait le passage par le type « joli » que `getShow()`
interpose : `slugify('Ouïedire')` rend `ouedire`, pas `ouiedire`. Une première
version de ce script l'ignorait et rapportait six faux non-conformes.

```php
<?php
/**
 * Combien d'emissions voient leur fichier audio reconnu comme conforme a la
 * convention ? La mesure appelle le code de production — paddedShowNumber() et
 * slugify() — plutot que de le reimplementer, et refait le passage par le type
 * « joli » que getShow() interpose entre le nom du dossier et le prefixe :
 * slugify('Ouiedire') rend « ouedire », pas « ouiedire ».
 *
 * Usage : php mesure-convention.php [brut|rempli|les-deux]
 *   brut     : le prefixe d'avant le correctif, sur le numero du dossier
 *   rempli   : le prefixe sur le seul numero a trois chiffres
 *   les-deux : ce que le correctif applique (defaut)
 */
$debug = false;
require '/app/src/src/bootstrap.php';

$mode = isset($argv[1]) ? $argv[1] : 'les-deux';
$base = '/app/src/public/assets/emission';
$total = 0; $avecAudio = 0; $conforme = 0; $nonConformes = array();
foreach (scandir($base) as $dir) {
    if ($dir[0] === '.' || !is_dir($base.'/'.$dir)) { continue; }
    $total++;
    $pos = strrpos($dir, '-');
    $type = substr($dir, 0, $pos);
    $number = substr($dir, $pos + 1);

    // Le type « joli » de getShow(), puis slugify() : c'est ce qui entre dans
    // le prefixe, et ce n'est pas le segment du dossier.
    if ($type === 'ailleurs') { $type = 'Ailleurs'; }
    elseif ($type === 'bagage') { $type = 'Bagage'; }
    elseif ($type === 'bureau') { $type = 'Bureau'; }
    else { $type = 'Ouïedire'; }
    $typeSlug = slugify($type);

    $padded = paddedShowNumber($number);
    $prefixes = array();
    if ($mode !== 'rempli') { $prefixes[] = sprintf('ouiedire_%s-%s_', $typeSlug, $number); }
    if ($mode !== 'brut') { $prefixes[] = sprintf('ouiedire_%s-%s_', $typeSlug, $padded); }

    $audios = array();
    foreach (scandir($base.'/'.$dir) as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if ($ext === 'mp3' || $ext === 'flac') { $audios[] = $f; }
    }
    if (!$audios) { continue; }
    $avecAudio++;

    $ok = false;
    foreach ($audios as $f) {
        foreach ($prefixes as $prefix) {
            if (strpos($f, $prefix) === 0) { $ok = true; }
        }
    }
    if ($ok) { $conforme++; } else { $nonConformes[] = $dir.' => '.implode(', ', $audios); }
}
printf("mode                                : %s\n", $mode);
printf("dossiers d'emission                 : %d\n", $total);
printf("dossiers portant au moins un audio  : %d\n", $avecAudio);
printf("audio reconnu comme conforme        : %d\n", $conforme);
printf("audio NON reconnu                   : %d\n", count($nonConformes));
foreach ($nonConformes as $l) { echo "  - $l\n"; }
```

```bash
for m in brut rempli les-deux; do
  docker run --rm -v .:/app -w /app/src ouiedire-test \
    php /app/tmp-mesure/mesure-convention.php $m
done
```

| Mode | Audio reconnu comme conforme |
| --- | --- |
| `brut` — le préfixe d'avant le correctif | **231 / 367** (63 %) |
| `rempli` — la normalisation écartée | 367 / 367 |
| `les-deux` — ce que le correctif applique | **367 / 367** (100 %) |

**136 émissions réparées**, et la garantie couvre désormais le fonds entier.

### L'instantané `var_export()` sur les 367 émissions

Procédé de la Task 5, Step 7, à l'identique. Ici il **doit** bouger.

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test \
  php /app/tmp-mesure/snapshot.php > avant.txt    # avant le correctif
# … correctif …
docker run --rm -v .:/app -w /app/src ouiedire-test \
  php /app/tmp-mesure/snapshot.php > apres.txt
/usr/bin/diff avant.txt apres.txt
```

Mesuré : **544 lignes de diff, 136 lignes `<` et 136 lignes `>`, toutes sur la
clé `canonicalDownloadName`** — vérifié par comptage, pas à l'œil :

```
$ grep -c "^<     'canonicalDownloadName'" d.txt   →  136
$ grep -c '^< ' d.txt                              →  136
```

Une seule clé bouge, sur exactement les 136 émissions dont le numéro se remplit,
`ailleurs-17bis` comprise :

```
<     'canonicalDownloadName' => 'ouiedire_ailleurs-1_dj-damie-boy_sans-thme',
>     'canonicalDownloadName' => 'ouiedire_ailleurs-001_dj-damie-boy_sans-thme',
<     'canonicalDownloadName' => 'ouiedire_ailleurs-17bis_dj-gum_rebondir',
>     'canonicalDownloadName' => 'ouiedire_ailleurs-017bis_dj-gum_rebondir',
```

**`urlDownloadMp3` ne bouge pas, et c'est le point.** Le fichier retenu est le
même qu'avant : il tombait dans le groupe non conforme et le tri alphabétique le
rendait quand même, un seul audio par dossier. Ce que le correctif change n'est
pas le résultat d'aujourd'hui, c'est la **raison** pour laquelle il est juste —
la convention, et non le hasard d'un tri sur un dossier à un seul candidat.

`stderr` vide dans les deux relevés.

> **Piège rencontré, à noter :** dans cet environnement, `diff` est intercepté
> par un proxy qui a rapporté `[ok] Files are identical` sur deux fichiers de
> sommes MD5 différentes. `cmp` a démenti. Les relevés ci-dessus emploient
> `/usr/bin/diff` explicitement.

### Non-régression HTTP

`src/cache` vidé **avant chaque relevé** — l'application monte un cache HTTP
disque, et une seconde requête sur la même URL est servie du cache.

```bash
docker run -d --rm --name ouiedire-http -v .:/app -w /app/src/public \
  -p 18080:8080 ouiedire-test php -S 0.0.0.0:8080 index.php
for u in / /artists /feed /emission/ailleurs-331 /emission/ailleurs-1 /emission/ailleurs-17bis; do
  rm -rf src/cache/*
  curl -s -o /tmp/p.html -w "%{http_code}  $u   " "http://localhost:18080$u"
  grep -oE 'download="[^"]*"' /tmp/p.html | head -1
done
```

```
200  /
200  /artists
200  /feed
200  /emission/ailleurs-331   download="ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3"
200  /emission/ailleurs-1     download="ouiedire_ailleurs-001_dj-damie-boy_sans-thme.mp3"
200  /emission/ailleurs-17bis download="ouiedire_ailleurs-017bis_dj-gum_rebondir.mp3"
```

Les deux numéros remplis servent l'attribut `download` sous la forme que la page
affiche, et **identique au fichier stocké** dans les deux cas.
