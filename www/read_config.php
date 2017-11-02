<?php

$path = str_replace("read_config.php", "", __FILE__) . "/config.json";

$data = file_get_contents($path);

if (!$data) {
	// error....
        header("HTTP/1.1 500 Internal Server Error");
        echo "";
        return;
}

echo $data;
