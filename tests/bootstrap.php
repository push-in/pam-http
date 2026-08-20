<?php

declare(strict_types=1);

$runtimeBootstrap = dirname(__DIR__, 2) . '/runtime/bootstrap.php';
if (!class_exists(\Pam\Http\Request::class) && is_file($runtimeBootstrap)) {
    require_once $runtimeBootstrap;
}

