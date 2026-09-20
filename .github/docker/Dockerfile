ARG ALPINE_VERSION=3.24
FROM alpine:${ALPINE_VERSION}

WORKDIR /app/www

ARG SWOOLE_VERSION=6.2.2
RUN set -eux; \
    apk add --no-cache \
      bash \
      curl \
      nginx \
      ca-certificates \
      php83 \
      php83-ctype \
      php83-curl \
      php83-dom \
      php83-fileinfo \
      php83-fpm \
      php83-ftp \
      php83-gd \
      php83-gettext \
      php83-intl \
      php83-iconv \
      php83-mbstring \
      php83-mysqli \
      php83-opcache \
      php83-openssl \
      php83-phar \
      php83-sodium \
      php83-session \
      php83-simplexml \
      php83-tokenizer \
      php83-xml \
      php83-xmlreader \
      php83-xmlwriter \
      php83-zip \
      php83-pdo \
      php83-pdo_mysql \
      php83-pdo_sqlite \
      php83-pecl-swoole \
      php83-pecl-ssh2 \
      supervisor \
      && ln -sf /usr/bin/php83 /usr/bin/php; \
    apk add --no-cache --virtual .swoole-build-deps \
      php83-dev \
      php83-pear \
      autoconf \
      automake \
      make \
      gcc \
      g++ \
      libc-dev \
      linux-headers \
      pkgconf \
      re2c \
      binutils \
      openssl-dev \
      curl-dev \
      pcre2-dev; \
    curl -fsSL "https://pecl.php.net/get/swoole-${SWOOLE_VERSION}.tgz" -o /tmp/swoole.tgz; \
    mkdir -p /usr/src/swoole; \
    tar -xzf /tmp/swoole.tgz -C /usr/src/swoole --strip-components=1; \
    cd /usr/src/swoole; \
    phpize83; \
    ./configure \
      --with-php-config=/usr/bin/php-config83 \
      --enable-sockets \
      --enable-swoole-curl \
      --enable-cares \
      --enable-brotli \
      --enable-zstd; \
    make -j"$(getconf _NPROCESSORS_ONLN)"; \
    make install; \
    strip --strip-unneeded /usr/lib/php83/modules/swoole.so; \
    cd /; \
    rm -rf /usr/src/swoole /tmp/swoole.tgz; \
    apk del .swoole-build-deps; \
    rm -rf /var/cache/apk/* /tmp/*

COPY config/nginx.conf /etc/nginx/nginx.conf

ENV PHP_INI_DIR=/etc/php83
COPY config/fpm-pool.conf ${PHP_INI_DIR}/php-fpm.d/www.conf
COPY config/php.ini ${PHP_INI_DIR}/conf.d/custom.ini
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

ARG CACHE_BUST=local
ARG DNSMGR_VERSION=dev
ARG DNSMGR_REPOSITORY=HanadaLee/dnsmgr
ARG DNSMGR_REF=ext
ARG DNSMGR_SOURCE_SHA

LABEL org.opencontainers.image.title="dnsmgr" \
      org.opencontainers.image.description="Extended dnsmgr image" \
      org.opencontainers.image.version="${DNSMGR_VERSION}" \
      org.opencontainers.image.source="https://github.com/${DNSMGR_REPOSITORY}/tree/${DNSMGR_REF}"

RUN set -eux; \
    source_ref="${DNSMGR_SOURCE_SHA:-$DNSMGR_REF}"; \
    echo "$CACHE_BUST" >/dev/null; \
    mkdir -p /usr/src; \
    wget --no-cache "https://github.com/${DNSMGR_REPOSITORY}/archive/${source_ref}.zip" -O /usr/src/www.zip; \
    unzip /usr/src/www.zip -d /usr/src/; \
    source_dir="$(find /usr/src -mindepth 1 -maxdepth 1 -type d -name 'dnsmgr-*' -print -quit)"; \
    test -n "$source_dir"; \
    mv "$source_dir" /usr/src/www; \
    rm -f /usr/src/www.zip

RUN echo "$CACHE_BUST" >/dev/null \
    && wget https://getcomposer.org/download/latest-stable/composer.phar -O /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

RUN echo "$CACHE_BUST" >/dev/null \
    && composer install -d /usr/src/www --no-interaction --no-dev --optimize-autoloader --no-cache

RUN adduser -D -s /sbin/nologin -g www www \
    && chown -R www:www /usr/src/www /var/lib/nginx /var/log/nginx \
    && echo "* * * * * cd /app/www && /usr/bin/php83 think certtask" | crontab -u www -

COPY config/run_tasks.sh /app/run_tasks.sh
RUN chmod +x /app/run_tasks.sh

COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["sh", "/entrypoint.sh"]

EXPOSE 80

CMD /usr/sbin/crond && /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping || exit 1
