<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2008 Monte Ohrt, All rights reserved.
 *              | Modifications (C) 2025, Nipaa
 * Author:     Nipaa
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// ------------------------------------------------------------------------

/**
 * NanoMVC_ErrorHandler
 *
 * Unified error and exception handler for NanoMVC.
 *
 * @package    NanoMVC
 * @author     Nipaa
 */
class NanoMVC_ErrorHandler extends ErrorException {
  /**
   * Error handler: convert PHP errors into exceptions.
   *
   * @param int $errno
   * @param string $errstr
   * @param string $errfile
   * @param int $errline
   * @return void
   * @throws self
   */
  public static function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
    throw new self($errstr, $errno, $errno, $errfile, $errline);
  }

  /**
   * Exception handler: display formatted error via view.
   *
   * @param Throwable $e
   * @return void
   */
  public static function handleException(Throwable $e): void {
    // print_r($e);die;
    if (!headers_sent())
      while (ob_get_level() > 0) ob_end_clean(); // clean the all buffer

    $code = $e->getCode();
    self::_hSent($code);

    $code_val = self::_codeName($code);
    $message  = $e->getMessage();
    $file     = $e->getFile();
    $line     = $e->getLine();

    $view = !isset($_GET['is_ajax_json']) ? ((in_array($code, [404, 410], true)) ? 'notfound_view' : 'error_view') : 'json_view';

    $statuses = self::_get_statuses();

    if($view == 'json_view') header('Content-Type: application/json');

    nmvc::instance()->view->sysview($view, [
      'code'       => $code,
      'code_val'   => $code_val,
      'message'    => $message,
      'file'       => $file,
      'line'       => $line,
      'outputed'   => headers_sent() ? 1 : 0,
      'show_error' => ini_get('display_errors') === '1' && (isset($statuses[$code]) || $code === 0 || (error_reporting() & $code))
    ]);
  }

  /**
   * Translate error code to constant name.
   *
   * @param int $code
   * @return string
   */
  private static function _codeName(int $code): string {
    return match ($code) {
      // PHP error constants
      E_ERROR             => 'E_ERROR',
      E_WARNING           => 'E_WARNING',
      E_PARSE             => 'E_PARSE',
      E_NOTICE            => 'E_NOTICE',
      E_CORE_ERROR        => 'E_CORE_ERROR',
      E_CORE_WARNING      => 'E_CORE_WARNING',
      E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
      E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
      E_USER_ERROR        => 'E_USER_ERROR',
      E_USER_WARNING      => 'E_USER_WARNING',
      E_USER_NOTICE       => 'E_USER_NOTICE',
      E_STRICT            => 'E_STRICT',
      E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
      E_DEPRECATED        => 'E_DEPRECATED',
      E_USER_DEPRECATED   => 'E_USER_DEPRECATED',

      // HTTP status codes
      400 => 'BAD REQUEST',
      401 => 'UNAUTHORIZED',
      403 => 'FORBIDDEN',
      404 => 'NOT FOUND',
      405 => 'METHOD NOT ALLOWED',
      408 => 'REQUEST TIMEOUT',
      410 => 'GONE',
      429 => 'TOO MANY REQUESTS',
      500 => 'INTERNAL SERVER ERROR',
      501 => 'NOT IMPLEMENTED',
      502 => 'BAD GATEWAY',
      503 => 'SERVICE UNAVAILABLE',
      504 => 'GATEWAY TIMEOUT',

      default => 'UNKNOWN'
    };

  }

  private static function _hSent(int $code): void {
    if (headers_sent()) return;

    $statuses = self::_get_statuses();

    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';

    // Подставим заголовок и код по умолчанию 500, если код неизвестен
    $status_text = $statuses[$code] ?? $statuses[500];
    $status_code = array_key_exists($code, $statuses) ? $code : 500;

    header("$protocol $status_code $status_text", true, $status_code);

  }

  private static function _get_statuses(): array {
    return [
      400 => 'Bad Request',
      401 => 'Unauthorized',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      408 => 'Request Timeout',
      410 => 'Gone',
      429 => 'Too Many Requests',
      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout'
    ];
  }

}

?>
