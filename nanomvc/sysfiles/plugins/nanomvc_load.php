<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2008 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// ------------------------------------------------------------------------

/**
 * NanoMVC_Load
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Load {

  /**
   * class constructor
   *
   * @access public
   */
  public function __construct() {}

	/**
   * model
   *
   * load a model object
   *
   * @access public
   * @param string $model_name the name of the model class
   * @param string|null $model_alias the property name alias
   * @param string|null $filename the filename (unused here)
   * @param string|null $pool_name the database pool name to use
   * @return bool
   */
  public function model(string $model_name, ?string $model_alias = null, ?string $filename = null, ?string $pool_name = null): bool {
    $model_alias ??= $model_name; // if no alias, use model name

    $filename ??= strtolower($model_name) . '.php'; // default filename (unused)

    if (empty($model_alias)) throw new Exception("Model name cannot be empty");

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $model_alias)) throw new Exception("Model name '{$model_alias}' is an invalid syntax");

    if (method_exists($this, $model_alias)) throw new Exception("Model name '{$model_alias}' is an invalid (reserved) name");

    $controller = nmvc::instance(null, 'controller'); // get controller instance

    if (isset($controller->$model_alias)) return true; // skip if already loaded

    $controller->$model_alias = new $model_name($pool_name); // instantiate model

    return true;
  }

  /**
   * library
   *
   * load a library plugin
   *
   * @access public
   * @param string $lib_name the library class name
   * @param string|null $alias the property name alias
   * @param string|null $filename the filename (currently unused)
   * @return bool
   */
  public function library(string $lib_name, ?string $alias = null, ?string $filename = null): bool {
    $alias ??= $lib_name; // use lib name if alias not provided

    if (empty($alias)) throw new Exception("Library name cannot be empty");

    if (!preg_match('/^[a-zA-Z][a-zA-Z_]+$/', $alias)) throw new Exception("Library name '{$alias}' is an invalid syntax");

    if (method_exists($this, $alias)) throw new Exception("Library name '{$alias}' is an invalid (reserved) name");

    $controller = nmvc::instance(null, 'controller'); // get controller instance

    if (isset($controller->$alias)) return true; // skip if already loaded

    $class_name = "NanoMVC_Library_{$lib_name}";

    $controller->$alias = new $class_name; // instantiate library

    return true;
  }

	/**
   * script
   *
   * load a script plugin
   *
   * @access public
   * @param string $script_name the script plugin name
   * @return bool
   */
  public function script(string $script_name): bool {
    if (!preg_match('/^[a-zA-Z][a-zA-Z_]+$/', $script_name)) throw new Exception("Invalid script name '{$script_name}'");

    $filename = strtolower("NanoMVC_Script_{$script_name}.php");

    if (!@include_once($filename)) throw new Exception("Unknown script file '{$filename}'");

    return true;
  }

	/**
   * database
   *
   * returns a database plugin object
   *
   * @access public
   * @param string|null $poolname the name of the database pool (if null, default pool is used)
   * @return object|null
   */
  public function database(?string $poolname = null): object|null {
    static $dbs = [];

    include 'config_database.php';

    $poolname ??= $config['default_pool'] ?? 'default';

    if (isset($dbs[$poolname])) return $dbs[$poolname]; // return from cache

    if (!empty($config[$poolname]['plugin'])) {
      $class = $config[$poolname]['plugin'];
      return $dbs[$poolname] = new $class($config[$poolname]);
    }

    return null;
  }
  
}

?>