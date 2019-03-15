<?php
/**
 * This file implements a trimmed down version of the SSP module aware autoloader that can be used in tests..
 *
 * @param string $className Name of the class.
 */
function SimpleSAML_test_module_autoload($className)
{
    $modulePrefixLength = strlen('sspmod_');
    $classPrefix = substr($className, 0, $modulePrefixLength);
    if ($classPrefix !== 'sspmod_') {
        return;
    }

    $modNameEnd = strpos($className, '_', $modulePrefixLength);
    $module = substr($className, $modulePrefixLength, $modNameEnd - $modulePrefixLength);
    $path = explode('_', substr($className, $modNameEnd + 1));
    $file = dirname(dirname(__FILE__)).'/lib/'.join('/', $path).'.php';

    if (!file_exists($file)) {
        return;
    }
    require_once($file);
}
spl_autoload_register('SimpleSAML_test_module_autoload');
