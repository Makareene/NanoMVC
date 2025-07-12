<?php

/**
 * application.php
 *
 * Application configuration for NanoMVC
 *
 * @package     NanoMVC
 * @author      Monte Ohrt (original), Nipaa (modifications)
 * @license     LGPL v2.1 or later
 */

// URL routing, use preg_replace() compatible syntax
$config['routing']['search'] = [];
$config['routing']['replace'] = [];

// Set this to force controller and method instead of using URL params
$config['root_controller'] = null;
$config['root_action'] = null;

// Default controller/method when none is given in the URL
$config['default_controller'] = 'default';
$config['default_action'] = 'index';

// PHP class that handles system errors
$config['error_handler_class'] = 'NanoMVC_ErrorHandler';

// Enable timer. Use {TMVC_TIMER} in your view to see it
$config['timer'] = true;

?>