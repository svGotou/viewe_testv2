<?php
	// http://192.168.1.100/passwordSave.php?userid=abc&password=12345
	// LOAD OK,abc,12345

	$filepath = "userid_password.txt";
	if (file_exists($filepath))  // 在る
	{
		$dataStr = file_get_contents($filepath);
		if (strlen($dataStr) > 0)
		{
			$dataArray = explode(",", $dataStr);  // 戻り値を配列に
			if (count($dataArray) == 2)
			{
				if (strlen($dataArray[0]) > 0 && strlen($dataArray[1]))
					echo "PASSWORD LOAD OK".",".$dataStr;
				else
					echo "PASSWORD LOAD NG,004";
			}
			else
			{
				echo "PASSWORD LOAD NG,003";
			}
		}
		else
		{
			writeLog2("[passwordLoad] データ長がゼロです。");
			echo "PASSWORD LOAD NG,002";
		}
	}
	else
	{
		writeLog2("[passwordLoad] userid_password.txtが在りません。");
		echo "PASSWORD LOAD NG,001";
	}
	
function writeLog2($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}

?>