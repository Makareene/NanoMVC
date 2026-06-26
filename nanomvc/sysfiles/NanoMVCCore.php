<?php

/**
 * Name:       NanoMVC -> NanoMVCCore
 * About:      A modernized fork of TinyMVC (PHP 8.3+ compatible)
 * Copyright:  (C) 2007-2009 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

defined('NMVC_BASEDIR') || define('NMVC_BASEDIR', dirname(__DIR__) . DS);

defined('NMVC_MYAPPDIR') || define('NMVC_MYAPPDIR', NMVC_BASEDIR . 'myapp' . DS);

defined('NMVC_VERSION') || define('NMVC_VERSION', '1.0.8');

/**
 * nmvc -> nmvc_core
 *
 * main core class
 *
 * @package		NanoMVC
 * @author		Monte Ohrt, Nipaa (modifications)
 */

class nmvc_core {
  protected ?string $controller_name = null; // real controller name from URL
  protected ?string $action = null; // real controller method name from URL
  protected string $path_info = '/'; // array of url path_info segments
  protected array $url_segments = []; // array of url path_info segments
  protected NanoMVC_View $view;

  protected const PATHS = [ NMVC_MYAPPDIR
                           ,NMVC_BASEDIR . 'myfiles' . DS
                           ,NMVC_BASEDIR . 'sysfiles' . DS
                          ];

  protected const PERSONAL_PATH = [ 'config'     => 'configs'
                                   ,'controller' => 'controllers'
                                   ,'model'      => 'models'
                                   ,'plugin'     => 'plugins'
                                   ,'view'       => 'views'
                                  ];

  /**
    * config file values (default)
    *
    * @access protected
    */
  protected array $config = [ // URL routing, use preg_replace() compatible syntax
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
                             ,'autoload' => []

                            ];

  /**
   * Class constructor
   *
   * @access public
   * @param string $id
   */
  public function __construct(string $id = 'default') {
    $this->path_info = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/'; // set path_info

    $this->setupPluginIncludePath(); // allow plugin classes to be autoloaded

    self::instance($this, $id); // set instance

    self::timer('nmvc_app_start'); // set initial timer

    $this->view = new NanoMVC_View; // instantiate view library

    $config_file = $this->findConfig('application'); // include application config

    if ($config_file)
      $this->config = array_replace_recursive( $this->config
                                              ,include $config_file
                                             );

  }

  /**
   * main method of execution
   *
   * @access public
   */
  public function main(): void {
    $this->setupErrorHandling(); // internal error handling

    $this->setupRouting(); // url remapping/routing

    $this->setupSegments(); // split path_info into array

    $this->setupAutoloaders(); // include custom files

    $controller_class = $this->setupController(); // instantiate the controller

    $this->setupAction(); // get controller method

    if ($this->config['timer']) ob_start(); // capture output if timing

    $controller = new $controller_class($this->controller_name, $this->action); // create controller object

    $controller->{$this->action}(); // execute controller action

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
   * @access protected
   */
  protected function setupErrorHandling(): void {
    if (!defined('NMVC_ERROR_HANDLING') || NMVC_ERROR_HANDLING != 1) return;

    $error_handler_class = $this->config['error_handler_class'];

    if (!class_exists($error_handler_class))
      throw new Exception("Fatal error: Error handler class '{$error_handler_class}' not found.", 1);

    set_exception_handler([$error_handler_class, 'handleException']);
    set_error_handler([$error_handler_class, 'handleError']);
  }

  /**
   * setup url routing for nmvc
   *
   * @access protected
   */
  protected function setupRouting(): void {
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
   * @access protected
   */
  protected function setupSegments(): void {
    $this->url_segments = array_filter(explode('/', $this->path_info), fn($v) => !in_array($v, [0, false, null, ''], true));
  }

  /**
   * setup controller
   *
   * @access protected
   */
  protected function setupController(): string {
    if (!empty($this->config['root_controller']))
      $controller_name = $this->config['root_controller'];
    else
      $controller_name = !isset($this->url_segments[1]) ? $this->config['default_controller'] : $this->url_segments[1];

    if (preg_match('!\W!', $controller_name))
      throw new Exception('Only word characters (letters, digits, and underscores) are allowed for the controller name', 404);

    $controller_file = $this->findController($controller_name);

    if (!$controller_file)
      throw new Exception("Controller '{$controller_name}' was not found", 404);

    include $controller_file;

    $this->controller_name = $controller_name;

    $controller_class = $this->controller_name . '_Controller';

    if (!class_exists($controller_class))
      throw new Exception("Controller class '$controller_class' was not found.", 404);

    return $controller_class;
  }

  /**
   * setup controller method (action) to execute
   *
   * @access protected
   */
  protected function setupAction(): void {
    if (!empty($this->config['root_action']))
      $this->action = $this->config['root_action']; // user override if set
    else
      $this->action = isset($this->url_segments[2]) ? $this->url_segments[2] : $this->config['default_action'];

    if (substr($this->action, 0, 1) == '_')
      throw new Exception("Action name is not allowed '{$this->action}'", 404); // cannot call method names starting with _

    if (preg_match('!\W!', $this->action))
      throw new Exception('Only word characters (letters, digits, and underscores) are allowed for the action name', 404);
  }

  /**
   * autoload any file
   *
   * @access protected
   */
  protected function setupAutoloaders(): void {
    if (isset($this->config['autoload']) && is_array($this->config['autoload'])) {
      foreach ($this->config['autoload'] as $file) {
        try {
          $path = isset($file[0]) && $file[0] === DS
                ? $file
                : NMVC_MYAPPDIR . $file;

          if (!file_exists($path))
            throw new Exception("Autoload file '{$path}' was not found.", 500);

          include_once $path;
        } catch (Throwable $e) {
          throw new Exception("Unable to autoload file '{$file}'.", 500, $e);
        }
      }
    }
  }

  /**
   * instance
   *
   * get/set the nmvc object instance(s)
   *
   * @access public
   * @param object|null $new_instance new object instance
   * @param string $id object instance id
   * @return object
   */
  public static function instance(?object $new_instance = null, string $id = 'default'): object {
    static $instance = [];

    if (isset($new_instance))
      $instance[$id] = $new_instance;

    if (!isset($instance[$id]))
      throw new RuntimeException("NanoMVC instance with ID '{$id}' is not initialized.", 500);

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

  /**
   * setup plugin include path
   *
   * @access protected
   */
  protected function setupPluginIncludePath(): void {
    $include_paths = [get_include_path()];

    foreach (self::PATHS as $base_path) {
      $plugin_path = $base_path . self::PERSONAL_PATH['plugin'] . DS;

      if (is_dir($plugin_path)) $include_paths[] = $plugin_path;
    }

    set_include_path(implode(PATH_SEPARATOR, $include_paths));

    spl_autoload_register([$this, 'autoloadPluginClass']);
  }

  /**
   * autoload plugin class
   *
   * searches for a class file in the configured include_path
   * and loads it automatically when the class is first used
   *
   * @access protected
   * @param string $class class name to load
   */
  protected function autoloadPluginClass(string $class): void {
    $file = strtolower($class) . '.php';

    foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
      $full_path = rtrim($path, DS) . DS . $file;

      if (is_file($full_path)) {
        require_once $full_path;
        return;
      }
    }
  }

  /**
   * prepare file filter
   *
   * @access protected
   * @param string $type
   * @param string $filter
   * @return string
   */
  protected function prepareFileFilter(string $type, string $filter): string {
    $filter = strtolower($filter);
    return match ($type) { 'config'     => 'config_' . $filter . '.php'
                          ,'controller' => $filter . '.php'
                          ,'model'      => $filter . '_model.php'
                          ,'view'       => $filter . '_view.php'
                          ,default      => $filter
                         };
  }

  /**
   * find file
   *
   * @access protected
   * @param string $type
   * @param string $filter
   * @return string|false
   */
  protected function findFile(string $type, string $filter): string|false {
    if (!isset(self::PERSONAL_PATH[$type])) return false;

    $filter = $this->prepareFileFilter($type, $filter);

    foreach (self::PATHS as $base_path) {
      $file_path = $base_path . self::PERSONAL_PATH[$type] . DS . $filter;

      if (is_file($file_path)) return $file_path;
    }

    return false;
  }

  /**
   * find files
   *
   * @access protected
   * @param string $type
   * @param string $filter
   * @return array
   */
  protected function findFiles(string $type, string $filter): array {
    if (!isset(self::PERSONAL_PATH[$type])) return [];

    $filter = $this->prepareFileFilter($type, $filter);

    $files = [];

    foreach (self::PATHS as $base_path) {
      foreach (glob($base_path . self::PERSONAL_PATH[$type] . DS . $filter) ?: [] as $file_path) {
        $file_name = basename($file_path);

        if (!isset($files[$file_name]))
          $files[$file_name] = $file_path;
      }
    }

    return $files;
  }

  /**
   * find config file
   *
   * @access public
   * @param string $filter config name without prefix and extension
   * @return string|false
   */
  public function findConfig(string $filter): string|false {
    return $this->findFile('config', $filter);
  }

  /**
   * find config files
   *
   * @access public
   * @param string $filter config name without prefix and extension
   * @return array
   */
  public function findConfigs(string $filter = '*'): array {
    return $this->findFiles('config', $filter);
  }

  /**
   * find controller file
   *
   * @access public
   * @param string $filter controller name without extension
   * @return string|false
   */
  public function findController(string $filter): string|false {
    return $this->findFile('controller', $filter);
  }

  /**
   * find controller files
   *
   * @access public
   * @param string $filter glob filter without extension
   * @return array
   */
  public function findControllers(string $filter = '*'): array {
    return $this->findFiles('controller', $filter);
  }

  /**
   * get view object
   *
   * @access public
   * @return NanoMVC_View
   */
  public function getView(): NanoMVC_View {
    return $this->view;
  }

  /**
   * get url segments
   *
   * @access public
   * @return array
   */
  public function getUrlSegments(): array {
    return $this->url_segments;
  }

  /**
   * find model file
   *
   * @access public
   * @param string $filter model name without suffix and extension
   * @return string|false
   */
  public function findModel(string $filter): string|false {
    return $this->findFile('model', $filter);
  }

  /**
   * find model files
   *
   * @access public
   * @param string $filter glob filter without suffix and extension
   * @return array
   */
  public function findModels(string $filter = '*'): array {
    return $this->findFiles('model', $filter);
  }

  /**
   * find view file
   *
   * @access public
   * @param string $filter view name without suffix and extension
   * @return string|false
   */
  public function findView(string $filter): string|false {
    return $this->findFile('view', $filter);
  }

  /**
   * find view files
   *
   * @access public
   * @param string $filter glob filter without suffix and extension
   * @return array
   */
  public function findViews(string $filter = '*'): array {
    return $this->findFiles('view', $filter);
  }

  /**
   * get config application
   *
   * @access public
   * @return array
   */
  public function getAppConfig(): array {
    return $this->config;
  }

}

?>
