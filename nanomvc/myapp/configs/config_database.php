<?php

/**
 * database.php
 *
 * Application database configuration for NanoMVC
 *
 * @package     NanoMVC
 * @author      Monte Ohrt (original), Nipaa (modifications)
 * @license     LGPL v2.1 or later
 */

$config['default']['plugin']     = 'NanoMVC_PDO'; // Plugin for DB access
$config['default']['type']       = 'mysql';       // Connection type
$config['default']['host']       = 'localhost';   // DB hostname
$config['default']['name']       = 'dbname';      // DB name
$config['default']['user']       = 'dbuser';      // DB username
$config['default']['pass']       = 'dbpass';      // DB password
$config['default']['persistent'] = false;         // DB connection persistence?

?>