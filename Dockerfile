FROM php:7.3-cli

COPY [ ".", "/app" ]

WORKDIR "/app"

VOLUME /conf /conf

# By default we wait for docker exec
CMD [ "tail", "-f", "/dev/null" ]
