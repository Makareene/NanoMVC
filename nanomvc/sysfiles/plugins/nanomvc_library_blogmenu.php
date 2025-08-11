<?php

/**
 * Name:       NanoMVC_Library_BlogMenu
 * About:      A simple utility for extracting blog categories and articles from NanoMVC structure
 * Author:     Nipaa
 * License:    MIT (optional to declare)
 *
 * Example usage:
 *
 * $this->load->library('blogmenu');
 * $this->blogmenu->get_categories();
 * $this->blogmenu->get_articles();
 */

class NanoMVC_Library_BlogMenu {

  private array $controller_paths;
  private string $format = 'Y-m-d H:i';

  /**
   * Constructor
   */
  public function __construct() {
    $path = 'controllers' . DS;
    $this->controller_paths = ['myapp' => NMVC_MYAPPDIR . $path,
                               'myfiles' => NMVC_BASEDIR . 'myfiles' . DS . $path
                              ];

  }

  public function get_categories(?string $controller = null, string $order = 'created desc'): array {
    $categories = [];

    foreach ($this->controller_paths as $key => $path) {
      $files = $controller === null
             ? glob($path . '*.php')
             : [ $path . $controller . '.php' ];

      foreach ($files as $file) {
        if (!file_exists($file)) continue;

        $filename = basename($file, '.php');
        if (isset($categories[$filename])) continue;

        $categories[$filename] = [];

        $handle = fopen($file, 'r');
        if ($handle === false) throw new Exception("Can't open the Controller '{$filename}'", 500);
        $first_line = fgets($handle);
        fclose($handle);
        //echo $first_line;
        preg_match('/^\<\?php \/\/ -\> as categorie: \{\s*(?<res>.*)\s*\}\s*\?\>$/iu', $first_line, $regexp);
        //print_r($regexp);die;
        if (isset($regexp['res'])) $regexp['res'] = '{ ' . trim($regexp['res']) . ' }';
        else continue;

        try {
          $json = json_decode($regexp['res'], false, 512, JSON_THROW_ON_ERROR);
          if (isset($json->name, $json->created)) {
            $dt = DateTime::createFromFormat($this->format, $json->created);
            if (!($dt && $dt->format($this->format) === $json->created)) throw new Exception("Date created in the controller '{$filename}' is incorrect", 500);
            foreach ($json as $key => $value) $categories[$filename][$key] = $value;
            $categories[$filename]['_link'] = '/' . $filename;
          } else continue;
        } catch (Throwable $e) {
          throw new Exception("Invalid JSON metadata in the controller '{$filename}': " . $e->getMessage(), 500);
        }

      }

    }

    $categories = array_filter($categories, fn($v) => $v !== []);

    // Sorting
    $this->_sort($categories, $order);

    return $categories;
  }

  public function get_articles(?string $action = null, string $order = 'created desc', ?string $controller_name = null, ?string $act = null): array {
    $articles = [];

    if ($controller_name) {
      $file = null;
      if (is_file($this->controller_paths['myapp'] . $controller_name . '.php'))
        $file = $this->controller_paths['myapp'] . $controller_name . '.php';
      elseif (is_file($this->controller_paths['myfiles'] . $controller_name . '.php'))
        $file = $this->controller_paths['myfiles'] . $controller_name . '.php';

      if ($file) {
        include_once $file;
        $controller_name .= '_Controller';
        $controller = new $controller_name($controller_name, $act ? $act : 'index');
      } else throw new Exception("Controller '{$controller_name}' was not found", 500);

    } else $controller = nmvc::instance(null, 'controller'); // get controller instance

    $name = $controller->_get_controller();
    $methods = get_class_methods($controller);
    $class = new ReflectionClass($controller);
    $methods = $action !== null ? [$action] : get_class_methods($controller);

    foreach ($methods as $method) {
      if (str_starts_with($method, '_')) continue;

      $ref = $class->getMethod($method);
      if (!$ref->isPublic()) continue;

      $doc = $ref->getDocComment();
      if (!$doc) continue;

      $doc = preg_replace('/^\s*\*\s?/m', '', $doc); // delete all "*" before json parsing

      // Searching block @blog { ... }
      preg_match('/@blog\s*\{\s*(?<blog>.*?)\s*\}/isu', $doc, $matches);
      if (isset($matches['blog'])) {
        $json = '{' . trim($matches['blog']) . '}';
        try {
          $meta = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
          if (isset($meta->name, $meta->created)) {
            $dt = DateTime::createFromFormat($this->format, $meta->created);
            if (!($dt && $dt->format($this->format) === $meta->created)) throw new Exception('Invalid date format', 500);

            foreach ($meta as $key => $value) $articles[$method][$key] = $value;
            $articles[$method]['_link'] = '/' . $name . '/' . $method;
          } else continue;

        } catch (Throwable $e) {
          throw new Exception("Invalid blog metadata in method '{$name} > {$method}': " . $e->getMessage(), 500);
        }
      }
    }

    // Sorting
    $this->_sort($articles, $order);

    return $articles;
  }

  private function _sort(array &$categories, string $order = ''): void {
    $order = trim($order);
    $parts = preg_split('/\s+/', $order);
    $field = $parts[0] ?? 'created';
    $dir = strtolower($parts[1] ?? 'asc');

    uasort($categories, function ($a, $b) use ($field, $dir) {
      $va = $a[$field] ?? null;
      $vb = $b[$field] ?? null;

      if ($va == $vb) return 0;

      $result = $va <=> $vb;
      return $dir === 'desc' ? -$result : $result;
    });
  }

  public function get_nav(array &$items, string|int $current): array {
    $res = ['pre' => [], 'next' => []];
    $keys = array_keys($items);
    $idx = array_search($current, $keys, true);

    if ($idx !== false && isset($keys[$idx - 1]))
      $res['pre'] = $items[$keys[$idx - 1]];

    if ($idx !== false && isset($keys[$idx + 1]))
      $res['next'] = $items[$keys[$idx + 1]];

    return $res;
  }

  public function pagination(array &$items, int $limit = 1): int|bool {
    $limit = abs($limit);
    if ($limit === 0) return false;
    $cur_page = 0;
    foreach ($items as &$item) {
      $cur_page++;
      $item['_page'] = ceil($cur_page / $limit);
    }
    unset($item);
    return ceil($cur_page / $limit); //max page
  }

}

?>
