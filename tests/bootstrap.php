<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once($projectRoot . '/vendor/autoload.php');

// Symlink module into ssp vendor lib so that templates and urls can resolve correctly
$linkPath = $projectRoot . '/vendor/simplesamlphp/simplesamlphp/modules/sqlattribs';
if (file_exists($linkPath) === false) {
    if (symlink($projectRoot, $linkPath) === false) {
        throw new RuntimeException("Unable to create test symlink '$linkPath' to '$projectRoot'.");
    }
}
