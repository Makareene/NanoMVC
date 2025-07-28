<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007, New Digital Group Inc. | Modifications (C) 2025, Nipaa
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Directory separator constant
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

// Uncomment and set if the /nanomvc/ dir is not one level above this file
// define('NMVC_BASEDIR', dirname(__FILE__) . DS . '..' . DS . 'nanomvc' . DS);

// Uncomment and set if the /myapp/ dir is not inside /nanomvc/
// define('NMVC_MYAPPDIR', DS . 'path' . DS . 'to' . DS . 'myapp' . DS);

// Set to 0 if you want external error/exception handling
define('NMVC_ERROR_HANDLING', 1);

// Base directory of the framework
if (!defined('NMVC_BASEDIR')) define('NMVC_BASEDIR', dirname(__FILE__) . DS . '..' . DS . 'nanomvc' . DS);

// Load the core NanoMVC system
require NMVC_BASEDIR . 'sysfiles' . DS . 'NanoMVC.php';

// Create and run the app
$nmvc = new nmvc();
$nmvc->main();

?>
