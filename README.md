# prs_be

# Start laravel
php artisan serve

# Start websocket
php websocket reverb:serve

# Image resizer install
composer require intervention/image-laravel

# Deploy Hosting
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
npm run dev -- --host


