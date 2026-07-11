<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/src/Hook/functions.php';

$config = new Phorum\Core\Config(ROOT_PATH . '/etc/phorum.php');

define('PHORUM_DB',        $config->get('db_name',   'phorum'));
define('PHORUM_DB_PREFIX', $config->get('db_prefix', 'phorum'));

$app = new Phorum\Core\App($config);
$app->run();
