FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm
RUN docker-php-ext-install pdo_mysql \
    && apt-get update && apt-get install -y --no-install-recommends mariadb-client \
    && rm -rf /var/lib/apt/lists/*
