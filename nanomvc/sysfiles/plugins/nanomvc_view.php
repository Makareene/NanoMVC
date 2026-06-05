<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.3+ compatible)
 * Copyright:  (C) 2007-2008 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// ------------------------------------------------------------------------

/**
 * NanoMVC_View
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_View {
  protected array $view_vars = [];

  /**
   * Class constructor
   *
   * @access public
   */
  public function __construct() {}

  /**
   * assign
   *
   * assign view variables
   *
   * @access public
   * @param  mixed $key   key of assignment, or array of values
   * @param  mixed|null $value value of assignment
   */    
  public function assign(mixed $key, mixed $value = null): void {
    if (isset($value)) $this->view_vars[$key] = $value;
    else
      foreach ($key as $k => $v)
        if (is_int($k))
          $this->view_vars[] = $v;
        else
          $this->view_vars[$k] = $v;
  }

  /**
   * display
   *
   * display a view file
   *
   * @access public
   * @param  string $view_name the name of the view file
   * @return void
   */    
  public function display(string $view_name, ?array $view_vars = null): void {
    $filepath = nmvc::instance()->findView($view_name);

    if (!$filepath)
      throw new Exception("View '{$view_name}' was not found.", 500);

    $this->_view($filepath, $view_vars);
  }  

  /**
   * fetch
   *
   * return the contents of a view file
   *
   * @access public
   * @param  string $filename
   * @param  array|null $view_vars
   * @return string contents of view
   */    
  public function fetch(string $filename, ?array $view_vars = null): string {
    ob_start();
    $this->display($filename, $view_vars);
    $results = ob_get_contents();
    ob_end_clean();
    return $results;
  }

  /**
   * sysview
   *
   * internal: display a view file for some system parts
   *
   * @access public
   * @param  string $view_name
   * @param  array|null $view_vars
   * @return void
   */    
  public function sysview(string $view_name, ?array $view_vars = null): void {
    $filepath = nmvc::instance()->findView($view_name);

    if (!$filepath)
      throw new Exception("View '{$view_name}' was not found.", 500);

    $this->_view($filepath, $view_vars);
  }

  /**
   * _view
   *
   * internal: display a view file
   *
   * @access protected
   * @param string $filename
   * @param array|null $view_vars
   * @return void
   */
  protected function _view(string $filename, ?array $view_vars = null): void {
    extract($this->view_vars);
    if (isset($view_vars)) extract($view_vars);

    try {
      include $filename;
    } catch (Throwable $e) {
      throw new Exception("Trying to include view '$filename': " . $e->getMessage(), 500);
    }
  }

}

?>
