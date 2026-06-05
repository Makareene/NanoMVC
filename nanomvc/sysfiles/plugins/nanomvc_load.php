<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.3+ compatible)
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
   * @param string $model_name the model name without "_Model" suffix
   * @param string|null $model_alias the property name alias
   * @param string|null $pool_name the database pool name to use
   * @return bool
   */
  public function model(string $model_name, ?string $model_alias = null, ?string $pool_name = null): bool {
    $model_alias ??= $model_name; // if no alias, use model name

    if (empty($model_alias)) throw new Exception('Model name cannot be empty', 500);

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $model_alias))
      throw new Exception("Model name '{$model_alias}' is an invalid syntax", 500);

    $controller = nmvc::instance(null, 'controller'); // get controller instance

    if (isset($controller->$model_alias)) return true; // skip if already loaded

    if (!property_exists($controller, $model_alias))
      throw new Exception("Controller does not contain its property '{$model_alias}'.", 500);

    $model_file = nmvc::instance()->findModel($model_name);

    if (!$model_file)
      throw new Exception("Model file '{$model_name}' was not found.", 500);

    include_once $model_file;

    $class_name = $model_name . '_Model';

    if (!class_exists($class_name))
      throw new Exception("Model class '{$class_name}' was not found.", 500);

    $controller->$model_alias = new $class_name($pool_name); // instantiate model

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
   * @return bool
   */
  public function library(string $lib_name, ?string $alias = null): bool {
    $alias ??= $lib_name; // use lib name if alias not provided

    if (empty($alias)) throw new Exception('Library name cannot be empty', 500);

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $alias)) throw new Exception("Library name '{$alias}' is an invalid syntax", 500);

    $controller = nmvc::instance(null, 'controller'); // get controller instance

    if (isset($controller->$alias)) return true; // skip if already loaded

    if (!property_exists($controller, $alias))
      throw new Exception("Controller does not contain its property '{$alias}'.", 500);

    $class_name = "NanoMVC_Library_{$lib_name}";

    if (!class_exists($class_name))
      throw new Exception("Library class '{$class_name}' was not found.", 500);

    $controller->$alias = new $class_name; // instantiate library

    return true;
  }

  /**
   * database
   *
   * returns a database plugin object
   *
   * @access public
   * @param string|null $poolname the name of the database pool (if null, default pool is used)
   * @return object
   */
  public function database(?string $poolname = null): object {
    static $dbs = [];

    $config_file = nmvc::instance()->findConfig('database');

    if (!$config_file)
      throw new Exception('Database configuration file was not found.', 500);

    $config = include $config_file;

    $poolname ??= $config['default_pool'] ?? 'default';

    if (isset($dbs[$poolname])) return $dbs[$poolname]; // return from cache

    if (!isset($config[$poolname]))
      throw new Exception("Database pool '{$poolname}' was not found.", 500);

    if (empty($config[$poolname]['plugin']))
      throw new Exception("Database plugin was not specified for pool '{$poolname}'.", 500);

    $class = $config[$poolname]['plugin'];

    if (!class_exists($class))
      throw new Exception("Database plugin class '{$class}' was not found.", 500);

    return $dbs[$poolname] = new $class($config[$poolname]); // instantiate plugin
  }
  
}

?>
