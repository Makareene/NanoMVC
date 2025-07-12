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
 * NanoMVC_Model
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Model {

  /**
   * The database object instance
   *
   * @access public
   */
  public ?object $db = null;

  /**
   * Class constructor
   *
   * @access public
   * @param string|null $poolname
   */
  public function __construct(?string $poolname = null) {
    $this->db = nmvc::instance()->controller->load->database($poolname);
  }
}

?>