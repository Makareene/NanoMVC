<?php

/** 
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2009 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */ 

if(!defined('NMVC_VERSION')) define('NMVC_VERSION','1.0'); 

// directory separator alias 
if(!defined('DS')) define('DS',DIRECTORY_SEPARATOR);  

// define myapp directory 
if(!defined('NMVC_MYAPPDIR')) define('NMVC_MYAPPDIR', NMVC_BASEDIR . 'myapp' . DS);

// Set include_path for spl_autoload to scan relevant directories
$paths = [
    NMVC_MYAPPDIR,
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

if ($spl_funcs === false || !in_array('spl_autoload', $spl_funcs, true)) spl_autoload_register('spl_autoload');

/**
 * nmvc
 *
 * main object class
 *
 * @package		NanoMVC
 * @author		Monte Ohrt, Nipaa (modifications)
 */

class nmvc{
  public $config = null; // config file values
  public $controller = null; // controller object
  public $action = null; // controller method name
  public $path_info = null; // server path_info
  public $url_segments = null; // array of url path_info segments
  
  /**
   * Class constructor
   *
   * @access public
   * @param string $id
   */    
  public function __construct(string $id = 'default'): void {
    self::instance($this, $id); // set instance
  }
  
  /**
   * main method of execution
   *
   * @access public
   */    
  public function main(): void {
    self::timer('tmvc_app_start'); // set initial timer
    
    $this->path_info = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] :
      (!empty($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : ''); // set path_info
    
    $this->setupErrorHandling(); // internal error handling
    
    /* include application config */
    include('config_application.php');
    $this->config = $config;

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
      self::timer('tmvc_app_end');
      echo str_replace('{TMVC_TIMER}', sprintf('%0.5f', self::timer('tmvc_app_start', 'tmvc_app_end')), $output);
    }
  }
  
  /**
   * setup error handling for nmvc
   *
   * @access public
   */    
  public function setupErrorHandling(): void {
    if (defined('NMVC_ERROR_HANDLING') && NMVC_ERROR_HANDLING == 1) {
      /* catch all uncaught exceptions */
      set_exception_handler(['NanoMVC_ExceptionHandler', 'handleException']);
      require_once('nanomvc_errorhandler.php');
      set_error_handler('NanoMVC_ErrorHandler');
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
    $this->url_segments = !empty($this->path_info) ? array_filter(explode('/', $this->path_info)) : null;
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
      $controller_name = !empty($this->url_segments[1]) 
        ? preg_replace('!\W!', '', $this->url_segments[1]) 
        : $this->config['default_controller'];
      $controller_file = "{$controller_name}.php";
      
      /* if no controller, use default */
      if (!stream_resolve_include_path($controller_file)) {
        $controller_name = $this->config['default_controller'];
        $controller_file = "{$controller_name}.php";
      }
    }
    
    include $controller_file;
    
    $controller_class = $controller_name . '_Controller'; // see if controller class exists
    
    $this->controller = new $controller_class(true); // instantiate the controller
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
      $this->action = !empty($this->url_segments[2]) ? $this->url_segments[2] :
        (!empty($this->config['default_action']) ? $this->config['default_action'] : 'index'); // get from url if present, else use default
      
      /* cannot call method names starting with underscore */
      if (substr($this->action, 0, 1) == '_')
        throw new Exception("Action name is not allowed '{$this->action}'");
    }
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
        $this->controller->load->model($model);
  }
  
  /**
   * instance
   *
   * get/set the nmvc object instance(s)
   *
   * @access public
   * @param object|null $new_instance reference to new object instance
   * @param string $id object instance id
   * @return object|null reference to object instance
   */    
  public static function &instance(object|null $new_instance = null, string $id = 'default'): object|null {
    static $instance = [];
    if (isset($new_instance) && is_object($new_instance))
      $instance[$id] = $new_instance;
    return $instance[$id] ?? null;
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