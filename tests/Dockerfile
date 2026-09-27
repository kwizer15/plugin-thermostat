ARG PHP_VERSION=7.0
FROM php:${PHP_VERSION}-cli
ARG LIBFAKETIME_VERSION=0.9.10
RUN if grep -q stretch /etc/os-release; then \
        echo 'deb [trusted=yes] http://archive.debian.org/debian stretch main' > /etc/apt/sources.list; \
    fi \
    && apt-get update \
    && apt-get install -y --no-install-recommends gcc make libc6-dev curl ca-certificates \
    && curl -fsSL https://github.com/wolfcw/libfaketime/archive/refs/tags/v${LIBFAKETIME_VERSION}.tar.gz | tar -xz -C /tmp \
    && make -C /tmp/libfaketime-${LIBFAKETIME_VERSION}/src \
    && install -D /tmp/libfaketime-${LIBFAKETIME_VERSION}/src/libfaketime.so.1 /usr/local/lib/faketime/libfaketime.so.1 \
    && rm -rf /tmp/libfaketime-* /var/lib/apt/lists/*
ENV TZ=Europe/Paris \
    LD_PRELOAD=/usr/local/lib/faketime/libfaketime.so.1 \
    FAKETIME_TIMESTAMP_FILE=/tmp/faketime \
    FAKETIME_NO_CACHE=1
RUN echo "date.timezone=Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "2026-01-15 10:00:00" > /tmp/faketime \
    && chmod 666 /tmp/faketime
