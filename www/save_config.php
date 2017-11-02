<?php

$path = str_replace("save_config.php", "", __FILE__) . "config.json";

$data = json_encode($_POST);

$fh = fopen($path, "wb");

if (!$fh) {
	// error....
	header("HTTP/1.1 500 Internal Server Error");
	echo "OFAILED";
	return;
}

$res = flock($fh, LOCK_EX);

if (!$res) {
	// error....
	header("HTTP/1.1 500 Internal Server Error");
	echo "LFAILED";
	return;
}

$res = fwrite($fh, $data);

flock($fh, LOCK_UN);

if (!$res) {
	// error....
	header("HTTP/1.1 500 Internal Server Error");
	echo "WFAILED";
	return;
}

/***
Send configurations to remote collector
**/

// read settings and extract solar and ecocute
$path = str_replace("save_config.php", "", __FILE__) . "/channel_name48A.txt";
$settings = file_get_contents($path);
$list = explode(",", $settings);
// extract solar and ecocute ONLY and add them to config data
$data2 = json_decode($data, true);
$data2['ecocuteState'] = toBool($list[count($list) - 2]);
$data2['solarState'] = toBool($list[count($list) - 1]);
$data = json_encode($data2);

$protocol = 'http';
$host = 'collector.viewe.jp';
$uri = '/remote/config';
$url = $protocol . '://' . $host . $uri;
$uuidFilePath = '/etc/security/soundvision.d/uuid';
$uuid = @file_get_contents($uuidFilePath);
$body = array(
	'uuid' => clean($uuid),
	'value' => $data
);
$opt = array();
$opt[$protocol] = array(
	'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
	'method'  => 'POST',
	'content' => http_build_query($body)
);
try {
	$context  = stream_context_create($opt);
	$res = file_get_contents($url, false, $context);
	if ($res === false) {
		throw new Exception('Failed to deliver data');
	}
} catch (Exception $error) {
	//error_log('Failed to send data:' . $error);
}

// done

/***
Utility function(s)
**/
function clean($str) {
	return str_replace(array("\n", "\r"), '', $str);
}

function toBool($val) {
	if ($val === "YES") {
		return true;
	}
	return false;
}

