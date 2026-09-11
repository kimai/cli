FROM docker.io/library/php:8.5-cli-bookworm AS build

WORKDIR /app

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
ENV COMPOSER_HOME=/tmp/composer
RUN composer global require humbug/box:^4.7 --no-interaction --no-scripts --no-plugins

COPY composer.json composer.lock box.json.dist LICENSE ./
COPY src/ src/
COPY bin/kimai bin/kimai
COPY .git/ .git/
RUN composer install --no-dev --no-interaction --no-scripts --no-plugins \
    && php -d phar.readonly=0 /tmp/composer/vendor/bin/box compile

FROM docker.io/library/php:8.5-cli-bookworm
COPY --from=build /app/kimai.phar /usr/local/bin/kimai
ENTRYPOINT ["php", "/usr/local/bin/kimai"]
CMD []
