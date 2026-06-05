<?php

/**
 * config_application.php
 *
 * Application configuration for NanoMVC
 *
 * @package     NanoMVC
 * @author      Monte Ohrt (original), Nipaa (modifications)
 * @license     LGPL v2.1 or later
 */

return [

  // URL routing, use preg_replace() compatible syntax
  'routing' => [ 'search'  => []
                ,'replace' => []
               ]

  // Set this to force controller and method instead of using URL params
 ,'root_controller' => null
 ,'root_action'     => null

  // Default controller/method when none is given in the URL
 ,'default_controller' => 'default'
 ,'default_action'     => 'index'

  // PHP class that handles system errors
 ,'error_handler_class' => 'NanoMVC_ErrorHandler'

  // Enable timer. Use {NMVC_TIMER} in your view to see it
 ,'timer' => true

  // Autoload files
 ,'autoload' => [] // Use a full path starting with the DS constant, or use a relative path from the "myapp" project directory.

];

?>
