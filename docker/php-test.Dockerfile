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
