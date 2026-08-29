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
