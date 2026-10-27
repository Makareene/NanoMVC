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

  /** Path to the XML access configuration. */
  protected string $file;
  /** Session key used for the authenticated user and related state. */
  protected string $session_key = 'nanomvc_access_user';
  /** Failed login limit; zero disables account locking. */
  protected int $max_failed = 3;
  /** Loaded XML document containing users, roles, and rules. */
  protected SimpleXMLElement $xml;

  /** Most recently looked-up user, cached for subsequent checks. */
  protected ?SimpleXMLElement $user = null;
  /** Roles of the cached user. */
  protected array $roles = [];
  /** Cached access rules for the current user or session. */
  protected ?array $permissions = null;

  /**
   * Require an active session and load the configured access file.
   *
   * @access public
   */
  public function __construct() {
    if (session_status() !== PHP_SESSION_ACTIVE)
      throw new Exception('PHP session was not started.', 500);

    $config = nmvc::instance()->getAppConfig();

    if (empty($config['access']['file']))
      throw new Exception('Access XML file was not configured.', 500);

    if (!empty($config['access']['session_key']))
      $this->session_key = $config['access']['session_key'];

    if (isset($config['access']['max_failed']))
      $this->max_failed = abs((int)$config['access']['max_failed']);

    $this->file = $config['access']['file'];

    if (!nmvc::instance()->isAbsolutePath($this->file))
      $this->file = NMVC_MYAPPDIR . DS . $this->file;

    $this->load();
  }

  /**
   * Load and validate the XML access file.
   *
   * @access protected
   * @return void
   */
  protected function load(): void {
    if (!file_exists($this->file))
      throw new Exception('Access XML file was not found.', 500);

    $xml = @simplexml_load_file($this->file);

    if ($xml === false)
      throw new Exception('Access XML file is invalid.', 500);

    $this->xml = $xml;
  }

  /**
   * Write the current XML access document to its configured file.
   *
   * @access protected
   * @return void
   */
  protected function save(): void {
    if ($this->xml->asXML($this->file) === false)
      throw new Exception('Unable to save access XML file.', 500);
  }

  /**
   * Return or create an activation secret for an inactive user.
   *
   * @access public
   * @param string $login
   * @return array
   */
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

  /**
   * Activate a user with a password and a prepared unlock key.
   *
   * @access public
   * @param string $login
   * @param string $secret
   * @param string $password
   * @return void
   */
  public function activate(string $login, string $secret, string $password): void {
    $this->check_activation($login, $secret);

    $user = $this->find_user($login);

    $unlock = $_SESSION[$this->session_key . '_unlock_prepare'][$login] ?? null;

    if (!$unlock)
      throw new Exception('Unlock key was not prepared.', 403);

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $unlock_hash = password_hash($unlock, PASSWORD_DEFAULT);

    if ($password_hash === false || $unlock_hash === false)
      throw new Exception('Password hashing failed.', 500);

    $user['password'] = $password_hash;
    $user['unlock'] = $unlock_hash;
    $user['failed'] = '0';
    $user['changed'] = $this->changed();

    $this->remove_attribute($user, 'secret');

    unset($_SESSION[$this->session_key . '_unlock_prepare'][$login]);

    $this->save();
  }

  /**
   * Reset a user account and return a new activation secret.
   *
   * @access public
   * @param string $login
   * @return array
   */
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

  /**
   * Temporarily unlock an account in the current session.
   *
   * @access public
   * @param string $login
   * @param string $unlock
   * @return void
   */
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

  /**
   * Authenticate a user and establish a fresh session identity.
   *
   * @access public
   * @param string $login
   * @param string $password
   * @return void
   */
  public function login(string $login, string $password): void {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('Login failed.', 403);

    if ((string)$user['password'] === '')
      throw new Exception('Login failed.', 403);

    if ($this->is_blocked($login))
      throw new Exception('Account is locked. Unlock key is required.', 403);

    if (!password_verify($password, (string)$user['password'])) {
      unset($_SESSION[$this->session_key . '_unlocked'][$login]);

      $this->increase_failed($user);
      $this->save();

      throw new Exception('Login failed.', 403);
    }

    $user['failed'] = '0';

    unset($_SESSION[$this->session_key . '_unlocked'][$login]);

    $this->save();

    if (!session_regenerate_id(true))
      throw new Exception('Unable to regenerate session ID.', 500);

    $this->set_session_user($user);
  }

  /**
   * Clear the session user and cached access state.
   *
   * @access public
   * @return void
   */
  public function logout(): void {
    unset($_SESSION[$this->session_key]);

    $this->clear_user();
  }

  /**
   * Return the authenticated session user, if present.
   *
   * @access public
   * @return ?array
   */
  public function user(): ?array {
    return $_SESSION[$this->session_key] ?? null;
  }

  /**
   * Check whether a session user is present.
   *
   * @access public
   * @return bool
   */
  public function is_logged(): bool {
    return isset($_SESSION[$this->session_key]);
  }

  /**
   * List users with roles, activation state, and failed login counts.
   *
   * @access public
   * @return array
   */
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

  /**
   * Check a controller, action, and permission against cached access rules.
   *
   * @access protected
   * @param string $controller
   * @param string $action
   * @param string $permission
   * @return bool
   */
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

  /**
   * Build the effective access rules from the current user roles.
   *
   * @access protected
   * @return void
   */
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

  /**
   * Require a permission for the given controller and action.
   *
   * @access public
   * @param string $controller
   * @param string $action
   * @param string $permission
   * @return void
   */
  public function check(string $controller, string $action, string $permission = self::PERMISSION_READ): void {
    if (!$this->can($controller, $action, $permission))
      throw new Exception('Access denied.', 403);
  }

  /**
   * Check whether a user is locked and has not been unlocked in this session.
   *
   * @access public
   * @param string $login
   * @return bool
   */
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

  /**
   * Increment the failed login counter of an XML user.
   *
   * @access protected
   * @param SimpleXMLElement $user
   * @return void
   */
  protected function increase_failed(SimpleXMLElement $user): void {
    $user['failed'] = (int)$user['failed'] + 1;
  }

  /**
   * Find a user by login and cache its roles.
   *
   * @access public
   * @param string $login
   * @return ?SimpleXMLElement
   */
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

  /**
   * Find a role in the XML document by name.
   *
   * @access protected
   * @param string $name
   * @return ?SimpleXMLElement
   */
  protected function find_role(string $name): ?SimpleXMLElement {
    foreach ($this->xml->roles->role as $role)
      if ((string)$role['name'] === $name)
        return $role;

    return null;
  }

  /**
   * Split a comma-separated list and discard empty items.
   *
   * @access protected
   * @param string $value
   * @return array
   */
  protected function split(string $value): array {
    $items = array_map('trim', explode(',', $value));
    return array_values(array_filter($items, fn($v) => $v !== ''));
  }

  /**
   * Check a value against a comma-separated list of wildcard patterns.
   *
   * @access protected
   * @param string $patterns
   * @param string $value
   * @return bool
   */
  protected function match_list(string $patterns, string $value): bool {
    foreach ($this->split($patterns) as $pattern)
      if ($this->match($pattern, $value))
        return true;

    return false;
  }

  /**
   * Match a value against a case-insensitive wildcard pattern.
   *
   * @access protected
   * @param string $pattern
   * @param string $value
   * @return bool
   */
  protected function match(string $pattern, string $value): bool {
    if ($pattern === '*')
      return true;

    $regexp = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/iu';

    return (bool)preg_match($regexp, $value);
  }

  /**
   * Remove an attribute from an XML element.
   *
   * @access protected
   * @param SimpleXMLElement $xml
   * @param string $name
   * @return void
   */
  protected function remove_attribute(SimpleXMLElement $xml, string $name): void {
    unset($xml[$name]);
  }

  /**
   * Prepare or reuse the session unlock key for account activation.
   *
   * @access public
   * @param string $login
   * @return string
   */
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

  /**
   * Validate a user activation secret.
   *
   * @access public
   * @param string $login
   * @param string $secret
   * @return void
   */
  public function check_activation(string $login, string $secret): void {
    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['secret'] === '')
      throw new Exception('Secret code was not found.', 403);

    if (!hash_equals((string)$user['secret'], $secret))
      throw new Exception('Secret code is invalid.', 403);
  }

  /**
   * Filter controller and action lists by the requested permission.
   *
   * @access public
   * @param array $items
   * @param string $permission
   * @return array
   */
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

  /**
   * Clear the cached user, roles, and effective permissions.
   *
   * @access public
   * @return void
   */
  public function clear_user(): void {
    $this->user = null;
    $this->roles = [];
    $this->permissions = null;
  }

  /**
   * Clear cached access state on a cloned library instance.
   *
   * @access public
   * @return void
   */
  public function __clone(): void {
    $this->clear_user();
  }

  /**
   * Create an inactive user with the specified roles.
   *
   * @access public
   * @param string $login
   * @param string $roles
   * @return void
   */
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

  /**
   * Update a user login and assigned roles.
   *
   * @access public
   * @param string $login
   * @param string $new_login
   * @param string $roles
   * @return void
   */
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

  /**
   * Store the supplied user identity and change marker in the session.
   *
   * @access protected
   * @param SimpleXMLElement $user
   * @return void
   */
  protected function set_session_user(SimpleXMLElement $user): void {
    $_SESSION[$this->session_key] = [ 'login'   => (string)$user['login']
                                     ,'roles'   => $this->roles
                                     ,'changed' => (string)$user['changed']
                                    ];
  }

  /**
   * Replace the session identity with an activated user.
   *
   * @access public
   * @param string $login
   * @return void
   */
  public function relogin(string $login): void {
    $this->clear_user();

    $user = $this->find_user($login);

    if (!$user)
      throw new Exception('User was not found.', 404);

    if ((string)$user['password'] === '')
      throw new Exception('User was not activated.', 403);

    $this->set_session_user($user);
  }

  /**
   * Delete a user and clear related session and cached state.
   *
   * @access public
   * @param string $login
   * @return void
   */
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

  /**
   * List roles with either rule counts or their full rules.
   *
   * @access public
   * @param ?string $role_name
   * @param bool $count
   * @return array
   */
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

  /**
   * Update or rename a role; empty rules remove the role.
   *
   * @access public
   * @param string $role_name
   * @param string $new_role_name
   * @param string $rules
   * @return void
   */
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

  /**
   * Create a role from newline-separated access rules.
   *
   * @access public
   * @param string $role_name
   * @param string $rules
   * @return void
   */
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

  /**
   * Generate a microsecond-resolution user change marker.
   *
   * @access protected
   * @return string
   */
  protected function changed(): string {
    return DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)))->format('Y-m-d H:i:s.u');
  }

  /**
   * Validate the session user against the current XML data.
   *
   * @access public
   * @return string
   */
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
