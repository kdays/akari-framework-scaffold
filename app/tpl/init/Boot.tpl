<?php
namespace Rin;

define('DISPLAY_BENCHMARK', false);
define('BASE_APP_DIR',  "app");

include "../core/akari.php";

error_reporting(E_ERROR | E_PARSE | E_USER_ERROR);

use Akari\akari;

akari::getInstance()
    ->initApp(__DIR__."/../", __NAMESPACE__, NULL, __DIR__)
    ->run(NULL);