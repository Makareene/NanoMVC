<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
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
  public array $view_vars = [];

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
   * @param  string $filename the name of the view file
   * @return void
   */    
  public function display(string $_nmvc_filename, ?array $view_vars = null): void {
    $this->_view("{$_nmvc_filename}.php", $view_vars);
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
   * internal: view a system file
   *
   * @access public
   * @param  string $filename
   * @param  array|null $view_vars
   * @return void
   */    
  public function sysview(string $filename, ?array $view_vars = null): void {
    $filepath = "{$filename}.php";
    $this->_view($filepath, $view_vars);
  }

  /**
   * _view
   *
   * internal: display a view file
   *
   * @access private
   * @param  string $_nmvc_filepath
   * @param  array|null $view_vars
   * @return void
   */    
  private function _view(string $_nmvc_filepath, ?array $view_vars = null): void {
    extract($this->view_vars);
    if (isset($view_vars)) extract($view_vars);
    try {
      include $_nmvc_filepath;
    } catch (Exception $e) {
      throw new Exception("Trying to include view '$_nmvc_filepath': " . $e->getMessage(), 500);
    }
  }
}

?>
