<!-- テストバージョン = 受信したデータをtextファイルに書き出す -->
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
</head>

<?php

$keys = array_keys($_GET);

$fileRec = "\n";
$cnt = count($keys);
echo "項目数=".$cnt."<br />";
for ($i=0; $i<$cnt; $i++)
{
	echo "GET[$keys[$i]]=".$_GET[$keys[$i]]."<br />";
	$fileRec = $fileRec."GET[$keys[$i]]=".$_GET[$keys[$i]]."\n";
}

echo "fileRec=".$fileRec."\n";

$fp = fopen('sip_text.txt', 'ab');
if ($fp)
{
	if (flock($fp, LOCK_EX))
	{
		if (fwrite($fp,  $fileRec) === FALSE)
		{
			echo ログファイルの書き込みに失敗しました."<br />\n";
		}
		else
		{
			echo ログファイルを書き込みました."<br />\n";
		}
		flock($fp, LOCK_UN);
	}
	else  // if (flock($fp, LOCK_EX))
	{
		echo ログファイルのロックに失敗しました。."<br />\n";
	}
	fclose($fp);
}
else
{
	echo fopenに失敗しました。."<br />\n";
}


// HTMLでのエスケープ処理をする関数
function h($string)
{ 
  return htmlspecialchars($string, ENT_QUOTES);
}
?>

</html>