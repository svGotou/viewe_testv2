<?php
	//http://192.168.1.100/eco_eye_day4_30mini.php?period=2015.01.17&cute=1&solar=1
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
		//
		$setInfoRec = getSetInfo();  // チャンネル名等の読み込み
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ

		$kwh = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);  // 電力(49item)
		$maxitem = 49;  // 24時間x2(30分間隔)+1(翌日)
		$kwh = array();
		for ($i=0; $i<$maxitem; ++$i)
			$kwh[$i] = 0;
		$cuteStr = $_GET['cute'];  // エコキュート有無
		$solarStr = $_GET['solar'];  // 太陽光有無
		$ymdStr = $_GET['period'];  // 指定日
		$yy1Str = mb_substr($ymdStr, 0, 4);
		$mm1Str = mb_substr($ymdStr, 5, 2);
		$dd1Str = mb_substr($ymdStr, 8, 2);
		$nextDay = date("Y-m-d",strtotime("1 day" ,strtotime($yy1Str.$mm1Str.$dd1Str)));  // 翌日
		$yy2Str = mb_substr($nextDay, 0, 4);
		$mm2Str = mb_substr($nextDay, 5, 2);
		$dd2Str = mb_substr($nextDay, 8, 2);  // 23:30台を求めるため、翌日00:00のkwhを求める為に必要
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10

		 // 読み取り範囲の日付
	 	$from_date1 = $yy1Str."-".$mm1Str."-".$dd1Str." 00:00:00";
		$from_date2 = $yy2Str."-".$mm2Str."-".$dd2Str." 00:05:00";

		// 今日のkwh読み込み
		$sql = "SELECT * FROM bundenban3 WHERE yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY channel, yymmddhms";
		//echo $sql."<br />";  // debug
		$query = mysql_query($sql, $db);
		while($rec = mysql_fetch_array($query))
		{
			//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
			$channel = intval($rec["channel"]);
			$dd = intval(mb_substr($rec["yymmddhms"], 8, 2));  // 日
			$hh = intval(mb_substr($rec["yymmddhms"], 11, 2));  // 時
			$mm = intval(mb_substr($rec["yymmddhms"], 14, 2));  // 分

			if ($sw1st == true)  // 最初の処理
			{
				$channel_old = $channel;
				$sw1st = false;
			}
			//echo "channel=".strval($channel)."<br />";
			//echo "channel_old=".strval($channel_old)."<br />";
			if ($channel == $channel_old)  // 同一チャンネル
			{
				if ($dd == intval($dd1Str))  // 当日00:00から23:30のデータ
				{
					if ($mm >= 0 && $mm <= 5)
						$kwh[$hh*2] = intval($rec["kwh"]);
					else if ($mm >= 30 && $mm <= 35)
						$kwh[$hh*2+1] = intval($rec["kwh"]);
				}
				else  // 翌日00:00のデータ
				{
					$kwh[$maxitem-1] = intval($rec["kwh"]);
				}
			}
			else  // 異なるチャンネル
			{
				// 前のチャンネル処理(出力)
				$senDataStr = $senDataStr."CHANNEL;CH".strval($channel_old).";".chr(0x0a);
				if ($channel_old == 0)
					$senDataStr = $senDataStr."NAME;主幹;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 101)
					$senDataStr = $senDataStr."NAME;エコキュート;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 100)
					$senDataStr = $senDataStr."NAME;売電量;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 102)
					$senDataStr = $senDataStr."NAME;発電量;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 103)
					$senDataStr = $senDataStr."NAME;太陽光機器消費量;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 104)
					$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前
				else if ($channel_old == 105)
					$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前
				else
					$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前

				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);

				// $kwh[$i]は累計値なので、差を求める
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
				{
					// 前の時間帯との差
					if ($kwh[$i+1]-$kwh[$i] > 0)
						$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
					else
						$senDataStr = $senDataStr."0.0;";
				}
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				//echo "senDataStr=".$senDataStr."<br />";

				// 今のチャンネル処理
				for ($i=0; $i<$maxitem; ++$i)  // 初期化
					$kwh[$i] = 0;
				if ($dd == intval($dd1Str))
				{
					if ($mm >= 0 && $mm <= 5)
						$kwh[$hh*2] = intval($rec["kwh"]);
					else if ($mm >= 30 && $mm <= 35)
						$kwh[$hh*2+1] = intval($rec["kwh"]);
				}
				else  // 翌日00:00のデータ
				{
					$kwh[$maxitem-1] = intval($rec["kwh"]);
				}

				 $channel_old = $channel;  // old key 更新
			}
		}  // while

		if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
		{
			// 前のチャンネル処理(出力)
			$senDataStr = $senDataStr."CHANNEL;CH".strval($channel_old).";".chr(0x0a);
			if ($channel_old == 0)
				$senDataStr = $senDataStr."NAME;主幹;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 101)
				$senDataStr = $senDataStr."NAME;エコキュート;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 100)
				$senDataStr = $senDataStr."NAME;売電量;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 102)
				$senDataStr = $senDataStr."NAME;発電量;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 103)
				$senDataStr = $senDataStr."NAME;太陽光機器消費量;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 104)
				$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前
			else if ($channel_old == 105)
				$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前
			else
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前

			//echo "from_date1=".mb_substr($from_date1, 0, 10)."<br />";
			$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);

			// $kwh[$i]は累計値なので、差を求める
			$senDataStr = $senDataStr."KWH;";
			for ($i=0; $i<$maxitem-1; ++$i)
			{
				// 前の時間帯との差
				if ($kwh[$i+1]-$kwh[$i] > 0)
					$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
				else
					$senDataStr = $senDataStr."0.0;";
			}
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

			echo $senDataStr;  // 全チャンネル送信
		}  // if (strlen(senDataStr) > 0)

		mysql_close($db);
	}  // DB準備OK

	// チャンネル名取得
	function getSetInfo()
	{
		$setInfoRec = "";
		$filepath = "channel_name48A.txt";
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		return $setInfoRec;
	}
?>
