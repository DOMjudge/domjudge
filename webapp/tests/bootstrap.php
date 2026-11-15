<?php declare(strict_types=1);

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/load_db_secrets.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// The tests run with APP_DEBUG=0, in which Symfony does not rebuild a compiled container when
// the code or configuration it was built from changes, for example when a fixture is added or
// a bundle installed. Check its resources like debug mode would, and start from an empty cache
// if they changed.
// Lock, so ParaTest workers do not remove the cache while another one is building it.
$cacheDir = dirname(__DIR__) . '/var/cache/test';
$cacheLock = fopen($cacheDir . '.lock', 'c');
if ($cacheLock !== false) {
    flock($cacheLock, LOCK_EX);
}
foreach (glob($cacheDir . '/*Container.php') ?: [] as $containerFile) {
    if (!(new ConfigCache($containerFile, true))->isFresh()) {
        (new Filesystem())->remove($cacheDir);
        break;
    }
}
if ($cacheLock !== false) {
    flock($cacheLock, LOCK_UN);
    fclose($cacheLock);
}
