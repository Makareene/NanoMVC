<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.3+ compatible)
 * Copyright:  (C) 2007, New Digital Group Inc. | Modifications (C) 2025, Nipaa
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Directory separator constant
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

// Base directory of the framework
if (!defined('NMVC_BASEDIR')) define('NMVC_BASEDIR', __DIR__ . DS . '..' . DS . 'nanomvc' . DS);

// Uncomment and set if the /myapp/ dir is not inside /nanomvc/
// define('NMVC_MYAPPDIR', DS . 'path' . DS . 'to' . DS . 'myapp' . DS);

// Set to 0 if you want external error/exception handling
define('NMVC_ERROR_HANDLING', 1);

// Load the abstract NanoMVC core first.
require NMVC_BASEDIR . 'sysfiles' . DS . 'NanoMVCCore.php';

// To customize nmvc, copy NanoMVC.php to myapp, myfiles, or any other location.
// Point the require below to your copy and keep the class named nmvc.
// Load only one definition of nmvc.
require NMVC_BASEDIR . 'sysfiles' . DS . 'NanoMVC.php';

// Create and run the app
$nmvc = new nmvc();
$nmvc->main();

?>
