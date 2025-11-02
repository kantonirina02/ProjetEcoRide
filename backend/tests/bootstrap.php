<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$projectDir = dirname(__DIR__);
$sqliteUrl = 'sqlite:///'.$projectDir.'/var/test.db';

if (
    !isset($_SERVER['DATABASE_URL'])
    || str_contains($_SERVER['DATABASE_URL'], 'mysql://')
    || str_contains($_SERVER['DATABASE_URL'], 'postgresql://')
    || str_contains($_SERVER['DATABASE_URL'], 'pgsql://')
) {
    $_SERVER['DATABASE_URL'] = $sqliteUrl;
}
if (
    !isset($_ENV['DATABASE_URL'])
    || str_contains($_ENV['DATABASE_URL'], 'mysql://')
    || str_contains($_ENV['DATABASE_URL'], 'postgresql://')
    || str_contains($_ENV['DATABASE_URL'], 'pgsql://')
) {
    $_ENV['DATABASE_URL'] = $sqliteUrl;
}
if (!is_dir($projectDir.'/var')) {
    mkdir($projectDir.'/var', 0775, true);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
