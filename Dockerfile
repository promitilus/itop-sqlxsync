FROM php:7.4-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		ca-certificates \
	&& apt-get clean \
	&& rm -rf /var/lib/apt/lists/*

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions yaml pdo_mysql pdo_pgsql

COPY [ ".", "/app" ]

WORKDIR "/app"

VOLUME /conf /app/conf

# By default we wait for docker exec
CMD [ "tail", "-f", "/dev/null" ]
