<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.3+ compatible)
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
      while (ob_get_level() > 0) ob_end_clean(); // clean all buffers

    $code = $e->getCode();

    if ($code === 0 && $e instanceof ErrorException)
      $code = $e->getSeverity();

    self::_hSent($code);

    $code_val = self::_codeName($code);
    $message  = $e->getMessage();
    $file     = $e->getFile();
    $line     = $e->getLine();

    $statuses = self::_get_statuses();
    $view_vars = [ 'code'       => $code
                  ,'code_val'   => $code_val
                  ,'message'    => $message
                  ,'file'       => $file
                  ,'line'       => $line
                  ,'outputed'   => headers_sent() ? 1 : 0
                  ,'show_error' => ini_get('display_errors') === '1'
                                && (isset($statuses[$code]) || $code === 0 || (error_reporting() & $code))
                  ,'statuses'   => $statuses
                 ];

    $app = nmvc::instance();
    $views = $app->getView();
    $is_json = isset($_GET['is_ajax_json']);
    $default_view = in_array($code, [404, 410], true) ? 'notfound' : 'error';
    $view_name = $is_json
               ? 'json'
               : ($app->getAppConfig()['error_views'][$default_view] ?? $default_view);

    if ($is_json && !headers_sent())
      header('Content-Type: application/json');

    $buffer_level = ob_get_level();
    ob_start();

    try {
      $views->sysview($view_name, $view_vars);
    } catch (Throwable $view_error) {
      while (ob_get_level() > $buffer_level)
        ob_end_clean();

      self::_hSent($code);
      self::_displayFallback($view_vars, $view_error, $is_json);
      return;
    }

    ob_end_flush();
  }

  /**
   * Display the original error directly when the selected view fails.
   *
   * @param array $view_vars
   * @param Throwable $view_error
   * @param bool $is_json
   * @return void
   */
  private static function _displayFallback(array $view_vars, Throwable $view_error, bool $is_json): void {
    $code = $view_vars['code'];
    $name = $view_vars['code_val'];

    if ($is_json) {
      $data = [ 'err_type' => isset($view_vars['statuses'][$code]) ? 'HTTP' : 'Error'
               ,'err_code' => $code
               ,'err_name' => $name
              ];

      if ($view_vars['show_error'])
        $data += [ 'err_msg'  => $view_vars['message']
                  ,'err_file' => $view_vars['file']
                  ,'err_line' => $view_vars['line']
                 ];

      if (!headers_sent())
        header('Content-Type: application/json');

      echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
      return;
    }

    $text = $code . ' ' . $name;

    if ($view_vars['show_error']) {
      $text .= "\n" . $view_vars['message'];
      $text .= "\n" . $view_vars['file'] . ':' . $view_vars['line'];
      $text .= "\nError rendering view: " . $view_error->getMessage();
    }

    if (!headers_sent())
      header('Content-Type: text/html; charset=UTF-8');

    echo '<pre>', htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '</pre>';
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

  /**
   * Send an HTTP error status unless headers have already been sent.
   *
   * @access private
   * @param int $code
   * @return void
   */
  private static function _hSent(int $code): void {
    if (headers_sent()) return;

    $statuses = self::_get_statuses();

    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';

    // Use status 500 if the error code is unknown
    $status_text = $statuses[$code] ?? $statuses[500];
    $status_code = array_key_exists($code, $statuses) ? $code : 500;

    header("$protocol $status_code $status_text", true, $status_code);

  }

  /**
   * Return supported HTTP error status descriptions.
   *
   * @access private
   * @return array
   */
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
