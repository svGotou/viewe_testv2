<?php

$path = str_replace("read_settings.php", "", __FILE__) . "/channel_name48A.txt";
$data = file_get_contents($path);

if (!$data) {
	// error....
	header("HTTP/1.1 500 Internal Server Error");
	echo "";
	return;
}

$list = explode(",", $data);

$map = [
	"board" => $list[0],
	"channels" => getChannels($list),
	"ecocute" => toBool($list[count($list) - 3]),
	"solar" => toBool($list[count($list) - 2]),
	"battery" => toBool($list[count($list) - 1])
];

echo json_encode($map);

function getChannels($_list) {
	$boardMap = [
		"0" => 16,
		"1" => 20,
		"2" => 24,
		"3" => 28,
		"4" => 32,
		"5" => 36,
		"6" => 40
	];
	$to = $boardMap[$_list[0]];
	$res = [];
	for ($i = 1; $i < $to; $i++) {
		$res[] = $_list[$i];
	}
	return $res;
}

function toBool($val) {
	if ($val === "YES") {
		return true;
	}
	return false;
}

