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
 * NanoMVC_ErrorHandler
 * 
 * A simple error handler that converts PHP errors to exceptions.
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
function NanoMVC_ErrorHandler(int $errno, string $errstr, string $errfile, int $errline): void {
  if (error_reporting() === 0) return; // do nothing if error reporting is turned off

  if (error_reporting() & $errno) throw new NanoMVC_ExceptionHandler($errstr, $errno, $errno, $errfile, $errline); // convert reportable error to exception
}

?>