<?php

/**
 * default.php
 *
 * Default application controller for NanoMVC
 *
 * @package     NanoMVC
 * @author      Monte Ohrt (original), Nipaa (modifications)
 * @license     LGPL v2.1 or later
 */

class Default_Controller extends NanoMVC_Controller {
  public function index(): void  {
    $this->view->display('index_view');
  }
}

?>
