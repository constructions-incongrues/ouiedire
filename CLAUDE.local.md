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
