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
  sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && composer update --no-interaction"
```

Attendu : `phpunit/phpunit (9.6.x)` dans la sortie, `src/vendor/bin/phpunit` présent.

- [ ] **Step 3: Créer l'image de test avec pilote de couverture**

`docker/php-test.Dockerfile` :

```dockerfile
FROM php:7.4-cli
RUN pecl install pcov && docker-php-ext-enable pcov
WORKDIR /app/src
```

```bash
docker build -t ouiedire-test -f docker/php-test.Dockerfile .
```

Attendu : `Successfully tagged ouiedire-test:latest`.

- [ ] **Step 4: Configurer PHPUnit**

`src/phpunit.xml` :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="ouiedire">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <file>src/audio.php</file>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 5: Écrire un test trivial pour valider la chaîne**

`src/tests/SmokeTest.php` :

```php
<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testLaChaineDeTestFonctionne()
    {
        $this->assertTrue(true);
    }
}
```

- [ ] **Step 6: Lancer**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit
```

Attendu : `OK (1 test, 1 assertion)`.

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

## Task 2: Découverte d'un fichier audio

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

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/ouiedire-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
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
}
```

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
        if ($conventionPrefix !== '' && strpos($name, $conventionPrefix) === 0) {
            $conventional[] = $name;
        } else {
            $others[] = $name;
        }
    }

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

Attendu : `OK (4 tests, 4 assertions)`.

- [ ] **Step 5: Commit**

```bash
git add src/src/audio.php src/tests/AudioTest.php
git commit -m "feat: decouvre le fichier audio par balayage du dossier"
```

---

## Task 3: Ordre déterministe

**Files:**
- Modify: `src/tests/AudioTest.php`

- [ ] **Step 1: Écrire les tests d'ordre**

Ajouter dans `src/tests/AudioTest.php`, avant l'accolade fermante de la classe :

```php
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

    public function testLaConventionDuneAutreEmissionNeGagnePas()
    {
        $this->touchFiles(['ouiedire_ailleurs-182_dj_titre.mp3', 'ouiedire_ailleurs-331_dj_titre.mp3']);

        $this->assertSame(
            'ouiedire_ailleurs-331_dj_titre.mp3',
            findAudioFile($this->dir, 'mp3', 'ouiedire_ailleurs-331_')
        );
    }
```

- [ ] **Step 2: Lancer**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --filter AudioTest
```

Attendu : `OK (7 tests, 7 assertions)`. Le tri de la Task 2 les satisfait déjà — c'est voulu, ces tests figent le comportement plutôt que de le découvrir.

- [ ] **Step 3: Vérifier la couverture**

```bash
docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --coverage-text
```

Attendu : `src/audio.php` à 100 %, au-dessus du seuil de 90 % de `sdr-004`.

- [ ] **Step 4: Commit**

```bash
git add src/tests/AudioTest.php
git commit -m "test: fige l'ordre de selection du fichier audio"
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

Attendu : `OK (9 tests, 9 assertions)`.

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
