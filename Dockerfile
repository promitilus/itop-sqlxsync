FROM php:7.3-cli

COPY [ ".", "/app" ]

WORKDIR "/app"

# By default we wait for docker exec
CMD [ "tail", "-f", "/dev/null" ]
