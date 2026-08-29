## Testing (ce dépôt)

Installation des dépendances (`src/vendor` est ignoré par git, c'est le seul moyen de reconstituer l'environnement) :

```bash
docker run --rm -v .:/app -w /app/src php:7.4-cli \
  sh -c "apt-get update -qq && apt-get install -y -qq unzip && \
         curl -sS https://getcomposer.org/installer | php -- \
           --install-dir=/usr/local/bin --filename=composer --version=2.8.12 && \
         composer install --no-interaction"
```

Deux détails sans lesquels l'installation échoue :

- Composer est épinglé à **2.8.12** : à partir de 2.9, Composer refuse par défaut les paquets visés par un avis de sécurité (`policy.advisories.block`), et ce dépôt en compte 23 — suivis à part dans le change `dependances-vulnerables`.
- `apt-get install unzip` est nécessaire : l'image `php:7.4-cli` ne fournit ni `unzip` ni `git`, et Composer ne peut donc extraire aucune archive.

Runner : PHPUnit 9.6
Image : `docker build -t ouiedire-test -f docker/php-test.Dockerfile .`
Commande : `docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit`
Couverture : `docker run --rm -v .:/app -w /app/src ouiedire-test vendor/bin/phpunit --coverage-text`
Sortie attendue : `OK (N tests, M assertions)`

La suite exige l'extension **pcov** : `SmokeTest` échoue volontairement sur une image `php:7.4-cli` nue. Le runner documenté est l'image `ouiedire-test`, et un pilote de couverture absent doit se voir plutôt que se taire.

Seuil par fichier : `docker run --rm -v .:/app -w /app/src ouiedire-test sh -c "vendor/bin/phpunit --coverage-clover /app/clover.xml 2>/dev/null && php /app/bin/coverage-check.php /app/clover.xml <fichier> 90"`

### `bootstrap.php` : le harnais de `BootstrapShowTest`

`bootstrap.php` n'est amorcé par aucun autre test, et sa couverture de ligne
vaut `0.00 % (0/492)` — mesuré. Ce n'est pas un oubli : le seul harnais qui le
juge, `src/tests/BootstrapShowTest.php`, lance
`src/tests/support/probe-bootstrap.php` dans un **processus fils**, que le
pilote de couverture ne voit pas. Charger `bootstrap.php` dans le processus de
la suite y poserait un `$app` Silex, une locale et une trentaine de fonctions
globales, pour tous les autres tests.

Sa garantie est donc **de mutation**, pas de couverture. Deux mutations
survivaient à la suite entière avant ce harnais, et il les tue : supprimer
l'appel à `paddedShowNumber()` dans `getShow()` (136 émissions divergent), et
bâtir le préfixe des couvertures sur le numéro rempli (`bagage-7` diverge). La
table est dans `openspec/changes/audio-sans-convention/plan.md`, section
« Ce que la relecture a ajouté au périmètre ».

**Ce harnais lit l'archive réelle**, `src/public/assets/emission`, parce que
`$pathPublic` est codé en dur dans `getShow()` : il n'y a aucun dossier de
fixtures à lui substituer. Il ne pose et ne modifie **rien** — `getShow()` ouvre
`index.json` en lecture et balaye le dossier. Ne pas transformer ce harnais en
un test qui écrit ses propres fixtures : elles atterriraient dans l'arbre
versionné des émissions.

Commande : celle de la suite, ci-dessus. Rien de particulier à lancer.

### Fixtures audio en local

Aucun fichier audio réel n'est versionné (`.gitignore` : `*.mp3`) ; ils vivent
sur Nextcloud. `bin/dev-audio-fixtures` pose des MP3 **vides** sous le nom
canonique que l'application calcule elle-même, sans quoi aucune émission n'est
publique en local et les pages tombent en 500.

Conséquence à ne pas oublier en mesurant : sur un arbre garni de ces fixtures,
**toute émission est servie et tout audio est conforme à la convention, par
construction**. Une mesure locale sur les noms de fichiers ne dit rien de
l'archive réelle.

## Linting (ce dépôt)

**Aucun.** Ni formateur ni linter n'est déclaré pour PHP : le dépôt ne porte
aucune configuration (`.php-cs-fixer`, `.editorconfig`, aucun outil au
`composer.json`), et rien n'est lancé en CI. `CLAUDE.md` demande d'écrire
`aucun` plutôt que de laisser deviner — c'est fait.

Conséquence sur le style, constatée et non arbitrée : `src/src/*.php` (code
Silex hérité) écrit uniformément `array()`, les fichiers de test et
`bin/coverage-check.php` écrivent `[]`. Le code neuf suit les tests.

Le `2>/dev/null` ne masque que `stderr`, où quelques tests d'erreur écrivent
leur diagnostic en cours de route. La sortie de PHPUnit reste visible : si la
suite échoue, le `&&` court-circuite et la liste des échecs s'affiche.

`--coverage-text` ne sert qu'à l'œil : il ne rend ni les fonctions libres ni le
détail par fichier, et son total est dominé par `bootstrap.php`. C'est
`coverage-check.php` qui fait foi pour le seuil de `sdr-004`.

Un fichier absent du rapport est un **échec**, jamais un succès : c'est ce qui
attrape un filtre de couverture cassé. Une correspondance **ambiguë** l'est
aussi : le nom demandé est une queue de chemin (`audio.php` désigne
`…/src/audio.php`, jamais `mon_audio.php`), et s'il correspond à plusieurs
fichiers du rapport, le contrôle refuse de choisir plutôt que de mesurer un
homonyme du `vendor`.
