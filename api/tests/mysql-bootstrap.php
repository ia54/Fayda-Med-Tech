<?php
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_DATABASE') !== 'faydamed_ci') {
    throw new RuntimeException('MySQL tests are restricted to the disposable CI database.');
}
require __DIR__.'/../vendor/autoload.php';
