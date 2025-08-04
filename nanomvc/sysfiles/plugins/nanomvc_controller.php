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
 * NanoMVC_Controller
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Controller {

  public NanoMVC_Load $load;
  public NanoMVC_View $view;
  private string|null $action = null;
  private string|null $controller = null;

  /**
   * class constructor
   *
   * @access public
   */
  public function __construct() {
    nmvc::instance($this, 'controller'); // save controller instance

    $this->load = new NanoMVC_Load; // instantiate load library
    $this->view = &nmvc::instance()->view;
  }

  /**
   * index
   *
   * the default controller method
   *
   * @access public
   */
  public function index(): void {}

  /**
   * __call
   *
   * gets called when an unspecified method is used
   *
   * @access public
   * @param string $function
   * @param array $args
   */
  public function __call(string $function, array $args): void {
    throw new Exception("Unknown controller method '{$function}'", 404);
  }

  public final function _set_action($name): void {
    $this->action = $name;
  }

  public final function _get_action(): string|null {
    return $this->action;
  }

  public final function _set_controller($name): void {
    $this->controller = $name;
  }

  public final function _get_controller(): string|null {
    return $this->controller;
  }

}

?>
