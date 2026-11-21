FROM php:8.2-cli

# unzip lets Composer unpack packages; pdo_sqlite and mbstring ship with the official PHP image
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP packages first so this layer is cached when only the code changes
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && cp .env.example .env \
    && php artisan key:generate --force

EXPOSE 8000

# The SQLite file lives in a volume (DB_DATABASE). On the first start it is created and seeded
# with the sample data; later starts only run new migrations, so the data is kept.
# DB_DATABASE is also written to .env: `artisan serve` does not pass most environment variables on
# to the PHP server it starts, so without this, web requests would look for the default database path.
CMD ["sh", "-c", "(grep -q '^DB_DATABASE=' .env || echo \"DB_DATABASE=$DB_DATABASE\" >> .env) && if [ ! -f \"$DB_DATABASE\" ]; then touch \"$DB_DATABASE\" && php artisan migrate --seed --force; else php artisan migrate --force; fi && php artisan serve --host=0.0.0.0 --port=8000"]
