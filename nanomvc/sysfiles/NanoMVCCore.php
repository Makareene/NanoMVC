<?php

/**
 * Name:       NanoMVC -> NanoMVCCore
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2009 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

if(!defined('NMVC_VERSION')) define('NMVC_VERSION', '1.0.3');

// directory separator alias
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

// define myapp directory
if(!defined('NMVC_MYAPPDIR')) define('NMVC_MYAPPDIR', NMVC_BASEDIR . 'myapp' . DS);

// Set include_path for spl_autoload to scan relevant directories
$paths = [NMVC_MYAPPDIR,
          NMVC_BASEDIR . 'myfiles' . DS,
          NMVC_BASEDIR . 'sysfiles' . DS,
         ];

$subdirs = ['controllers', 'models', 'configs', 'plugins', 'views'];

$includePaths = [get_include_path()];

foreach ($paths as $basePath)
  foreach ($subdirs as $subdir)
    $includePaths[] = $basePath . $subdir . DS;

set_include_path(implode(PATH_SEPARATOR, $includePaths));

// Set autoload extensions (try .php first for speed)
spl_autoload_extensions('.php,.inc');

// Register spl_autoload if not already registered
$spl_funcs = spl_autoload_functions();

if ($spl_funcs === false || !in_array('spl_autoload', $spl_funcs, true)) spl_autoload_register('spl_autoload'); // include the all scripts here

/**
 * nmvc -> nmvc_core
 *
 * main core class
 *
 * @package		NanoMVC
 * @author		Monte Ohrt, Nipaa (modifications)
 */

class nmvc_core{
  public $config = null; // config file values
  public $controller = null; // controller object
  public $action = null; // controller method name
  public $path_info = null; // server path_info
  public $url_segments = null; // array of url path_info segments
  public NanoMVC_View $view;

  /**
   * Class constructor
   *
   * @access public
   * @param string $id
   */
  public function __construct(string $id = 'default') {
    self::instance($this, $id); // set instance

    self::timer('nmvc_app_start'); // set initial timer

    $this->view = new NanoMVC_View; // instantiate view library

  }

  /**
   * main method of execution
   *
   * @access public
   */
  public function main(): void {
    $this->path_info = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/'; // set path_info

    /* include application config */
    include 'config_application.php';
    $this->config = $config;

    $this->setupErrorHandling(); // internal error handling

    $this->setupRouting(); // url remapping/routing

    $this->setupSegments(); // split path_info into array

    $this->setupController(); // create controller object

    $this->setupAction(); // get controller method

    $this->setupAutoloaders(); // run library/script autoloaders

    if ($this->config['timer']) ob_start(); // capture output if timing

    $this->controller->{$this->action}(); // execute controller action

    if ($this->config['timer']) {
      /* insert timing info */
      $output = ob_get_contents();
      ob_end_clean();
      self::timer('nmvc_app_end');
      echo str_replace('{NMVC_TIMER}', sprintf('%0.5f', self::timer('nmvc_app_start', 'nmvc_app_end')), $output);
    }
  }

  /**
   * setup error handling for nmvc
   *
   * @access public
   */
  public function setupErrorHandling(): void {
    if (defined('NMVC_ERROR_HANDLING') && NMVC_ERROR_HANDLING == 1) {
      /* Catch all uncaught exceptions */
      $error_handler_class = !empty($this->config['error_handler_class']) ? $this->config['error_handler_class'] : 'NanoMVC_ErrorHandler';
      if (!class_exists($error_handler_class)) throw new Exception("Fatal error: Error handler class '{$error_handler_class}' not found.");
      set_exception_handler([$error_handler_class, 'handleException']);
      set_error_handler([$error_handler_class, 'handleError']);
    }
  }

  /**
   * setup url routing for nmvc
   *
   * @access public
   */
  public function setupRouting(): void {
    if (!empty($this->config['routing']['search']) && !empty($this->config['routing']['replace']))
      $this->path_info = preg_replace(
        $this->config['routing']['search'],
        $this->config['routing']['replace'],
        $this->path_info
      );
  }

  /**
   * setup url segments array
   *
   * @access public
   */
  public function setupSegments(): void {
    $this->url_segments = array_filter(explode('/', $this->path_info), fn($v) => !in_array($v, [0, false, null, ''], true));
  }

  /**
   * setup controller
   *
   * @access public
   */
  public function setupController(): void {
    /* get controller/method */
    if (!empty($this->config['root_controller'])) {
      $controller_name = $this->config['root_controller'];
      $controller_file = "{$controller_name}.php";
    } else {
      if ( !isset($this->url_segments[1]) )
        $controller_name = (!empty($this->config['default_controller']) ? $this->config['default_controller'] : 'default'); // get from url if present, else use default
      else $controller_name = $this->url_segments[1];

      if (preg_match('!\W!', $controller_name)) throw new Exception('Only word characters (letters, digits, and underscores) are allowed for the controller name', 404);

      $controller_file = "{$controller_name}.php";

    }

    $controller_file = strtolower($controller_file);

    /* if no controller, throw an exception */
    if (!stream_resolve_include_path($controller_file)) throw new Exception("Controller '{$controller_name}' was not found", 404);

    include $controller_file;

    $controller_class = $controller_name . '_Controller'; // see if controller class exists

    if(!class_exists($controller_class)) throw new Exception("Controller class '$controller_class' was not found.", 404);

    $this->controller = new $controller_class(); // instantiate the controller

    $this->controller->_set_controller($controller_name); // save controller name

  }

  /**
   * setup controller method (action) to execute
   *
   * @access public
   */
  public function setupAction(): void {
    if (!empty($this->config['root_action'])) {
      $this->action = $this->config['root_action']; // user override if set
    } else {
      $this->action = isset($this->url_segments[2]) ? $this->url_segments[2] :
        (!empty($this->config['default_action']) ? $this->config['default_action'] : 'index'); // get from url if present, else use default

      if (substr($this->action, 0, 1) == '_') throw new Exception("Action name is not allowed '{$this->action}'", 404); // cannot call method names starting with _

      if (preg_match('!\W!', $this->action)) throw new Exception('Only word characters (letters, digits, and underscores) are allowed for the action name', 404);

    }

    $this->controller->_set_action($this->action); // save action name

  }

  /**
   * autoload any libs/scripts
   *
   * @access public
   */
  public function setupAutoloaders(): void {
    include 'config_autoload.php';

    if (!empty($config['libraries']))
      foreach ($config['libraries'] as $library)
        if (is_array($library)) $this->controller->load->library($library[0], $library[1]);
        else $this->controller->load->library($library);

    if (!empty($config['scripts']))
      foreach ($config['scripts'] as $script)
        $this->controller->load->script($script);

    if (!empty($config['models']))
      foreach ($config['models'] as $model)
        if (is_array($model)) $this->controller->load->model($model[0], $model[1], null, isset($model[2]) ? $model[2] : null);
        else $this->controller->load->model($model);
  }

  /**
   * instance
   *
   * get/set the nmvc object instance(s)
   *
   * @access public
   * @param object|null $new_instance reference to new object instance
   * @param string $id object instance id
   * @return object reference to object instance
   */
  public static function &instance(object|null $new_instance = null, string $id = 'default'): object {
    static $instance = [];

    if (isset($new_instance))
      $instance[$id] = $new_instance;

    if (!isset($instance[$id]))
      throw new RuntimeException("NanoMVC instance with ID '{$id}' is not initialized.");

    return $instance[$id];
  }

  /**
   * timer
   *
   * get/set timer values
   *
   * @access public
   * @param string|null $id the timer id to set (or compare with $id2)
   * @param string|null $id2 the timer id to compare with $id
   * @return float|false difference of two times or false if not set
   */
  public static function timer(string|null $id = null, string|null $id2 = null): float|false {
    static $times = [];
    if ($id !== null && $id2 !== null)
      return (isset($times[$id]) && isset($times[$id2])) ? ($times[$id2] - $times[$id]) : false;
    elseif ($id !== null)
      return $times[$id] = microtime(true);
    return false;
  }

}

?>
