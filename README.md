# Care4All

SaaS Laravel 11 para gestão de escalas de apoio domiciliário da Ciclone D'abraços.

## Versao sem instalacao no servidor

Foi adicionada uma versao auto-contida em `upload-ready/`.

Use essa pasta se quiser apenas fazer upload para um dominio/subdominio sem Composer, npm ou Laravel no servidor. Consulte `upload-ready/README-UPLOAD.md`.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- SQLite ou MySQL

## Instalacao local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Aceda a `http://127.0.0.1:8000`.

Credenciais iniciais:

- username: `Tiago`, `Andreia` ou `Fernanda`
- password: defina `CARE4ALL_INITIAL_ADMIN_PASSWORD` ou crie `upload-ready/config-local.php` antes da primeira execução.

## MySQL

No `.env`, altere:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=care4all
DB_USERNAME=...
DB_PASSWORD=...
```

Depois execute:

```bash
php artisan migrate --seed
```

## cPanel/subdominio

1. Crie uma base de dados MySQL e um utilizador no cPanel.
2. Envie o projeto para uma pasta fora de `public_html`, por exemplo `care4all`.
3. Aponte o document root do subdominio para `care4all/public`.
4. Configure `.env` com `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false` e os dados MySQL.
5. Execute via Terminal/SSH:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Garanta permissoes de escrita em `storage` e `bootstrap/cache`.

