<?php
/**
 * Created by PhpStorm.
 * User: lapotchkin
 * Date: 02.11.2017
 * Time: 20:47
 */

require_once 'vendor/autoload.php';
require_once 'Autoloader.php';
spl_autoload_register(array('Autoloader', 'loadPackages'));

$oGrabber = new \classes\Grabber();
$oGrabber->actionGrabIssue();