<?php
	// eco_eye01.phpから余計なタグを外してクリーンなデータのみiOSに返す
	#DB処理
	$flag = TRUE;
	require_once 'db_info.php';  // DBの情報
	if (! $db = mysql_connect("localhost", $userStr, $pathStr))
	{
		$flag = FALSE;
	}
	
	if ($flag == TRUE)
	{
		if (! mysql_select_db("ECOEyeDB01",$db))
		{
			$flag = FALSE;
		}
	}

	if ($flag == TRUE)  // DB準備OK
	{
		$setInfoRec = getSetInfo();  // 設定情報の読み込み
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
		$maxChan = 16+4*intval($setInfoArr[0]);  // チャンネル数 ($setInfoArr[0]は分電盤タイプの0,1,2・・・)
		
		$cnt = count($setInfoArr);
		$cuteStr = $setInfoArr[$cnt-2];  // エコキュート有無
		//echo $cuteStr."<br />";
		$solarStr = $setInfoArr[$cnt-1];  // 太陽光有無
		//echo $solarStr."<br />";
		
		// チャンネルの配列を作る
		$chanArray = array();
		for ($chan=0; $chan<=$maxChan; ++$chan)
		{
			$chanArray[] = $chan;
		}
		if ($cuteStr == "YES")  // エコキュートあり
		{
			$chanArray[] = 101;
			$maxChan += 1;
		}
		if ($solarStr == "YES")  // 太陽光あり
		{
			$chanArray[] = 100;
			$chanArray[] = 102;
			$chanArray[] = 103;
			$maxChan += 3;
		}
		
		//echo "maxChan=".strval($maxChan)."<br />";
		//writeLog2("maxChan=".strval($maxChan));
		//var_dump( $chanArray ) ;
		//echo "<br />";

		$sqlDate_y = date("Y");  // 現在の年
		//echo $sqlDate_y."<br />";
		$sqlDate_m = date("m");  // 現在の月
		//echo $sqlDate_m."<br />";
		$sqlDate_d = date("d");  // 現在の日
		//echo $sqlDate_d."<br />";
		$sqlDate_h = date("H");  // 現在の時
		//echo $sqlDate_h."<br />";
		$sqlDate_i = date("i");  // 現在の分
		//echo $sqlDate_i."<br />";
		if (intval($sqlDate_i) < 30)
		{
			$sqlDate_i1 = "00";
			$sqlDate_i2 = "01";
		}
		else
		{
			$sqlDate_i1 = "30";
			$sqlDate_i2 = "31";
		}
		$ymd1 = $sqlDate_y."-".$sqlDate_m."-".$sqlDate_d." ".$sqlDate_h.":".$sqlDate_i1.":00";
		$ymd2 = $sqlDate_y."-".$sqlDate_m."-".$sqlDate_d." ".$sqlDate_h.":".$sqlDate_i2.":59";
		//echo $ymd1."<br />";
		//echo $ymd2."<br />";
		
		for ($chan=0; $chan<=$maxChan; ++$chan)  // 本番は48chanまで(0CHは主幹、1〜48CHは分岐)
		{
			$sql = "SELECT * FROM bundenban3 WHERE channel = $chanArray[$chan] AND yymmddhms >= '$ymd1' AND yymmddhms <= '$ymd2' ORDER BY yymmddhms DESC";
			$query = mysql_query($sql,$db);
			$query_count = mysql_num_rows($query); 
			//echo "query_count=".strval($query_count)."<br />";
			//writeLog2("chan=".strval($chan));
			//writeLog2("chanArray=".$chanArray[$chan]);
			if ($query_count == 0)  // 分電盤からのデータ無かった
			{
				//echo strval($chanArray[$chan])."チャンネルのデータ無かった。".$ymd1."<br />";
				writeLog2(strval($chanArray[$chan])."チャンネルのデータ無かった。".$ymd1);
				//30分前のデータ在るか？
				$ymd1_b = date( 'Y-m-d H:i:s', strtotime( '-30 minute',  strtotime($ymd1)));  // 30分前
				$ymd2_b = date( 'Y-m-d H:i:s', strtotime( '-30 minute',  strtotime($ymd2)));  // 30分前
				//echo $ymd1_b."<br />";
				//echo $ymd2_b."<br />";
				$sql_b = "SELECT * FROM bundenban3 WHERE channel = $chanArray[$chan] AND yymmddhms >= '$ymd1_b' AND yymmddhms <= '$ymd2_b' ORDER BY yymmddhms DESC";
				$query_b = mysql_query($sql_b, $db);
				$query_count_b = mysql_num_rows($query_b); 
				//echo "query_count_b=".strval($query_count_b)."<br />";
				if ($query_count_b > 0)  //30分前のデータ在った
				{
					//echo strval($chanArray[$chan])."チャンネルの30分前データ在った。".$ymd1_b."<br />";
					writeLog2(strval($chanArray[$chan])."チャンネルの30分前データ在った。".$ymd1_b);
					while($rec_b = mysql_fetch_array($query_b))
					{
						$kwh_b = $rec_b["kwh"];
					}
					//30分前と同じKWH値のデータ追加
					$sql_s = "INSERT INTO bundenban3 (yymmddhms, channel, kwh) VALUES ('$ymd2',$chanArray[$chan], $kwh_b)";
					//echo "sql_s=".$sql_s."<br />";
					//writeLog2($sql_s);  // 2016.6.16
					$query_s = mysql_query($sql_s, $db);
					$errNo = mysql_errno($db);
					if ($errNo)
					{
						//echo mysql_errno($db) . ": " . mysql_error($db) . "<br>";
						writeLog2(mysql_errno($db) . ": " . mysql_error($db));  // 2016.6.16
					}
				}
				else  //30分前のデータ無かった
				{
					//echo strval($chanArray[$chan])."チャンネルの30分前データ無かった。".$ymd1_b."<br />";
					writeLog2(strval($chanArray[$chan])."チャンネルの30分前データ無かった。".$ymd1_b);
				}
			}  // if ($query_count == 0)
			else
			{
				//echo strval($chanArray[$chan])."チャンネルのデータ在った。".$ymd1."<br />";
				//writeLog2(strval($chanArray[$chan])."チャンネルのデータ在った。".$ymd1);
			}
		}  // for ($chan=0; $chan<=$maxChan; ++$chan)
		mysql_close($db);    // DBファイルを閉る
	}  // if ($flag == TRUE)  // DB準備OK

	
	function getSetInfo()
	{
		$setInfoRec = "";
		//$filepath = "channel_name48A.txt";
		$filepath = "/var/www/channel_name48A.txt";
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		return $setInfoRec;
	}
	
	function writeLog2($logStr)
	{
		error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
	}


/*YES
YES
2015-01-19 15:30:00
2015-01-19 15:30:59
query_count=0
0チャンネルのデータ無かった。2015-01-19 15:30:00
2015-01-19 15:00:00
2015-01-19 15:00:59
query_count_b=2
0チャンネルの30分前データ在った。2015-01-19 15:00:00
sql_s=INSERT INTO bundenban3 (yymmddhms, channel, kwh) VALUES ('2015-01-19 15:30:59',0, 3097507)
query_count=1
1チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
2チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
3チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
4チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
5チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
6チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
7チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
8チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
9チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
10チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
11チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
12チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
13チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
14チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
15チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
16チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
17チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
18チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
19チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
20チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
101チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
100チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
102チャンネルのデータ在った。2015-01-19 15:30:00
query_count=1
103チャンネルのデータ在った。2015-01-19 15:30:00*/

?>