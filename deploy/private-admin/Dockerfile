ARG PHP_IMAGE
FROM ${PHP_IMAGE}
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev && docker-php-ext-install pdo_pgsql && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY src /app/src
COPY deploy/private-admin/read.php /app/deploy/private-admin/read.php
# Configuration/secrets remain operator-supplied; no default grants or private keys.
