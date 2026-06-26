<?php

/**
 * NanoMVC_Library_Access
 *
 * Simple XML-based access library for NanoMVC.
 *
 * @package    NanoMVC
 * @author     Nipaa
 * @license    LGPL
 */

class NanoMVC_Library_Access {
  public const PERMISSION_READ   = 'r';
  public const PERMISSION_WRITE  = 'w';
  public const PERMISSION_DELETE = 'd';
  public const PERMISSION_CREATE = 'c';

  protected const REGEXP_NAME = '/^[a-zA-Z0-9_]+$/';
  protected const REGEXP_CONTROLLER = '/^([a-z0-9_]+|\*)$/';
  protected const REGEXP_ACTION = '/^[*a-z0-9_]+$/';

  public const SESSION_OK = '';
  public const SESSION_NOT_LOGGED = 'not_logged';
  public const SESSION_USER_DELETED = 'user_deleted';
  public const SESSION_USER_CHANGED = 'user_changed';

  protected string $file;
  protected string $session_key = 'nanomvc_access_user';
  protected int $max_failed = 3;
  protected SimpleXMLElement $xml;

  protected ?SimpleXMLElement $user = null;
  protected array $roles = [];
  protected ?array $permissions = null;

  public function __construct() {
    if (session_status() !== PHP_SESSION_ACTIVE)
      session_start();

    $config = nmvc::instance()->getAppConfig();

    if (empty($config['access']['file']))
      throw new Exception('Access XML file was not configured.', 500);

    if (!empty($config['access']['session_key']))
      $this->session_key = $config['access']['session_key'];

    if (!empty($config['access']['max_failed']))
      $this->max_failed = abs((int)$config['access']['max_failed']);

    $this->file = $config['access']['file'];

    if (!str_starts_with($this->file, DS))
      $this->file = NMVC_MYAPPDIR . DS . $this->file;

    $this->load();
  }

  protected function load(): void {
    if (!file_exists($this->file))
      throw new Exception('Access XML file was not found.', 500);

    $xml = @simplexml_load_file($this->file);

    if ($xml === false)
      throw new Exception('Access XML file is invalid.', 500);

    $this->xml = $xml;
  }

  protected function save(): void {
    if ($this->xml->asXML($this->file) === false)
      throw new Exception('Unable to save access XML file.', 500);
  }

  public function generate(string $login): array {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['password'] !== '')
      throw new Exception('User already has a password.', 403);

    $secret = (string)$user['secret'];

    if ($secret === '') {
      $secret = bin2hex(random_bytes(16));

      $user['secret'] = $secret;
      $user['changed'] = $this->changed();

      $this->save();
    }

    return [ 'login' => $login
            ,'secret' => $secret
           ];
  }

  public function activate(string $login, string $secret, string $password): void {
    $this->check_activation($login, $secret);

    $user = $this->find_user($login);

    $unlock = $_SESSION[$this->session_key . '_unlock_prepare'][$login] ?? null;

    if (!$unlock)
      throw new Exception('Unlock key was not prepared.', 403);

    $user['password'] = password_hash($password, PASSWORD_DEFAULT);
    $user['unlock'] = password_hash($unlock, PASSWORD_DEFAULT);
    $user['failed'] = '0';
    $user['changed'] = $this->changed();

    $this->remove_attribute($user, 'secret');

    unset($_SESSION[$this->session_key . '_unlock_prepare'][$login]);

    $this->save();
  }

  public function reset(string $login): array {
    $login = trim($login);

    if ($login === '')
      throw new Exception('Login was not specified.', 400);

    $current_user = $this->user();

    if ($current_user && $current_user['login'] === $login)
      throw new Exception('You cannot reset yourself.', 403);

    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    $secret = bin2hex(random_bytes(16));

    $user['password'] = '';
    $user['secret'] = $secret;
    $user['failed'] = '0';
    $user['changed'] = $this->changed();

    $this->remove_attribute($user, 'unlock');

    unset($_SESSION[$this->session_key . '_unlock_prepare'][$login]);
    unset($_SESSION[$this->session_key . '_unlocked'][$login]);

    $this->save();

    return [ 'login' => $login
            ,'secret' => $secret
          ];
  }

  public function unlock(string $login, string $unlock): void {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['unlock'] === '')
      throw new Exception('Unlock key was not found.', 403);

    if (!password_verify($unlock, (string)$user['unlock']))
      throw new Exception('Unlock failed.', 403);

    $_SESSION[$this->session_key . '_unlocked'][$login] = true;
  }

  public function login(string $login, string $password): void {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['password'] === '')
      throw new Exception('User was not activated.', 403);

    if ($this->is_blocked($login))
      throw new Exception('Account is locked. Unlock key is required.', 403);

    if (!password_verify($password, (string)$user['password'])) {
      $this->increase_failed($user);
      $this->save();

      throw new Exception('Login failed.', 403);
    }

    $user['failed'] = '0';

    unset($_SESSION[$this->session_key . '_unlocked'][$login]);

    $this->save();

    $this->set_session_user($user);
  }

  public function logout(): void {
    unset($_SESSION[$this->session_key]);
  }

  public function user(): ?array {
    return $_SESSION[$this->session_key] ?? null;
  }

  public function is_logged(): bool {
    return isset($_SESSION[$this->session_key]);
  }

  public function users(): array {
    $res = [];

    foreach ($this->xml->users->user as $user) {
      $failed = (int)$user['failed'];

      $res[] = [ 'login'     => (string)$user['login']
                ,'roles'     => $this->split((string)$user['roles'])
                ,'activated' => (string)$user['password'] !== ''
                ,'blocked'   => $this->max_failed > 0 && $failed >= $this->max_failed
                ,'failed'    => $failed
               ];
    }

    return $res;
  }

  protected function can(string $controller, string $action, string $permission = self::PERMISSION_READ): bool {
    if ($this->permissions === null)
      $this->build_permissions();

    foreach ($this->permissions as $rule) {
      if (!$this->match($rule['controller'], $controller))
        continue;

      if (!$this->match_list($rule['actions'], $action))
        continue;

      if (!in_array($permission, $rule['permissions'], true))
        continue;

      return true;
    }

    return false;
  }

  protected function build_permissions(): void {
    $this->permissions = [];

    if ($this->user !== null)
      $roles = $this->roles;
    else {
      $user = $this->user();

      if (!$user)
        return;

      $roles = $user['roles'];
    }

    foreach ($roles as $role_name) {
      $role = $this->find_role($role_name);

      if (!$role)
        continue;

      foreach ($role->rule as $rule)
        $this->permissions[] = [ 'controller'  => (string)$rule['controller']
                                ,'actions'     => (string)$rule['actions']
                                ,'permissions' => $this->split((string)$rule['permissions'])
                               ];
    }
  }

  public function check(string $controller, string $action, string $permission = self::PERMISSION_READ): void {
    if (!$this->can($controller, $action, $permission))
      throw new Exception('Access denied.', 403);
  }

  public function is_blocked(string $login): bool {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ($this->max_failed <= 0)
      return false;

    if ((int)$user['failed'] < $this->max_failed)
      return false;

    return empty($_SESSION[$this->session_key . '_unlocked'][$login]);
  }

  protected function increase_failed(SimpleXMLElement $user): void {
    $user['failed'] = (int)$user['failed'] + 1;
  }

  public function find_user(string $login): ?SimpleXMLElement {
    if ( $this->user !== null
        && (string)$this->user['login'] === $login
    )
      return $this->user;

    $this->permissions = null;

    foreach ($this->xml->users->user as $user)
      if ((string)$user['login'] === $login) {
        $this->user = $user;
        $this->roles = $this->split((string)$user['roles']);

        return $this->user;
      }

    $this->roles = [];

    return $this->user = null;
  }

  protected function find_role(string $name): ?SimpleXMLElement {
    foreach ($this->xml->roles->role as $role)
      if ((string)$role['name'] === $name)
        return $role;

    return null;
  }

  protected function split(string $value): array {
    $items = array_map('trim', explode(',', $value));
    return array_values(array_filter($items, fn($v) => $v !== ''));
  }

  protected function match_list(string $patterns, string $value): bool {
    foreach ($this->split($patterns) as $pattern)
      if ($this->match($pattern, $value))
        return true;

    return false;
  }

  protected function match(string $pattern, string $value): bool {
    if ($pattern === '*')
      return true;

    $regexp = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/iu';

    return (bool)preg_match($regexp, $value);
  }

  protected function remove_attribute(SimpleXMLElement $xml, string $name): void {
    unset($xml[$name]);
  }

  public function prepare_unlock(string $login): string {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['secret'] === '')
      throw new Exception('Secret code was not found.', 403);

    if ((string)$user['password'] !== '')
      throw new Exception('User already has a password.', 403);

    if (isset($_SESSION[$this->session_key . '_unlock_prepare'][$login]))
      return $_SESSION[$this->session_key . '_unlock_prepare'][$login];

    $unlock = bin2hex(random_bytes(16));

    $_SESSION[$this->session_key . '_unlock_prepare'][$login] = $unlock;

    return $unlock;
  }

  public function check_activation(string $login, string $secret): void {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['secret'] === '')
      throw new Exception('Secret code was not found.', 403);

    if (!hash_equals((string)$user['secret'], $secret))
      throw new Exception('Secret code is invalid.', 403);
  }

  public function map(array $items, string $permission = self::PERMISSION_READ): array {
    $res = [];

    foreach ($items as $controller => $actions) {
      $controller = trim($controller);

      if ($controller === '')
        continue;

      foreach ($this->split($actions) as $action)
        if ($this->can($controller, $action, $permission))
          $res[$controller][] = $action;
    }

    return $res;
  }

  public function clear_user(): void {
    $this->user = null;
    $this->roles = [];
    $this->permissions = null;
  }

  public function __clone(): void {
    $this->clear_user();
  }

  public function create(string $login, string $roles): void {
    $login = trim($login);

    if ($login === '')
      throw new Exception('Login was not specified.', 400);

    if (!preg_match(self::REGEXP_NAME, $login))
      throw new Exception('Login is invalid.', 400);

    if ($this->find_user($login))
      throw new Exception('User already exists.', 403);

    $roles_ar = $this->split($roles);

    if (!$roles_ar)
      throw new Exception('Roles were not specified.', 400);

    foreach ($roles_ar as $role)
      if (!$this->find_role($role))
        throw new Exception('Role was not found: ' . $role, 404);

    $user = $this->xml->users->addChild('user');

    $user['login'] = $login;
    $user['roles'] = implode(',', $roles_ar);
    $user['failed'] = '0';
    $user['changed'] = $this->changed();

    $this->save();
  }

  public function update(string $login, string $new_login, string $roles): void {
    $login = trim($login);
    $new_login = trim($new_login);

    if ($login === '')
      throw new Exception('Login was not specified.', 400);

    if ($new_login === '')
      throw new Exception('New login was not specified.', 400);

    if (!preg_match(self::REGEXP_NAME, $login))
      throw new Exception('Login is invalid.', 400);

    if (!preg_match(self::REGEXP_NAME, $new_login))
      throw new Exception('New login is invalid.', 400);

    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ($login !== $new_login && $this->find_user($new_login))
      throw new Exception('User already exists.', 403);

    $roles_ar = $this->split($roles);

    if (!$roles_ar)
      throw new Exception('Roles were not specified.', 400);

    foreach ($roles_ar as $role)
      if (!$this->find_role($role))
        throw new Exception('Role was not found: ' . $role, 404);

    $user['login'] = $new_login;
    $user['roles'] = implode(',', $roles_ar);
    $user['changed'] = $this->changed();

    $this->save();
  }

  protected function set_session_user(SimpleXMLElement $user): void {
    $_SESSION[$this->session_key] = [ 'login'   => (string)$user['login']
                                     ,'roles'   => $this->roles
                                     ,'changed' => (string)$user['changed']
                                    ];
  }

  public function relogin(string $login): void {
    $this->clear_user();

    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['password'] === '')
      throw new Exception('User was not activated.', 403);

    $this->set_session_user($user);
  }

  public function delete(string $login): void {
    $login = trim($login);

    if ($login === '')
      throw new Exception('Login was not specified.', 400);

    $current_user = $this->user();

    if ($current_user && $current_user['login'] === $login)
      throw new Exception('You cannot delete yourself.', 403);

    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    $dom = dom_import_simplexml($user);

    if (!$dom)
      throw new Exception('Unable to delete user.', 500);

    $dom->parentNode->removeChild($dom);

    unset($_SESSION[$this->session_key . '_unlock_prepare'][$login]);
    unset($_SESSION[$this->session_key . '_unlocked'][$login]);

    $this->save();

    $this->clear_user();
  }

  public function roles(?string $role_name = null, bool $count = true): array {
    $res = [];

    foreach ($this->xml->roles->role as $role) {
      if ( $role_name !== null
          && (string)$role['name'] !== $role_name
      )
        continue;

      if ($count)
        $res[] = [ 'name' => (string)$role['name']
                  ,'cnt'  => count($role->rule)
                ];
      else {
        $rules = [];

        foreach ($role->rule as $rule)
          $rules[] = [ 'controller'  => (string)$rule['controller']
                      ,'actions'     => (string)$rule['actions']
                      ,'permissions' => (string)$rule['permissions']
                    ];

        $res[] = [ 'name'  => (string)$role['name']
                  ,'rules' => $rules
                ];
      }
    }

    return $res;
  }

  public function role_update(string $role_name, string $new_role_name, string $rules): void {
    $role_name = trim($role_name);
    $new_role_name = trim($new_role_name);
    $rules = trim($rules);

    if ($role_name === '')
      throw new Exception('Role name was not specified.', 400);

    if ($new_role_name === '')
      throw new Exception('New role name was not specified.', 400);

    if (!preg_match(self::REGEXP_NAME, $role_name))
      throw new Exception('Role name is invalid.', 400);

    if (!preg_match(self::REGEXP_NAME, $new_role_name))
      throw new Exception('New role name is invalid.', 400);

    $role = $this->find_role($role_name);

    if (!$role)
      throw new Exception('Role was not found.', 404);

    if ($role_name !== $new_role_name && $this->find_role($new_role_name))
      throw new Exception('Role already exists.', 403);

    if ($rules === '') {
      foreach ($this->xml->users->user as $user) {
        $user_roles = $this->split((string)$user['roles']);

        if (!in_array($role_name, $user_roles, true))
          continue;

        $user_roles = array_values(array_filter($user_roles, fn($v) => $v !== $role_name));

        $user['roles'] = implode(',', $user_roles);
        $user['changed'] = $this->changed();
      }

      $dom = dom_import_simplexml($role);

      if (!$dom)
        throw new Exception('Unable to delete role.', 500);

      $dom->parentNode->removeChild($dom);

      $this->save();

      return;
    }

    $parsed_rules = [];

    foreach (preg_split('/\R/u', $rules) as $row_no => $row) {
      $row = trim($row);

      if ($row === '')
        continue;

      $parts = array_map('trim', explode('|', $row));

      if (count($parts) > 3)
        throw new Exception('Rule has too many parts at line ' . ($row_no + 1) . '.', 400);

      $controller = $parts[0] ?? '';
      $actions = $parts[1] ?? '';
      $permissions = $parts[2] ?? '';

      if ($controller === '')
        throw new Exception('Controller was not specified at line ' . ($row_no + 1) . '.', 400);

      if (!preg_match(self::REGEXP_CONTROLLER, $controller))
        throw new Exception('Controller is invalid at line ' . ($row_no + 1) . ': ' . $controller, 400);

      if ($actions !== '') {
        foreach ($this->split($actions) as $action)
          if ( !preg_match(self::REGEXP_ACTION, $action)
              || str_contains($action, '**')
          )
            throw new Exception('Action is invalid at line ' . ($row_no + 1) . ': ' . $action, 400);
      }

      if ($permissions !== '') {
        foreach ($this->split($permissions) as $permission)
          if (!in_array($permission, [ self::PERMISSION_READ
                                      ,self::PERMISSION_WRITE
                                      ,self::PERMISSION_CREATE
                                      ,self::PERMISSION_DELETE
                                    ], true)
          )
            throw new Exception('Permission is invalid at line ' . ($row_no + 1) . ': ' . $permission, 400);
      }

      $parsed_rules[] = [ 'controller'  => $controller
                         ,'actions'     => implode(',', $this->split($actions))
                         ,'permissions' => implode(',', $this->split($permissions))
                        ];
    }

    if (!$parsed_rules)
      throw new Exception('Rules were not specified.', 400);

    $role['name'] = $new_role_name;

    unset($role->rule);

    foreach ($parsed_rules as $parsed_rule) {
      $rule = $role->addChild('rule');

      $rule['controller'] = $parsed_rule['controller'];
      $rule['actions'] = $parsed_rule['actions'];
      $rule['permissions'] = $parsed_rule['permissions'];
    }

    $changed = $this->changed();

    foreach ($this->xml->users->user as $user) {
      $user_roles = $this->split((string)$user['roles']);

      if ($role_name !== $new_role_name) {
        foreach ($user_roles as &$user_role)
          if ($user_role === $role_name)
            $user_role = $new_role_name;

        unset($user_role);
      }

      if (in_array($new_role_name, $user_roles, true)) {
        $user['roles'] = implode(',', $user_roles);
        $user['changed'] = $changed;
      }
    }

    $this->save();
  }

  public function role_create(string $role_name, string $rules): void {
    $role_name = trim($role_name);
    $rules = trim($rules);

    if ($role_name === '')
      throw new Exception('Role name was not specified.', 400);

    if (!preg_match(self::REGEXP_NAME, $role_name))
      throw new Exception('Role name is invalid.', 400);

    if ($this->find_role($role_name))
      throw new Exception('Role already exists.', 403);

    if ($rules === '')
      throw new Exception('Rules were not specified.', 400);

    $parsed_rules = [];

    foreach (preg_split('/\R/u', $rules) as $row_no => $row) {
      $row = trim($row);

      if ($row === '')
        continue;

      $parts = array_map('trim', explode('|', $row));

      if (count($parts) > 3)
        throw new Exception('Rule has too many parts at line ' . ($row_no + 1) . '.', 400);

      $controller = $parts[0] ?? '';
      $actions = $parts[1] ?? '';
      $permissions = $parts[2] ?? '';

      if ($controller === '')
        throw new Exception('Controller was not specified at line ' . ($row_no + 1) . '.', 400);

      if (!preg_match(self::REGEXP_CONTROLLER, $controller))
        throw new Exception('Controller is invalid at line ' . ($row_no + 1) . ': ' . $controller, 400);

      if ($actions !== '') {
        foreach ($this->split($actions) as $action)
          if ( !preg_match(self::REGEXP_ACTION, $action)
              || str_contains($action, '**')
          )
            throw new Exception('Action is invalid at line ' . ($row_no + 1) . ': ' . $action, 400);
      }

      if ($permissions !== '') {
        foreach ($this->split($permissions) as $permission)
          if (!in_array($permission, [ self::PERMISSION_READ
                                      ,self::PERMISSION_WRITE
                                      ,self::PERMISSION_CREATE
                                      ,self::PERMISSION_DELETE
                                     ], true)
          )
            throw new Exception('Permission is invalid at line ' . ($row_no + 1) . ': ' . $permission, 400);
      }

      $parsed_rules[] = [ 'controller'  => $controller
                         ,'actions'     => implode(',', $this->split($actions))
                         ,'permissions' => implode(',', $this->split($permissions))
                        ];
    }

    if (!$parsed_rules)
      throw new Exception('Rules were not specified.', 400);

    $role = $this->xml->roles->addChild('role');

    $role['name'] = $role_name;

    foreach ($parsed_rules as $parsed_rule) {
      $rule = $role->addChild('rule');

      $rule['controller'] = $parsed_rule['controller'];
      $rule['actions'] = $parsed_rule['actions'];
      $rule['permissions'] = $parsed_rule['permissions'];
    }

    $this->save();
  }

  protected function changed(): string {
    return DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)))->format('Y-m-d H:i:s.u');
  }

  public function check_session(): string {
    $session_user = $this->user();

    if (!$session_user)
      return self::SESSION_NOT_LOGGED;

    $user = $this->find_user($session_user['login']);

    if (!$user) {
      $this->logout();
      return self::SESSION_USER_DELETED;
    }

    if ((string)$user['changed'] !== ($session_user['changed'] ?? '')) {
      $this->logout();
      return self::SESSION_USER_CHANGED;
    }

    return self::SESSION_OK;
  }

}

?>
