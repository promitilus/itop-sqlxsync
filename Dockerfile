FROM php:7.3-cli

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions yaml pdo_mysql pdo_pgsql

COPY [ ".", "/app" ]

WORKDIR "/app"

VOLUME /conf /app/conf

# By default we wait for docker exec
CMD [ "tail", "-f", "/dev/null" ]
