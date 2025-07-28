<?php

/** 
 * Name:       NanoMVC
 * About:      A modernized fork of TinyMVC (PHP 8.4+ compatible)
 * Copyright:  (C) 2007-2009 Monte Ohrt, All rights reserved. | Modifications (C) 2025, Nipaa
 * Author:     Monte Ohrt, monte [at] ohrt [dot] com, Nipaa (modifications)
 * License:    LGPL v2.1 or later (see LICENSE file)
 */ 

// directory separator alias 
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

require_once dirname(__FILE__) . DS . 'NanoMVCCore.php'; // main core class

/**
 * nmvc
 *
 * main object class
 *
 * @package		NanoMVC
 * @author		Monte Ohrt, Nipaa (modifications)
 */

class nmvc extends nmvc_core {}
 
?>
