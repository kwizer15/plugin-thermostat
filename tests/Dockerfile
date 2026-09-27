ARG PHP_VERSION=7.3
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends faketime \
    && rm -rf /var/lib/apt/lists/*
ENV TZ=Europe/Paris \
    LD_PRELOAD=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1 \
    FAKETIME_TIMESTAMP_FILE=/tmp/faketime \
    FAKETIME_NO_CACHE=1
RUN echo "date.timezone=Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "2026-01-15 10:00:00" > /tmp/faketime
