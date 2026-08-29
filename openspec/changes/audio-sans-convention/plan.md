# Découverte du fichier audio par balayage — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `getShow()` cesse de calculer le nom du fichier audio d'une émission et le découvre en balayant son dossier, de sorte que corriger un titre ne dépublie plus l'émission.

**Architecture:** La découverte est extraite dans `src/src/audio.php`, deux fonctions pures sans dépendance à Silex ni à `slugify()` — le préfixe de convention leur est passé en argument. `bootstrap.php` les appelle. Cette extraction n'introduit ni port ni adaptateur : elle rend la logique testable sans monter l'application, ce que `sdr-002` (`monolithic`) n'interdit pas.

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
- Create: `bin/coverage-check`
- Create: `src/tests/CoverageCheckTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

`src/tests/CoverageCheckTest.php` :

```php
<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../bin/coverage-check';

class CoverageCheckTest extends TestCase
{
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
        $path = tempnam(sys_get_temp_dir(), 'clover').'.xml';
        file_put_contents($path, $xml);

        return $path;
    }

    public function testSeuilAtteint()
    {
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 10]]));

        $this->assertSame(0, coverageCheck($path, 'audio.php', 90.0));
    }

    public function testSeuilNonAtteint()
    {
        $path = $this->write($this->clover(['/app/src/src/audio.php' => [10, 5]]));

        $this->assertSame(1, coverageCheck($path, 'audio.php', 90.0));
    }

    public function testFichierAbsentDuRapport()
    {
        // Le cas qui compte : filtre casse, aucune entree, le controle doit echouer.
        $path = $this->write($this->clover(['/app/src/src/bootstrap.php' => [507, 0]]));

        $this->assertSame(1, coverageCheck($path, 'audio.php', 90.0));
    }

    public function testRapportIllisible()
    {
        $this->assertSame(1, coverageCheck('/nexiste/pas.xml', 'audio.php', 90.0));
    }
}
```

- [ ] **Step 2: Vérifier que ça échoue**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter CoverageCheck
```

Attendu : `Failed to open stream` sur `bin/coverage-check`.

- [ ] **Step 3: Implémenter**

`bin/coverage-check` :

```php
#!/usr/bin/env php
<?php

/**
 * Verifie la couverture d'UN fichier dans un rapport Clover.
 *
 * --coverage-text ne rend qu'un total, domine par bootstrap.php, et n'affiche
 * pas les fonctions libres. sdr-004 demande un seuil sur le code touche : il
 * faut donc lire Clover, qui donne le detail par fichier.
 *
 * Un fichier absent du rapport est un ECHEC, pas un succes : c'est ce qui
 * attrape un filtre de couverture casse.
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
        fwrite(STDERR, sprintf("Rapport illisible : %s\n", $cloverPath));

        return 1;
    }

    foreach ($xml->xpath('//file') as $file) {
        if (strpos((string) $file['name'], $needle) === false) {
            continue;
        }
        $statements = (int) $file->metrics['statements'];
        $covered = (int) $file->metrics['coveredstatements'];
        $ratio = $statements > 0 ? 100.0 * $covered / $statements : 100.0;
        printf("%s : %.2f %% (%d/%d), seuil %.2f %%\n",
            $needle, $ratio, $covered, $statements, $threshold);

        return $ratio + 1e-9 >= $threshold ? 0 : 1;
    }

    fwrite(STDERR, sprintf(
        "%s absent du rapport de couverture — filtre casse ou fichier jamais charge.\n",
        $needle
    ));

    return 1;
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

Attendu : tous les tests au vert, dont les quatre de `CoverageCheckTest`.

- [ ] **Step 5: Vérifier le contrôle de bout en bout sur le vrai rapport**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test \
  sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml >/dev/null && \
         php /app/bin/coverage-check /app/clover.xml audio.php 90"
echo "code de sortie : $?"
```

Attendu : `audio.php : 100.00 % (N/N), seuil 90.00 %` et code de sortie `0`.

- [ ] **Step 6: Déclarer le contrôle dans `CLAUDE.local.md`**

Ajouter à la section `## Testing (ce dépôt)` :

```markdown
Seuil par fichier : `docker run --rm -v .:/app -w /app/src ouiedire-test sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml >/dev/null && php /app/bin/coverage-check /app/clover.xml <fichier> 90"`

`--coverage-text` ne sert qu'à l'œil : il ne rend ni les fonctions libres ni le
détail par fichier, et son total est dominé par `bootstrap.php`. C'est
`coverage-check` qui fait foi pour le seuil de `sdr-004`.
```

- [ ] **Step 7: Commit**

```bash
git add bin/coverage-check src/tests/CoverageCheckTest.php CLAUDE.local.md
git commit -m "test: controle de couverture par fichier via Clover"
```

---

## Task 4: Nom canonique de téléchargement

**Files:**
- Modify: `src/src/audio.php`
- Modify: `src/tests/AudioTest.php`

- [ ] **Step 1: Écrire le test qui échoue**

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
        $this->assertSame(
            'ouiedire_ailleurs-331_dj_titre',
            canonicalDownloadName('Ailleurs', '331', 'DJ', 'Titre')
        );
    }
```

- [ ] **Step 2: Vérifier que ça échoue**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter canonique
```

Attendu : `Error: Call to undefined function canonicalDownloadName()`.

- [ ] **Step 3: Implémenter**

Ajouter à la fin de `src/src/audio.php` :

```php
/**
 * Nom sous lequel le public telecharge l'audio, quel que soit le nom stocke.
 *
 * Les arguments arrivent deja slugifies : cette fonction n'a pas de dependance
 * a slugify(), qui vit dans bootstrap.php.
 *
 * @return string nom sans extension
 */
function canonicalDownloadName($typeSlug, $number, $authorsSlug, $titleSlug)
{
    return strtolower(sprintf('ouiedire_%s-%s_%s_%s', $typeSlug, $number, $authorsSlug, $titleSlug));
}
```

- [ ] **Step 4: Vérifier**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
```

Attendu : `OK (19 tests, 24 assertions)` — 2 `SmokeTest` + 11 `AudioTest`
(Task 2) + 4 `CoverageCheckTest` (Task 3) + les 2 ci-dessus. Chiffre calculé,
pas mesuré : le relever à l'exécution et corriger ici s'il diffère.

- [ ] **Step 5: Commit**

```bash
git add src/src/audio.php src/tests/AudioTest.php
git commit -m "feat: nom canonique de telechargement independant du fichier stocke"
```

---

## Task 5: Brancher dans getShow()

**Files:**
- Modify: `src/src/bootstrap.php:3` (require), `:225-250` (bloc audio)

- [ ] **Step 1: Charger le nouveau fichier**

Après la ligne 3 de `src/src/bootstrap.php` (`require_once __DIR__.'/../vendor/autoload.php';`), ajouter :

```php
require_once __DIR__.'/audio.php';
```

- [ ] **Step 2: Remplacer le bloc audio**

Remplacer intégralement, dans `src/src/bootstrap.php`, depuis `// Guess show audio properties (MP3 and FLAC)` jusqu'à la ligne fermante du second `if ($fileFlac->isReadable()) { ... }` :

```php
    // Guess show audio properties (MP3 and FLAC).
    // Le nom du fichier n'est pas une donnee : on balaye le dossier, la
    // convention historique d'abord. Voir le change audio-sans-convention.
    $conventionPrefix = sprintf('ouiedire_%s-%s_', slugify($show['type']), $show['number']);
    $nameMp3 = findAudioFile($pathPublicEmission, 'mp3', $conventionPrefix);
    $nameFlac = findAudioFile($pathPublicEmission, 'flac', $conventionPrefix);

    $show['sizeDownloadMp3'] = $nameMp3
        ? round(filesize($pathPublicEmission.'/'.$nameMp3) / (1024 * 1024), 2).' Mo'
        : null;
    $show['sizeDownloadFlac'] = $nameFlac
        ? round(filesize($pathPublicEmission.'/'.$nameFlac) / (1024 * 1024), 2).' Mo'
        : null;

    $show['urlDownloadMp3'] = $nameMp3 ? sprintf('%s/%s', $urlAssets, rawurlencode($nameMp3)) : null;
    $show['urlDownloadFlac'] = $nameFlac ? sprintf('%s/%s', $urlAssets, rawurlencode($nameFlac)) : null;

    // Etiquette d'enregistrement, independante du nom du fichier stocke.
    $show['canonicalDownloadName'] = canonicalDownloadName(
        slugify($show['type']), $show['number'], slugify($show['authors']), slugify($show['title'])
    );
```

- [ ] **Step 3: Vérifier que le site répond**

```bash
docker run --rm -v .:/app -w /app/src -p 8123:80 php:7.4-cli php -S 0.0.0.0:80 -t /app/src/public &
bin/dev-audio-fixtures
curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1:8123/emission/ailleurs-331'
```

Attendu : `200`.

- [ ] **Step 4: Vérifier le scénario qui motive le change**

```bash
docker run --rm -v .:/app -w /app/src php:7.4-cli php -r '
$debug=false; require("/app/src/src/bootstrap.php");
$p="/app/src/public/assets/emission/ailleurs-331/index.json";
$d=json_decode(file_get_contents($p), true); $orig=$d["title"];
$d["title"]="La Pompa Calor Vol 3"; file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$s=getShow("ailleurs-331"); echo $s["isPublic"] ? "PUBLIEE\n" : "DEPUBLIEE\n";
$d["title"]=$orig; file_put_contents($p, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));'
```

Attendu : `PUBLIEE`. Avant ce change, la même commande affichait `DEPUBLIEE`.

- [ ] **Step 5: Commit**

```bash
git add src/src/bootstrap.php
git commit -m "feat: getShow decouvre l'audio au lieu de calculer son nom"
```

---

## Task 6: Retirer ce qui n'a plus d'objet

**Files:**
- Modify: `src/src/bootstrap.php` (`slugDownload`)
- Modify: `src/views/emission.html.twig:57-64`

- [ ] **Step 1: Supprimer `slugDownload`**

Supprimer de `src/src/bootstrap.php` la ligne :

```php
    $show['slugDownload'] = strtolower(sprintf('%s/ouiedire_%s-%s_%s_%s', $urlAssets, slugify($show['type']), $show['number'], slugify($show['authors']), slugify($show['title'])));
```

- [ ] **Step 2: Réécrire les boutons de téléchargement**

Remplacer dans `src/views/emission.html.twig` le bloc `{% if show.urlDownloadFlac %} … {% endif %}` par :

```twig
            {% if show.urlDownloadFlac %}
                <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadFlac }}" download="{{ show.canonicalDownloadName }}.flac" title="Télécharger l'émission au format FLAC ({{ show.sizeDownloadFlac }})"><i class="icon-download" style="color:#{% if app.request.cookies.get('night') %}555{% else %}fff{% endif %}"></i></a></h3>
            {% elseif show.urlDownloadMp3 %}
                <h3 class="showDownload hide-on-mobile"><a href="{{ show.urlDownloadMp3 }}" download="{{ show.canonicalDownloadName }}.mp3" title="Télécharger l'émission au format MP3 ({{ show.sizeDownloadMp3 }})"><i class="icon-download" style="color:#{% if app.request.cookies.get('night') %}555{% else %}fff{% endif %}"></i></a></h3>
            {% endif %}
```

Le troisième bouton, `title="Copier le nom de fichier attendu"`, disparaît : sans audio, aucun bouton de téléchargement n'est proposé.

- [ ] **Step 3: Vérifier qu'aucune référence ne survit**

```bash
grep -rn "slugDownload" src/ --exclude-dir=vendor
```

Attendu : aucune sortie.

- [ ] **Step 4: Vérifier l'attribut dans la page rendue**

```bash
curl -s 'http://127.0.0.1:8123/emission/ailleurs-331' | grep -o 'download="[^"]*"'
```

Attendu : `download="ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3"`.

- [ ] **Step 5: Commit**

```bash
git add src/src/bootstrap.php src/views/emission.html.twig
git commit -m "refactor: retire slugDownload et le bouton du nom attendu"
```

---

## Task 7: Documentation

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Dire que le nom du fichier est libre**

Dans `README.md`, à la suite de la phrase sur le dépôt du MP3, ajouter :

```markdown
Le nom du fichier n'a pas d'importance : le site retient le premier fichier audio
qu'il trouve dans le dossier de l'émission, et le propose au téléchargement sous
un nom construit à partir du titre et des auteurices.
```

- [ ] **Step 2: Relire**

Vérifier accents, typographie française et style — la prose du dépôt est en français (`sdr-003`, `traits.localization.language.default`).

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: le nom du fichier audio n'a plus d'importance"
```

---

## Task 8: Vérification d'ensemble et bascule

- [ ] **Step 1: Aucune émission ne change de fichier servi**

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

- [ ] **Step 2: Suite complète et couverture**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --coverage-text
```

Attendu : tous les tests au vert, `src/audio.php` au-dessus de 90 %.

- [ ] **Step 3: Valider le change**

```bash
openspec validate audio-sans-convention --type change --strict
```

Attendu : `Change 'audio-sans-convention' is valid`.

- [ ] **Step 4: Après déploiement, relever le gain réel**

```bash
curl -s https://www.ouiedire.net/ | grep -oE 'emission/[a-z]+-[0-9a-z]+' | sed 's#emission/##' | sort -u > /tmp/apres.txt
for s in ailleurs-97 ailleurs-115 ailleurs-234 ailleurs-304 ailleurs-316; do
  grep -qx "$s" /tmp/apres.txt && echo "  $s : REAPPARUE" || echo "  $s : toujours absente"
done
```

Chaque `RÉAPPARUE` est une émission dont l'audio existait sous un autre nom. Les absentes n'ont jamais eu de fichier — ce change n'y peut rien, et c'est la mesure qui le dit.
