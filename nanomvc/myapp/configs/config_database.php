<?php

/**
 * config_database.php
 *
 * Application database configuration for NanoMVC
 *
 * @package     NanoMVC
 * @author      Monte Ohrt (original), Nipaa (modifications)
 * @license     LGPL v2.1 or later
 */

return [

  'default_pool' => 'default'

  ,'default' => [
    'plugin'      => 'NanoMVC_PDO' // Plugin for DB access
    ,'type'       => 'mysql'       // Connection type
    ,'host'       => 'localhost'   // DB hostname
    ,'name'       => 'dbname'      // DB name
    ,'user'       => 'dbuser'      // DB username
    ,'pass'       => 'dbpass'      // DB password
    ,'persistent' => false         // DB connection persistence?
  ]

];

?>
