<?php

declare(strict_types=1);

const APP_NAME = 'Care4All';
const COMPANY_NAME = "Ciclone D'abraços";
const APP_TIMEZONE = 'Europe/Lisbon';
const DB_FILE = __DIR__ . '/data/care4all.sqlite';
if (file_exists(__DIR__ . '/config-local.php')) {
    require __DIR__ . '/config-local.php';
}
if (! defined('INITIAL_ADMIN_PASSWORD')) {
    define('INITIAL_ADMIN_PASSWORD', getenv('CARE4ALL_INITIAL_ADMIN_PASSWORD') ?: 'CHANGE_ME_ON_SERVER');
}
const INITIAL_ADMINS = ['Tiago', 'Andreia', 'Fernanda'];

date_default_timezone_set(APP_TIMEZONE);
