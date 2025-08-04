<?php

/**
 * Name:       NanoMVC_Library_URI
 * About:      A URI utility library for NanoMVC
 * Copyright:  (C) Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * Credits:    pablo77
 *
 * Example usage:
 *
 * $this->load->library('uri');
 * $this->uri->segment(3);
 * $this->uri->uri_to_assoc(3);
 * $this->uri->uri_to_array(3);
 */

// ------------------------------------------------------------------------

/**
 * NanoMVC_Library_URI
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_Library_URI {

  public ?array $path = null;

  /**
   * class constructor
   *
   * @access public
   */
  public function __construct() {
    $this->path = nmvc::instance()?->url_segments;
  }

  /**
   * get specific URI segment
   *
   * @access public
   * @param int $index
   * @return string|false
   */
  public function segment(int $index = 1): string|false {
    return isset($this->path[$index]) ? $this->path[$index] : false;
  }

  /**
   * convert URI segments to associative array
   *
   * @access public
   * @param int $index
   * @return array
   */
  public function uri_to_assoc(int $index = 1): array {
    $assoc = [];

    for ($x = count($this->path), $y = $index; $y <= $x; $y += 2) {
      $key = $this->path[$y];
      $assoc[$key] = $this->path[$y + 1] ?? null;
    }

    return $assoc;
  }

  /**
   * convert URI segments to array starting at index
   *
   * @access public
   * @param int $index
   * @return array|false
   */
  public function uri_to_array(int $index = 1): array|false {
    return is_array($this->path) ? array_slice($this->path, $index - 1) : false;
  }

  public function uri(int $index = 1): ?string {
    $path = $this->uri_to_array($index);
    return is_array($path) ? implode('/', $path) : null;
  }

}

?>
