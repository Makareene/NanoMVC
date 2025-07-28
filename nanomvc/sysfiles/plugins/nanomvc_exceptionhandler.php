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
 * NanoMVC_ExceptionHandler
 * 
 * A simple exception handler to display exceptions in a formatted box.
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_ExceptionHandler extends ErrorException {

  /**
   * printException
   *
   * @access public
   * @param Throwable $e
   * @return void
   */
  public static function printException(Throwable $e): void {
    switch ($e->getCode()) {
      case E_ERROR:             $code_name = 'E_ERROR'; break;
      case E_WARNING:           $code_name = 'E_WARNING'; break;
      case E_PARSE:             $code_name = 'E_PARSE'; break;
      case E_NOTICE:            $code_name = 'E_NOTICE'; break;
      case E_CORE_ERROR:        $code_name = 'E_CORE_ERROR'; break;
      case E_CORE_WARNING:      $code_name = 'E_CORE_WARNING'; break;
      case E_COMPILE_ERROR:     $code_name = 'E_COMPILE_ERROR'; break;
      case E_COMPILE_WARNING:   $code_name = 'E_COMPILE_WARNING'; break;
      case E_USER_ERROR:        $code_name = 'E_USER_ERROR'; break;
      case E_USER_WARNING:      $code_name = 'E_USER_WARNING'; break;
      case E_USER_NOTICE:       $code_name = 'E_USER_NOTICE'; break;
      case E_STRICT:            $code_name = 'E_STRICT'; break;
      case E_RECOVERABLE_ERROR: $code_name = 'E_RECOVERABLE_ERROR'; break;
      default:                  $code_name = $e->getCode();
    }
    ?>
    <span style="display: block; margin: 1em 0; padding: .33em 6px; background-color: #fcc; border: 1px solid #600; color: #600; text-align: left">
      <b>Error:</b> <?= $code_name ?><br />
      <b>Message:</b> <?= $e->getMessage() ?><br />
      <b>File:</b> <?= $e->getFile() ?><br />
      <b>Line:</b> <?= $e->getLine() ?>
    </span>
    <?php
  }

  /**
   * handleException
   *
   * @access public
   * @param Throwable $e
   * @return void
   */
  public static function handleException(Throwable $e): void {
    self::printException($e);
  }
}

?>
