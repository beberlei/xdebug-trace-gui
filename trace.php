<?php
require 'trace.config.php';
error_reporting(0);
?>
<html>
	<head>
		<style type="text/css">
			@import url('trace.css');
		</style>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
		<title>Xdebug Trace File Parser</title>
	</head>
	<body>
		<h1>Xdebug Trace File Parser</h1>
		<h2>Settings</h2>
		<form method="get" action="trace.php">
			<label>File
				<select name="file">
					<option value="" selected="selected"> -- Select -- </option>
					<?php
					$files = new DirectoryIterator($config['directory']);
					foreach ($files as $file)
					{

						if (substr_count($file->getFilename(), '.xt') == 0 || in_array($config['directory'] . '/' . $file->getFilename(), $ownTraces))
						{
							continue;
						}

						$date = explode('.', $file->getFilename());
						$date = date('Y-m-d H:i:s', $date [0]);

						echo '<option value="' . $file->getFilename() . '"> ' . $date . ' - ' . $file->getFilename() . ' </option>';
					}
					?>
				</select>
			</label>

			<label>If the memory jumps <input type="text" name="memory" value="<?= XDEBUG_TRACE_GUI_MEMORY_TRIGGER ?>" style="text-align:right" size="5"/> MB, provide an alert</label>
			<label>If the execution time jumps <input type="text" name="time" value="<?= XDEBUG_TRACE_GUI_TIME_TRIGGER ?>" style="text-align:right" size="5"/> seconds, provide an alert</label>

			<input type="submit" value="parse" />

		</form>

		<h2>Output</h2>
		<?php
		/**
		 * retrieve the xdebug.trace_format ini set.
		 */
		$xdebug_trace_format = ini_get('xdebug.trace_format');
		$XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE_LEN = strlen(XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE);

		$traceFile = $config['directory'] . '/' . $_GET ['file'];

		$memJump = 1;
		if (isset($_GET['memory']))
		{
			$memJump = (float) $_GET['memory'];
		}

		$timeJump = 1;
		if (isset($_GET['time']))
		{
			$timeJump = (float) $_GET['time'];
		}

		if (!isset($_GET ['file']) || empty($_GET ['file']))
		{
			echo '<p>No file selected</p>';
		}
		else if (!file_exists($traceFile))
		{
			echo '<p>Invalid file</p>';
		}
		else
		{

			$trace = file_get_contents($traceFile);
			$lines = explode("\n", $trace);
			$previousLevel = 0;
			$levelIds = array();
			$ids = 0;
			$defFn = get_defined_functions();
			/**
			 * counter
			 */
			$jCnt = 0;
			
			/**
			 * Process all lines
			 */
			foreach ($lines as $line)
			{
				/**
				 * Add one to the counter
				 */
				$jCnt++;

				switch ($xdebug_trace_format)
				{
					/**
					 * xdebug.trace_format = 1  Computerized
					 * @link http://www.xdebug.org/docs/all_settings#trace_format
					 */
					case 1:
						$data = explode("\t", $line);
						list($level, $id, $point, $time, $memory, $function, $type, $file, $filename, $line, $numParms) = $data;
						
						/**
						 * if there is params save it
						 */
						if (isset($numParms) and $numParms > 0)
						{
							$valParms = '';
							for($i=11; $i<(11+$numParms); $i++)
							{
									$valParms .= "<li>". str_replace('\n', '<br />', htmlentities($data[$i])) . "</li>\n";
							}
						}
						elseif(!empty($file))
						{
							$valParms = "<li>{$file}</li>";
						}
						else
						{
							$valParms = '';
						}
						
						$memory = round($memory / (1024 * 1024), 4);

						break;

					/**
					 * Other xdebug.trace_format
					 * @todo code for all types
					 */
					default:

						$strippedLine = preg_replace('(([\s]{2,}))', ' ', trim($line));
						list ($time, $memory) = explode(" ", $strippedLine);

						$memory = round($memory / (1024 * 1024), 4);

						$level = round(((strpos($line, '->') - strpos($line, $memory) + strlen($memory))) / 2);

						if ($level <= $previousLevel && isset($levelIds[$level]))
						{
							$fullTrace[$levelIds[$level]]['timeOnExit'] = $time;
							$fullTrace[$levelIds[$level]]['memoryOnExit'] = $memory;
						}
						$id = ++$ids;
						$levelIds[$level] = $id;
						$previousLevel = $level;

						$parts = array_map('trim', explode("->", $line, 2));
						$parts = explode(" ", $parts[1]);
						$function = $parts[0];
						list($line, $file) = array_map('strrev', explode(":", strrev($parts[1]), 2));
						$filename = $file;
						$point = 0;
						$type = in_array(substr($function, 0, strpos($function, "(")), $defFn['internal']) ? 0 : 1;

						$valParms = '';

						break;
				}

				if (empty($function))
				{
					$fullTrace[$id]['timeOnExit'] = $time;
					$fullTrace[$id]['memoryOnExit'] = $memory;
					continue;
				}

				if ($point == 0)
				{
					// starting function
					$fullTrace[$id] = array('level' => $level, 
						'id' => $id, 
						'timeOnEntry' => $time, 
						'memoryOnEntry' => $memory, 
						'function' => $function, 
						'type' => $type, 
						'file' => $file, 
						'filename' => $filename, 
						'line' => $line,
						'valParms' => $valParms);

					if (($memory - $lastMemory) > $memJump)
					{
						$fullTrace[$id]['memoryAlert'] = $memory - $lastMemory;
					}
					else
					{
						$fullTrace[$id]['memoryAlert'] = false;
					}


					if (($time - $lastTime) > $timeJump)
					{
						$fullTrace[$id]['timeAlert'] = $time - $lastTime;
					}
					else
					{
						$fullTrace[$id]['timeAlert'] = false;
					}

					$lastMemory = $memory;
					$lastTime = $time;
				}
				else
				{
					$fullTrace[$id]['timeOnExit'] = $time;
					$fullTrace[$id]['memoryOnExit'] = $memory;
				}
			}
			?>
			<table>
				<tr>
					<td>
						<?= $traceFile; ?><br />
						<strong><?= count($fullTrace); ?></strong> function calls in <strong><?php $l = end($fullTrace);
					echo $l['timeOnEntry']; ?> seconds</strong>, using <strong><?= $l['memoryOnEntry'] ?> MB</strong> of memory.
					</td>
					<td></td>
					<td style="vertical-align:bottom"><small>in = start func.<br />out = end func.</small></td>
					<td style="vertical-align:bottom"><small>in = start func.<br />out = end func.</small></td>
				</tr>
				<tr>
					<th>Function / File</th>
					<th style="min-width: 8em;">Line</th>
					<th style="min-width: 8em;">Time</th>
					<th style="min-width: 8em;">Memory</th>
				</tr>
				<?php
				
				foreach ($fullTrace as $trace)
				{
					?>
				<tr>
						<td style="padding-left:<?= ($trace['level'] * 10) ?>px">
							<?php if ($trace['type'] == 0)
							{ ?><a target="_blank" href="http://php.net/<?= $trace['function'] ?>"><span class="native" title="PHP doc <?= $trace['function'] ?>">&#x261b; </span></a><?php
				}
				else
				{
					/**
					 * custom color identifier for ZendFramework methods
					 */
					if (substr($trace['function'], 0 , 5) == 'Zend_')
					{
						$userFunction = 'Zend';
					}
					/**
					 * And all Corretge namespace classes and methods
					 */
					elseif (substr($trace['function'], 0 , $XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE_LEN) == XDEBUG_TRACE_GUI_CUSTOM_NAMESPACE)
					{
						$userFunction = 'Corretge';
					}
					else
					{
						$userFunction = 'user';
					}
					
					
								?><span class="<?= $userFunction ?>" title="UDF ">&#x261b; </span><?php } ?><strong><?php if ($trace['type'] == 0)
					{ ?>\<?php } ?><?= $trace['function'] ?></strong><ul><?= $trace['valParms'] ?></ul><br />
							<small><?= $trace['filename'] ?></small>
							<span class="warning">
								<?php
								if ($trace['timeAlert'])
								{
									echo "<br />Warning, time jump exceeds trigger! {$trace['timeAlert']}";
								}
								if ($trace['memoryAlert'])
								{
									echo "<br />Warning, memory jump exceeds trigger! {$trace['memoryAlert']}";
								}
								?>
							</span>

						</td>
						<td class="digit line"><a href="#<?= $trace['line'] ?>"><?= $trace['line'] ?></a></td>
						<td class="digit" style="<?php if ($trace['timeAlert'])
						{ ?>background:maroon;color:white<?php } ?>">in: <?= $trace['timeOnEntry'] ?> s<br />out: <?= $trace['timeOnExit'] ?> s<br />
                                                <?php
                                                if (isset($trace['timeOnEntry']) and isset($trace['timeOnExit']))
                                                {
                                                    echo  number_format(($trace['timeOnExit'] - $trace['timeOnEntry']) * 1000000, 0) . ' µs';
                                                }
                                                ?></td>
						<td class="digit" style="<?php if ($trace['memoryAlert'])
						{ ?>background:maroon;color:white<?php } ?>">in: <?= $trace['memoryOnEntry'] ?> MB<br />out: <?= $trace['memoryOnExit'] ?> MB</td>
					</tr>
					<?php
				}
				?>
			</table>
			<?php
		}
		?>

		<script type="text/javascript">
			$(document).ready(function() {
				$('td.line a').each(function() {

					$(this).click(function() {
						showCode(this);
					})

				})
			})

			function showCode(where) {
				var file = $(where).parent().parent().find('small').text();
				window.open('trace-code.php?line=' + $(where).text() + '&file=' + file + '#l' + $(where).text(), 'code', 'width=500,height=400,toolbar=no,status=no,menubar=no,scrollbars=yes');
			}

		</script>
	</body>
</html>
