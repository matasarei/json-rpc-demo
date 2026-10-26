#!/bin/sh
set -e

# compose.yaml mounts the source over /app, so the vendor directory built into
# the image is hidden: install it on the first start, and again whenever the
# lock file changes.
if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

exec docker-php-entrypoint "$@"
