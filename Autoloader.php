<?php

/**
 * Class Autoloader
 */
class Autoloader {

    private static $_lastLoadedFilename;

    public static function loadPackages($className) {
        if (!strstr($className, 'OAuth')) {
            $pathParts                 = explode('\\', $className);
            self::$_lastLoadedFilename = implode('/', $pathParts) . '.php';
            require_once(self::$_lastLoadedFilename);
        }
    }

    public static function loadPackagesAndLog($className) {
        self::loadPackages($className);
        printf("Class %s was loaded from %sn", $className, self::$_lastLoadedFilename);
    }

}
