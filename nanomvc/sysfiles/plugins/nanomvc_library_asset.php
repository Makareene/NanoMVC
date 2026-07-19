<?php

/**
 * NanoMVC_Library_Asset
 *
 * JavaScript and CSS bundling and generation library.
 *
 * @package    NanoMVC
 * @author     Nipaa
 */
class NanoMVC_Library_Asset {

  /**
   * Project generation configuration.
   *
   * @var array
   */
  protected array $config;

  /**
   * Source templates directory.
   *
   * @var string
   */
  protected string $mode_tpl_from;

  /**
   * Generated templates directory.
   *
   * @var string
   */
  protected string $mode_tpl_to;

  /**
   * Template generation mode: yes|no|always.
   *
   * @var string
   */
  protected string $mode_tpl_generation;

  /**
   * class constructor
   *
   * @access public
   * @throws Exception
   */
  public function __construct() {
    $config_file = nmvc::instance()->findConfig('generation');

    if (!$config_file)
      throw new Exception('Generation config file was not found.', 500);

    $this->config = include $config_file;

    $controller = nmvc::instance(null, 'controller'); // get controller instance

    $this->mode_tpl_from       = $controller->mode_tpl_from;
    $this->mode_tpl_to         = $controller->mode_tpl_to;
    $this->mode_tpl_generation = $controller->mode_tpl_generation;
  }

  /**
   * Generate or return a configured asset list.
   *
   * @param string $template
   * @param string $key
   * @return array
   * @throws Exception
   */
  protected function generate(string $template, string $key): array {
    if (empty($this->config[$template]))
      throw new Exception('Template "' . $template . '" was not found in the project generation config.', 500);

    if (empty($this->config[$template][$key]))
      throw new Exception('Asset key "' . $key . '" was not found for template "' . $template . '".', 500);

    $asset = $this->config[$template][$key];

    if (empty($asset['name']))
      throw new Exception('Generated asset name was not found for key "' . $key . '".', 500);

    if (empty($asset['list']) || !is_array($asset['list']))
      throw new Exception('Asset list was not found for key "' . $key . '".', 500);

    if ($this->mode_tpl_generation === 'no')
      return $asset['list'];

    if (!in_array($this->mode_tpl_generation, ['yes', 'always'], true))
      throw new Exception('Unknown template generation mode "' . $this->mode_tpl_generation . '".', 500);

    $folder = $this->get_folder($asset['name']);

    if (!$folder)
      throw new Exception('Generated asset "' . $asset['name'] . '" does not have an extension.', 500);

    $source_directory = $this->mode_tpl_from . DS . $template . DS . $folder;

    $target_directory = $this->mode_tpl_to . DS . $template . DS . $folder;

    $target_file = $target_directory . DS . $asset['name'];

    $need_generation = $this->mode_tpl_generation === 'always' || !is_file($target_file);

    if ($need_generation) {
      if (!is_dir($target_directory)
        && !mkdir($target_directory, 0775, true)
        && !is_dir($target_directory))
        throw new Exception('Unable to create directory "' . $target_directory . '".', 500);

      $content = '';

      foreach ($asset['list'] as $file_name) {
        $source_file = $source_directory . DS . $file_name;

        if (!is_file($source_file))
          throw new Exception('Source asset file "' . $source_file . '" was not found.', 500);

        $file_content = file_get_contents($source_file);

        if ($file_content === false)
          throw new Exception('Unable to read source asset file "' . $source_file . '".', 500);

        if ($file_content !== '' && !str_ends_with($file_content, "\n"))
          $file_content .= PHP_EOL;

        $content .= '/* File: ' . $file_name . ' */' . PHP_EOL;

        $content .= $file_content . PHP_EOL;
      }

      if (file_put_contents($target_file, $content, LOCK_EX) === false)
        throw new Exception('Unable to write generated asset file "' . $target_file . '".', 500);
    }

    return [ $asset['name'] ];
  }

  /**
   * Get the asset folder name.
   *
   * Override this method to customize the asset directory structure.
   *
   * @param string $name Asset file name.
   * @return string Folder name.
   */
  protected function get_folder(string $name): string {
    return pathinfo($name, PATHINFO_EXTENSION);
  }

  /**
   * Get an asset list.
   *
   * @param string $template
   * @param string $key
   * @return array
   * @throws Exception
   */
  public function get(string $template, string $key): array {
    return $this->generate($template, $key);
  }

}

?>
