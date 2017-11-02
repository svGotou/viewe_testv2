<?php

//$str = file_get_contents("php://input");

$keys = array_keys($_POST);

$fileRec = "";
$cnt = count($keys);
//echo "項目数=".$cnt."<br />";
for ($i=0; $i<$cnt; $i++)
{
	//echo "POST[$keys[$i]]=".$_POST[$keys[$i]]."<br />";
	//$fileRec = $fileRec."POST[$keys[$i]]=".$_POST[$keys[$i]]."\n";
	if (strlen($fileRec) > 0) $fileRec = $fileRec.",";
	$fileRec = $fileRec.rawurldecode($_POST[$keys[$i]]);
}

//echo "fileRec=".$fileRec."\n";

$fp = fopen('channel_name48A.txt', 'wb');
if ($fp)
{
	if (flock($fp, LOCK_EX))
	{
		if (fwrite($fp,  $fileRec) === FALSE)
		{
			//echo ログファイルの書き込みに失敗しました。."<br />\n";
			writeLog2("[channelSave] ログファイルの書き込みに失敗しました。");
			echo "SAVE NG,001";
		}
		else
		{
			//echo ログファイルを書き込みました。."<br />\n";
			echo "SAVE OK";
		}
		flock($fp, LOCK_UN);
	}
	else  // if (flock($fp, LOCK_EX))
	{
		//echo ログファイルのロックに失敗しました。."<br />\n";
		writeLog2("[channelSave] ログファイルのロックに失敗しました。");
		echo "SAVE NG,002";
	}
	fclose($fp);
}
else
{
	//echo ログファイルのfopenに失敗しました。."<br />\n";
	writeLog2("[channelSave] ログファイルのfopenに失敗しました。");
	echo "SAVE NG,003";
}

function writeLog2($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}

// HTMLでのエスケープ処理をする関数
function h($string)
{ 
  return htmlspecialchars($string, ENT_QUOTES);
}

//////////////////////////////////////////////////////////
// added sending of config data to collector...
// wait 1000ms to make sure the file is there...
usleep(1000);
$path = str_replace("channelSave48A.php", "", __FILE__) . "/channel_name48A.txt";
$settings = file_get_contents($path);
$list = explode(",", $settings);
// read config file, too...
$path = str_replace("channelSave48A.php", "", __FILE__) . "config.json";
$configData = @file_get_contents($path);
if (!$configData) {
	$configData = "{}";
}
$configData = json_decode($configData, true);
$configData['ecocuteState'] = toBool($list[count($list) - 3]);
$configData['solarState'] = toBool($list[count($list) - 2]);
$configData['batteryState'] = toBool($list[count($list) - 1]);
$configData = json_encode($configData);
$protocol = 'http';
$host = 'collector.viewe.jp';
$uri = '/remote/config';
$url = $protocol . '://' . $host . $uri;
$uuidFilePath = '/etc/security/soundvision.d/uuid';
$uuid = @file_get_contents($uuidFilePath);
$body = array(
	'uuid' => clean($uuid),
	'value' => $configData
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

function toBool($val) {
        if ($val === "YES") {
                return true;
        }
        return false;
}
function clean($str) {
	return str_replace(array("\n", "\r"), '', $str);
}
///////////////////////////////////////
?>
