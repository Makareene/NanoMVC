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
 * NanoMVC_Script_Helper
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Script_Helper {

  /**
   * debug
   *
   * Show a PHP variable in a formatted debug window.
   *
   * @access public
   * @param mixed $var Variable to display
   * @param string|null $name Optional header name
   * @param bool $return Return or output contents
   * @param bool $esc HTML-escape output
   * @param bool $hide Hide in HTML comments
   * @return string|void
   */
  public static function debug(mixed $var, ?string $name = null, bool $return = false, bool $esc = true, bool $hide = false): ?string {
    ob_start();

    if (!$hide) {
      echo '<pre style="background-color: #000; border: 1px solid #3f3; clear: both; color: #3f3; line-height: 1.2em; margin: 2em 0; text-align: left;">';
      if ($name !== null) echo '<strong style="background-color: #3f3; color: #000; display: block; padding: .33em 12px;">' . $name . '</strong>';
      echo '<span style="display: block; max-height: 430px; overflow: auto; padding: 0 6px 1.2em 6px;">';
    } else echo '<!--';

    echo $esc ? htmlentities(print_r($var, true)) : print_r($var, true);

    echo $hide ? '-->' : '</span></pre>';

    if ($return) {
      $contents = ob_get_contents();
      ob_end_clean();
      return $contents;
    }

    ob_end_flush();
    return null;
  }

  /**
   * redirect
   *
   * Redirect web browser and exit.
   *
   * @access public
   * @param string $uri Where to redirect to
   * @return never|false
   */
  public static function redirect(string $uri): false {
    if (empty($uri)) return false;

    header("Location: $uri");
    exit; // `never` return type isn't yet used due to optional return false
  }
}

?>
