<?php

$config['directory'] = ini_get('xdebug.trace_output_dir');

/**
 * Do not modify below this line :) 
 * ...unless you really, really, reaaaally want to
 */
// get our own trace files
$ownTraces = file_get_contents('trace-own.cache');
$ownTraces = explode(PHP_EOL, $ownTraces);

define('XDEBUG_TRACE_GUI_MEMORY_TRIGGER', '0.3');
define('XDEBUG_TRACE_GUI_TIME_TRIGGER', '0.03');