<?php

$config['directory'] = ini_get('xdebug.trace_output_dir');

/**
 * Do not modify below this line :) 
 * ...unless you really, really, reaaaally want to
 */
// get our own trace files
$ownTraces = file_get_contents('trace-own.cache');
$ownTraces = explode(PHP_EOL, $ownTraces);
