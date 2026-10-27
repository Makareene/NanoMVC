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
 * NanoMVC_Controller
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Controller {

  /** Loader for controller models, libraries, and databases. */
  public NanoMVC_Load $load;
  /** Shared view renderer for this controller. */
  public NanoMVC_View $view;
  /** Original action name resolved for this controller. */
  private ?string $action = null;
  /** Original controller name resolved for this request. */
  private ?string $controller = null;

  /**
   * class constructor
   *
   * @access public
   */
  public function __construct(?string $controller_name = null, ?string $action = null) {
    nmvc::instance($this, 'controller'); // save controller instance

    $this->load = new NanoMVC_Load; // instantiate load library
    $this->view = nmvc::instance()->getView();
    if ($controller_name) $this->_set_controller($controller_name); // save controller name
    if ($action) $this->_set_action($action); // save action name
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

  /**
   * Set the original action name for this controller.
   *
   * @access public
   * @param string $name
   * @return void
   */
  public final function _set_action(string $name): void {
    $this->action = $name;
  }

  /**
   * Get the original action name for this controller.
   *
   * @access public
   * @return string|null
   */
  public final function _get_action(): string|null {
    return $this->action;
  }

  /**
   * Set the original controller name for this request.
   *
   * @access public
   * @param string $name
   * @return void
   */
  public final function _set_controller(string $name): void {
    $this->controller = $name;
  }

  /**
   * Get the original controller name for this request.
   *
   * @access public
   * @return string|null
   */
  public final function _get_controller(): string|null {
    return $this->controller;
  }

}

?>
