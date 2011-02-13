<?php
require 'trace.config.php';
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
        <table>
            <?php
            if (!isset($_GET['file']) || !file_exists($_GET['file'])) {
                die('File not found...');
            }

            $lines = file_get_contents($_GET['file']);
            $lines = explode("\n", $lines);
            $i = 1;
            foreach ($lines as $line) {

                $class = '';
                if ($i > ($_GET['line'] - 4) && $i < $_GET['line']) {
                    $class = 'near';
                }
                if ($i < ($_GET['line'] + 4) && $i > $_GET['line']) {
                    $class = 'near';
                }
                if ($i == $_GET['line']) {
                    $class = 'line';
                }

                echo '<tr class="' . $class . '"><td class="digit"><a name="l' . ($i) . '">' . ($i) . '</a></td><td>' . str_replace('&lt;?php&nbsp;', '', highlight_string('<?php ' . $line, true)) . '</td></tr>';
                $i++;
            }
            ?>
        </table>
    </body>
</html>