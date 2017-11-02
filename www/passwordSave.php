<?php
// http://192.168.1.100/passwordSave.php?userid=abc&password=12345
// PASSWORD SAVE OK

$fileRec = $_GET['userid'].",".$_GET['password'];
//echo "fileRec=".$fileRec."\n";

$fp = fopen('userid_password.txt', 'wb');
if ($fp)
{
	if (flock($fp, LOCK_EX))
	{
		if (fwrite($fp,  $fileRec) === FALSE)
		{
			//echo ログファイルの書き込みに失敗しました。."<br />\n";
			writeLog2("[passwordSave] ログファイルの書き込みに失敗しました。");
			echo "PASSWORD SAVE NG,001";
		}
		else
		{
			//echo ログファイルを書き込みました。."<br />\n";
			echo "PASSWORD SAVE OK";
		}
		flock($fp, LOCK_UN);
	}
	else  // if (flock($fp, LOCK_EX))
	{
		//echo ログファイルのロックに失敗しました。."<br />\n";
		writeLog2("[passwordSave] ログファイルのロックに失敗しました。");
		echo "PASSWORD SAVE NG,002";
	}
	fclose($fp);
}
else
{
	//echo ログファイルのfopenに失敗しました。."<br />\n";
	writeLog2("[passwordSave] ログファイルのfopenに失敗しました。");
	//echo "PASSWORD SAVE NG,003";
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
?>