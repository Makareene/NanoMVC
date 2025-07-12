<?php

/**
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2008 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */

// ------------------------------------------------------------------------

/* define SQL actions */
if (!defined('NMVC_SQL_NONE')) define('NMVC_SQL_NONE', 0);
if (!defined('NMVC_SQL_INIT')) define('NMVC_SQL_INIT', 1);
if (!defined('NMVC_SQL_ALL')) define('NMVC_SQL_ALL', 2);

/**
 * NanoMVC_PDO
 *
 * PDO database access
 * compile PHP with --enable-pdo (default with PHP 5.1+)
 *
 * @package    NanoMVC
 * @author     Monte Ohrt, Nipaa (modifications)
 */
class NanoMVC_PDO {

  /** @var PDO|null $pdo PDO object handle */
  public ?PDO $pdo = null;

  /** @var PDOStatement|null $result Query result handle */
  public ?PDOStatement $result = null;

  /** @var int $fetch_mode Results fetch mode */
  public int $fetch_mode = PDO::FETCH_ASSOC;

  /** @var array $query_params Query build parameters */
  public array $query_params = ['select' => '*'];

  /** @var string|null $last_query Last executed query */
  public ?string $last_query = null;

  /** @var string|null $last_query_type Type of last query */
  public ?string $last_query_type = null;
  
  /**
   * class constructor
   *
   * @access public
   * @param array $config Database connection configuration
   * @throws Exception
   */
  public function __construct(array $config) {
    if (!class_exists('PDO', false)) throw new Exception('PHP PDO extension is required.');

    if (empty($config)) throw new Exception('Database configuration is required.');

    $type = strtolower($config['type'] ?? '');
    $charset = $config['charset'] ?? (in_array($type, ['mysql', 'mariadb']) ? 'utf8mb4' : ($type === 'pgsql' ? 'UTF8' : null));

    if ($charset) $config['charset'] = $charset;

    // Build DSN
    if (!empty($config['dsn'])) $dsn = $config['dsn'];
    elseif ($type === 'sqlsrv') $dsn = "sqlsrv:Server={$config['host']};Database={$config['name']}";
    elseif ($type === 'pgsql') $dsn = "pgsql:host={$config['host']};dbname={$config['name']}";
    else $dsn = "{$type}:host={$config['host']};dbname={$config['name']};charset={$config['charset']}"; // default to MySQL/MariaDB

    try {
      $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_PERSISTENT => !empty($config['persistent'])]);

      if (in_array($type, ['mysql', 'mariadb'])) $this->pdo->exec("SET CHARACTER SET {$config['charset']}"); // Apply charset for MySQL/MariaDB

      if ($type === 'pgsql' && $charset) $this->pdo->exec("SET client_encoding TO '{$charset}'"); // Apply charset for PostgreSQL (client encoding)

      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    } catch (PDOException $e) {
      throw new Exception(sprintf("Can't connect to PDO database '%s'. Error: %s", $type, $e->getMessage()));
    }
  }

	/**
   * select
   *
   * set the active record SELECT clause
   *
   * @access public
   * @param string $clause
   * @return string
   */
  public function select(string $clause): string {
    return $this->query_params['select'] = $clause;
  }

  /**
   * from
   *
   * set the active record FROM clause
   *
   * @access public
   * @param string $clause
   * @return string
   */
  public function from(string $clause): string {
    return $this->query_params['from'] = $clause;
  }

  /**
   * where
   *
   * set the active record WHERE clause
   *
   * @access public
   * @param string $clause
   * @param array|string|int|float|null $args
   * @return void
   * @throws Exception
   */
  public function where(string $clause, array|string|int|float|null $args): void {
    if (empty($clause)) throw new Exception("WHERE clause cannot be empty");

    if (!preg_match('/[=<>]/', $clause)) $clause .= '=';

    if (strpos($clause, '?') === false) $clause .= '?';

    $this->_where($clause, (array) $args, 'AND');
  }

  /**
   * orwhere
   *
   * set the active record OR WHERE clause
   *
   * @access public
   * @param string $clause
   * @param array|string|int|float|null $args
   * @return void
   */
  public function orwhere(string $clause, array|string|int|float|null $args): void {
    $this->_where($clause, (array) $args, 'OR');
  }
  
	/**
   * _where
   *
   * set the active record WHERE clause
   *
   * @access private
   * @param string $clause
   * @param array $args
   * @param string $prefix
   * @return array
   * @throws Exception
   */
  private function _where(string $clause, array $args = [], string $prefix = 'AND'): array {
    if (empty($clause))
      throw new Exception("WHERE clause cannot be empty");

    $placeholders = substr_count($clause, '?');

    if ($placeholders > 0 && count($args) !== $placeholders)
      throw new Exception("Mismatched placeholders in WHERE clause: '{$clause}'");

    $entry = ['clause' => $clause, 'args' => $args, 'prefix' => $prefix];

    $this->query_params['where'] ??= [];
    $this->query_params['where'][] = $entry;

    return $entry;
  }

  /**
   * join
   *
   * set the active record JOIN clause
   *
   * @access public
   * @param string $table
   * @param string $on
   * @param string|null $type
   * @return void
   */
  public function join(string $table, string $on, ?string $type = null): void {
    $clause = trim(($type ? $type . ' ' : '') . "JOIN {$table} ON {$on}");

    $this->query_params['join'] ??= [];
    $this->query_params['join'][] = $clause;
  }

  /**
   * in
   *
   * set an active record IN clause with AND
   *
   * @access public
   * @param string $field
   * @param array $elements
   * @param bool $list whether to treat as precompiled list
   * @return void
   */
  public function in(string $field, array $elements, bool $list = false): void {
    $this->_in($field, $elements, $list, 'AND');
  }

  /**
   * orin
   *
   * set an active record IN clause with OR
   *
   * @access public
   * @param string $field
   * @param array $elements
   * @param bool $list whether to treat as precompiled list
   * @return void
   */
  public function orin(string $field, array $elements, bool $list = false): void {
    $this->_in($field, $elements, $list, 'OR');
  }
  
	/**
   * _in
   *
   * Builds an active record IN clause
   *
   * @access private
   * @param string $field
   * @param array|string $elements
   * @param bool $list
   * @param string $prefix
   * @return void
   */
  private function _in(string $field, array|string $elements, bool $list = false, string $prefix = 'AND'): void {
    if (!$list) {
      if (!is_array($elements)) $elements = explode(',', (string)$elements);

      $quoted = array_map(fn($v) => $this->pdo->quote(trim($v)), $elements);
      $clause = "{$field} IN (" . implode(',', $quoted) . ")";
    } else $clause = "{$field} IN ({$elements})";

    $this->_where($clause, [], $prefix);
  }

  /**
   * orderby
   *
   * Sets the ORDER BY clause
   *
   * @access public
   * @param string $clause
   * @return void
   */
  public function orderby(string $clause): void {
    $this->_set_clause('orderby', $clause);
  }

  /**
   * groupby
   *
   * Sets the GROUP BY clause
   *
   * @access public
   * @param string $clause
   * @return void
   */
  public function groupby(string $clause): void {
    $this->_set_clause('groupby', $clause);
  }

  /**
   * limit
   *
   * Sets the LIMIT clause
   *
   * @access public
   * @param int $limit
   * @param int $offset
   * @return void
   */
  public function limit(int $limit, int $offset = 0): void {
    $sql = $offset > 0 ? "{$offset},{$limit}" : (string)$limit;
    $this->_set_clause('limit', $sql);
  }

  /**
   * _set_clause
   *
   * Generic method to assign a query clause
   *
   * @access private
   * @param string $type
   * @param string $clause
   * @param array $args
   * @return void
   */
  private function _set_clause(string $type, string $clause, array $args = []): void {
    if ($type === '' || $clause === '') throw new Exception("Clause type or value cannot be empty");

    $this->query_params[$type] = ['clause' => $clause];

    if (!empty($args)) $this->query_params[$type]['args'] = $args;
  }
  
	/**
   * _query_assemble
   *
   * Builds an active record SELECT query string
   *
   * @access private
   * @param array &$params
   * @param int|null $fetch_mode
   * @return string
   * @throws Exception
   */
  private function _query_assemble(array &$params, ?int $fetch_mode = null): string {
    if (empty($this->query_params['from'])) throw new Exception("FROM clause is required. Call from() before get().");

    $parts = [];
    $parts[] = "SELECT {$this->query_params['select']}";
    $parts[] = "FROM {$this->query_params['from']}";

    if (!empty($this->query_params['join']))
      foreach ($this->query_params['join'] as $join_clause)
        $parts[] = $join_clause;

    if ($this->_assemble_where($where_clause, $params)) $parts[] = $where_clause;

    if (!empty($this->query_params['groupby'])) $parts[] = "GROUP BY {$this->query_params['groupby']['clause']}";

    if (!empty($this->query_params['orderby'])) $parts[] = "ORDER BY {$this->query_params['orderby']['clause']}";

    if (!empty($this->query_params['limit'])) $parts[] = "LIMIT {$this->query_params['limit']['clause']}";

    $query_string = implode(' ', $parts);
    $this->last_query = $query_string;

    $this->query_params = ['select' => '*']; // reset for next query

    return $query_string;
  }

  /**
   * _assemble_where
   *
   * Assembles WHERE clause and collects bound parameters
   *
   * @access private
   * @param string &$where
   * @param array &$params
   * @return bool
   */
  private function _assemble_where(string &$where, array &$params): bool {
    if (empty($this->query_params['where'])) return false;

    $clauses = [];
    $params = [];
    $first = true;

    foreach ($this->query_params['where'] as $condition) {
      $prefix = $first ? 'WHERE' : $condition['prefix'];
      $clauses[] = "{$prefix} {$condition['clause']}";
      $params = array_merge($params, (array)$condition['args']);
      $first = false;
    }

    $where = implode(' ', $clauses);
    return true;
  }

  /**
   * query
   *
   * Executes a raw SQL query or builds one from active record clauses
   *
   * @access public
   * @param string|null $query
   * @param array|null $params
   * @param int|null $fetch_mode
   * @return mixed
   */
  public function query(?string $query = null, ?array $params = null, ?int $fetch_mode = null): mixed {
    if ($query === null) $query = $this->_query_assemble($params, $fetch_mode);

    return $this->_query($query, $params, NMVC_SQL_NONE, $fetch_mode);
  }

	/**
   * query_all
   *
   * Executes a SELECT query and returns all rows
   *
   * @access public
   * @param string|null $query
   * @param array|null $params
   * @param int|null $fetch_mode
   * @return array
   */
  public function query_all(?string $query = null, ?array $params = null, ?int $fetch_mode = null): array {
    if ($query === null) $query = $this->_query_assemble($params, $fetch_mode);

    return $this->_query($query, $params, NMVC_SQL_ALL, $fetch_mode);
  }

  /**
   * query_one
   *
   * Executes a SELECT query and returns a single row
   *
   * @access public
   * @param string|null $query
   * @param array|null $params
   * @param int|null $fetch_mode
   * @return mixed
   */
  public function query_one(?string $query = null, ?array $params = null, ?int $fetch_mode = null): mixed {
    if ($query === null) {
      $this->limit(1);
      $query = $this->_query_assemble($params, $fetch_mode);
    }

    return $this->_query($query, $params, NMVC_SQL_INIT, $fetch_mode);
  }

  /**
   * _query
   *
   * Internal query executor with result handling
   *
   * @access private
   * @param string $query
   * @param array|null $params
   * @param int $return_type
   * @param int|null $fetch_mode
   * @return mixed
   * @throws Exception
   */
  private function _query(string $query, ?array $params = null, int $return_type = NMVC_SQL_NONE, ?int $fetch_mode = null): mixed {
    $fetch_mode ??= $this->fetch_mode;

    try {
      $this->result = $this->pdo->prepare($query);
      $this->result->execute($params);
      $this->result->setFetchMode($fetch_mode);
    } catch (PDOException $e) {
      throw new Exception("PDO Error: {$e->getMessage()} | Query: $query");
    }

    return match ($return_type) {
      NMVC_SQL_INIT => $this->result->fetch(),
      NMVC_SQL_ALL  => $this->result->fetchAll(),
      default       => true
    };
  }

	/**
   * update
   *
   * Updates records in a table
   *
   * @access public
   * @param string $table Table name
   * @param array $columns Key-value pairs of columns to update
   * @return bool
   * @throws Exception
   */
  public function update(string $table, array $columns): bool {
    if (empty($table)) throw new Exception("Unable to update, table name required");

    if (empty($columns)) throw new Exception("Unable to update, at least one column required");

    $fields = [];
    $params = [];

    foreach ($columns as $name => $value) {
      if ($name === '') continue;
      $fields[] = "`{$name}`=?";
      $params[] = $value;
    }

    $query_parts = [
      "UPDATE `{$table}` SET",
      implode(', ', $fields)
    ];

    if ($this->_assemble_where($where_sql, $where_params)) {
      $query_parts[] = $where_sql;
      $params = array_merge($params, $where_params);
    }

    $this->query_params = ['select' => '*'];

    $query = implode(' ', $query_parts);
    return $this->_query($query, $params) === true;
  }
  
  /**
   * insert
   *
   * Inserts a new record into the table
   *
   * @access public
   * @param string $table Table name
   * @param array $columns Key-value pairs of columns
   * @return int Last insert ID
   * @throws Exception
   */
  public function insert(string $table, array $columns): int {
    if (empty($table)) throw new Exception("Unable to insert, table name required");

    if (empty($columns)) throw new Exception("Unable to insert, at least one column required");

    $names = array_keys($columns);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($columns);

    $query = sprintf("INSERT INTO `%s` (`%s`) VALUES (%s)", $table, implode('`,`', $names), implode(',', $placeholders));

    $this->_query($query, $params);
    return $this->last_insert_id();
  }
  
  /**
   * delete
   *
   * Delete records from a table with optional where clause
   *
   * @access public
   * @param string $table
   * @return bool
   * @throws Exception
   */
  public function delete(string $table): bool {
    if (empty($table)) throw new Exception("Unable to delete, table name required");

    $query = ["DELETE FROM `{$table}`"];
    $params = [];

    if ($this->_assemble_where($where_sql, $where_params)) {
      $query[] = $where_sql;
      $params = array_merge($params, $where_params);
    }

    $this->query_params = ['select' => '*'];

    return $this->_query(implode(' ', $query), $params) === true;
  }

  /**
   * next
   *
   * Fetch the next row from the current result set
   *
   * @access public
   * @param int|null $fetch_mode
   * @return mixed
   */
  public function next(?int $fetch_mode = null): mixed {
    if ($fetch_mode !== null) $this->result->setFetchMode($fetch_mode);
    return $this->result->fetch();
  }

  /**
   * last_insert_id
   *
   * Get the last inserted ID
   *
   * @access public
   * @return string
   */
  public function last_insert_id(): string {
    return $this->pdo->lastInsertId();
  }

  /**
   * num_rows
   *
   * Get the number of rows returned from previous select
   *
   * @access public
   * @return int
   */
  public function num_rows(): int {
    return $this->result->rowCount();
  }

  /**
   * affected_rows
   *
   * Get the number of affected rows from previous insert/update/delete
   *
   * @access public
   * @return int
   */
  public function affected_rows(): int {
    return $this->result->rowCount();
  }

  /**
   * last_query
   *
   * Return the last executed query string
   *
   * @access public
   * @return string|null
   */
  public function last_query(): ?string {
    return $this->last_query;
  }

  /**
   * destructor
   *
   * Clean up PDO object
   *
   * @access public
   */
  public function __destruct() {
    $this->pdo = null;
  }
  
}

?>