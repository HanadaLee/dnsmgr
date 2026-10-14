#!/bin/sh
set -eu

context=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
test_dir=$(mktemp -d /tmp/dnsmgr-mount-test.XXXXXX)
image="dnsmgr-mount-test:$(basename "$test_dir" | tr '[:upper:]' '[:lower:]')"

cleanup() {
    docker run --rm --pull=never --entrypoint sh \
        --mount "type=bind,src=$test_dir,dst=/testdata" \
        "$image" -c "chown -R $(id -u):$(id -g) /testdata" >/dev/null 2>&1 || true
    docker image rm "$image" >/dev/null 2>&1 || true
    rm -rf "$test_dir"
}
trap cleanup EXIT

docker build --quiet --file "$context/tests/Dockerfile" \
    --build-arg "BASE_IMAGE=${DNSMGR_TEST_BASE_IMAGE:-alpine:3.24}" \
    --tag "$image" "$context" >/dev/null
mkdir -p "$test_dir/data/runtime/log" "$test_dir/data/public" "$test_dir/cert" "$test_dir/empty-app"
printf 'historical task log\n' > "$test_dir/data/runtime/log/history.log"
printf 'stale application\n' > "$test_dir/data/public/index.php"
printf 'APP_DEBUG = false\n' > "$test_dir/writable.env"
touch "$test_dir/empty.env"
chmod 600 "$test_dir/writable.env"
cp "$test_dir/writable.env" "$test_dir/writable.expected"

# An existing writable configuration stays intact; PHP cannot modify application code.
docker run --rm \
    --mount "type=bind,src=$test_dir/writable.env,dst=/app/www/.env" \
    --mount "type=bind,src=$test_dir/data/runtime/log,dst=/app/www/runtime/log" \
    --mount "type=bind,src=$test_dir/cert,dst=/etc/cert" \
    "$image" sh -ec '
        su -s /bin/sh -c "test -r /app/www/.env" www
        su -s /bin/sh -c "printf new-log > /app/www/runtime/log/new.log; printf certificate > /etc/cert/fullchain.pem" www
        ! su -s /bin/sh -c "test -w /app/www/public/index.php" www
        test "$(cat /app/www/public/index.php)" = "image application"
        test "$(cat /app/www/runtime/log/history.log)" = "historical task log"
        test ! -e /app/firstrun
    '
cmp "$test_dir/writable.env" "$test_dir/writable.expected"
printf 'APP_DEBUG = false\n' > "$test_dir/production.env"
chmod 644 "$test_dir/production.env"
cp "$test_dir/production.env" "$test_dir/production.expected"
owner=$(stat -c '%u:%g' "$test_dir/production.env")

# Recreating the container preserves logs/certificates and accepts a read-only configuration.
for attempt in 1 2; do
    docker run --rm -e LISTEN_PORT=19101 \
        --mount "type=bind,src=$test_dir/production.env,dst=/app/www/.env,readonly" \
        --mount "type=bind,src=$test_dir/data/runtime/log,dst=/app/www/runtime/log" \
        --mount "type=bind,src=$test_dir/cert,dst=/etc/cert" \
        "$image" sh -ec '
            test "$(cat /app/www/public/index.php)" = "image application"
            test "$(cat /app/www/runtime/log/history.log)" = "historical task log"
            test "$(cat /app/www/runtime/log/new.log)" = new-log
            test "$(cat /etc/cert/fullchain.pem)" = certificate
            test ! -w /app/www/.env
            su -s /bin/sh -c "test -r /app/www/.env && test -w /app/www/runtime/log" www
            grep -q "listen 19101 default_server;" /etc/nginx/nginx.conf
        '
done
cmp "$test_dir/production.env" "$test_dir/production.expected"
test "$(stat -c '%u:%g' "$test_dir/production.env")" = "$owner"
test "$(cat "$test_dir/data/public/index.php")" = "stale application"

for mode in '' ',readonly'; do
    if docker run --rm \
        --mount "type=bind,src=$test_dir/empty.env,dst=/app/www/.env$mode" \
        "$image" > "$test_dir/error.log" 2>&1; then
        echo 'An empty configuration should fail without changing application installation logic.' >&2
        exit 1
    fi
    grep -q 'Mount a non-empty database configuration file' "$test_dir/error.log"
done

if docker run --rm "$image" > "$test_dir/error.log" 2>&1; then
    echo 'A missing configuration should fail.' >&2
    exit 1
fi
grep -q 'Mount a non-empty database configuration file' "$test_dir/error.log"

if docker run --rm \
    --mount "type=bind,src=$test_dir/empty-app,dst=/app/www" \
    "$image" > "$test_dir/error.log" 2>&1; then
    echo 'An application-directory mount hiding the image code should fail.' >&2
    exit 1
fi
grep -q 'Do not mount over /app/www' "$test_dir/error.log"

if docker run --rm --entrypoint sh "$image" -ec '
    mkdir /app/www/.env
    sh /entrypoint.sh true
' > "$test_dir/error.log" 2>&1; then
    echo 'A directory at the configuration-file path should fail.' >&2
    exit 1
fi
grep -q 'Mount a non-empty database configuration file' "$test_dir/error.log"

chmod 600 "$test_dir/production.env"
if docker run --rm \
    --mount "type=bind,src=$test_dir/production.env,dst=/app/www/.env,readonly" \
    "$image" > "$test_dir/error.log" 2>&1; then
    echo 'A configuration unreadable by PHP should fail.' >&2
    exit 1
fi
grep -q '.env file must be readable by the www user' "$test_dir/error.log"

echo 'Docker mount tests passed: existing configuration, persistent logs/certificates, image-owned code and invalid mounts.'
