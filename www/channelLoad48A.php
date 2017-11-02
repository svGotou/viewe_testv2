<?php
	$filepath = "channel_name48A.txt";
	if (file_exists($filepath))  // 在る
	{
		$dataStr = file_get_contents($filepath);
		if (strlen($dataStr) > 0)
		{
			echo "LOAD OK".",".$dataStr;
		}
		else
		{
			writeLog2("[channelLoad] データ長がゼロです。");
			echo "LOAD NG,001";
		}
	}
	else
	{
		writeLog2("[channelLoad] channel_name48.txtが在りません。");
		echo "LOAD NG,002";
	}
	
function writeLog2($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}

?>