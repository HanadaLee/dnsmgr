ARG BASE_IMAGE=alpine:3.24
FROM ${BASE_IMAGE}

RUN if ! id www >/dev/null 2>&1; then \
        if command -v useradd >/dev/null 2>&1; then \
            useradd -U -s /usr/sbin/nologin www; \
        else \
            adduser -D -s /sbin/nologin -g www www; \
        fi; \
    fi \
    && mkdir -p /app/www/public /app/www/runtime/cache /etc/cert /etc/nginx \
    && printf 'image application\n' > /app/www/public/index.php \
    && printf 'listen 80 default_server;\n' > /etc/nginx/nginx.conf \
    && chown -R www:www /app/www/runtime /etc/cert

COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["sh", "/entrypoint.sh"]
CMD ["true"]
