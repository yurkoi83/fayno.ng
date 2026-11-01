#!/bin/bash
echo -n "Начало установки..."
#cp .env.example .env
if docker compose up -d --build; then
    docker compose exec -T php-fpm composer install
    docker compose exec -T php-fpm php artisan key:generate
    docker compose exec -T php-fpm php artisan migrate -n --force
    docker compose exec -T php-fpm php artisan cache:clear
#    docker compose exec -T node npm install
#    docker compose exec -T php-fpm php artisan wayfinder:generate --with-form
    echo "\nГотово. В браузере набираем: http://localhost:8037"
else
        echo "Не удалось запустить контейнеры!!!\n"
fi

