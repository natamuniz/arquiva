#!/bin/sh
# Prepara o projeto na primeira subida: dependências, .env, chave, migrations, seed e documentação.
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

if [ ! -f storage/app/.seeded ]; then
    php artisan db:seed --force
    touch storage/app/.seeded
fi

php artisan scribe:generate --no-interaction || echo "Aviso: não foi possível gerar a documentação da API."

# Sinal para os containers queue e scheduler começarem.
touch storage/app/.ready

exec "$@"
