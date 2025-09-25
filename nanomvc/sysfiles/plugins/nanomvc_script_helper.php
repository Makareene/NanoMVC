<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2008 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

/**
 * NanoMVC_Script_Helper
 *
 * Utility class for debugging, redirection, and low-level script helpers.
 *
 * @package    NanoMVC
 */
class NanoMVC_Script_Helper
{
  /**
   * Show a PHP variable in a formatted debug window.
   *
   * @param mixed       $var    Variable to display
   * @param string|null $name   Optional header name
   * @param bool        $return Return or output contents
   * @param bool        $esc    HTML-escape output
   * @param bool        $hide   Hide inside HTML comments
   * @return string|null
   */
  public static function debug(mixed $var, ?string $name = null, bool $return = false, bool $esc = true, bool $hide = false): ?string {
    ob_start();

    if (!$hide) {
      echo '<pre style="background-color: #1e1e1e; border: 1px solid #4ec9b0; clear: both; color: #d4d4d4; line-height: 1.4em; margin: 2em 0; text-align: left; font-family: Consolas, monospace;">';
      if ($name !== null) echo '<strong style="background-color: #4ec9b0; color: #1e1e1e; display: block; padding: .33em 12px;">' . $name . '</strong>';
      echo '<div style="display: block; max-height: 430px; overflow: auto; padding: 0 6px 1.2em 6px;">';
    } else echo '<!--';

    echo $esc ? htmlentities(print_r($var, true)) : print_r($var, true);

    echo $hide ? '-->' : '</div></pre>';

    if ($return) {
      $contents = ob_get_contents();
      ob_end_clean();
      return $contents;
    }

    ob_end_flush();
    return null;
  }

  /**
   * Send headers and redirect.
   *
   * @param string $uri     Destination URI
   * @param array  $headers Optional headers to send before redirection
   * @return false
   */
  public static function redirect(string $uri, ?int $code = null): false {
    if (empty($uri)) return false;

    // Send status code header if specified
    if ($code === 301) {
      $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
      $res = self::send_headers([$protocol . ' 301 Moved Permanently' => null]);
      if (!$res) return false;
    }

    header("Location: $uri");
    exit;
  }

  /**
   * Send additional headers safely.
   *
   * @param array $headers Associative array or single-line headers
   * @return bool
   */
  public static function send_headers(array $headers = [], bool $replace = true): bool {
    if (headers_sent()) return false;

    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $regex = '/^' . preg_quote($protocol, '/') . '\s+(?<code>[1-9][0-9]{2})\s+/i';

    foreach ($headers as $key => $value) {
      // Single-line raw header
      if ($value === null && is_string($key)) {
        if (preg_match($regex, $key, $matches)) {
          $code = (int)$matches['code'];
          header($key, $replace, $code);
        } else {
          header($key, $replace);
        }
      }
      // Standard key-value header
      elseif (is_string($key) && is_scalar($value)) {
        header("$key: $value", $replace);
      }
    }

    return true;
  }

  public static function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  public static function view(string $_nmvc_filename, ?array $view_vars = null): void {
    (new NanoMVC_View)->display($_nmvc_filename, $view_vars);
  }

  public static function is_int_like(mixed $value): bool {
    if (!is_string($value) && !is_int($value)) return false;

    $str = (string)$value;

    if ($str === '') return false;

    // Allow single leading minus
    if ($str[0] === '-') $str = substr($str, 1);

    // Empty string after removing "-" → not valid
    if ($str === '') return false;

    return ctype_digit($str);
  }

}

?>
