<?php
	// 余計なタグを外してクリーンなデータのみiOSに返す
	//http://192.168.1.100/eco_eye_month4.php?period=2015.01&cute=1&solar=1
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
		$setInfoRec = getSetInfo();  // チャンネル名等の読み込み
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
		$chanCnt = count($setInfoArr)-3;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
		//echo "chanCnt=".strval($chanCnt)."<br />";

		$cuteStr = $_GET['cute'];  // エコキュート有無
		$solarStr = $_GET['solar'];  // 太陽光有無
		$ymdStr = $_GET['period'];  // 指定日
		$yy1Str = mb_substr($ymdStr, 0, 4);
		$mm1Str = mb_substr($ymdStr, 5, 2);
		$nextMonth = date("Y-m-d",strtotime("1 month" ,strtotime($yy1Str.$mm1Str."01")));  // 翌日1日
		$yy2Str = mb_substr($nextMonth, 0, 4);
		$mm2Str = mb_substr($nextMonth, 5, 2);
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10
		
		// 読み取り範囲(ひと月)の日付
	 	$from_date1 = $yy1Str."-".$mm1Str."-__ 00:0_:__";
		$from_date2 = $yy2Str."-".$mm2Str."-01 00:0_:__";
		//echo "from_date1=".$from_date1."<br />";
		//echo "from_date2=".$from_date2."<br />";
		
		// 年月からmaxitemを求める
		if ($mm1Str == 1 || $mm1Str == 3 || $mm1Str == 5 || $mm1Str == 7 || $mm1Str == 8 || $mm1Str == 10 || $mm1Str == 12)  // 31日
			$maxitem = 31;  // 31日
		else if ($mm1Str == 4 || $mm1Str == 6 || $mm1Str == 9 || $mm1Str == 11)  // 30日
			$maxitem = 30;  // 31日
		else  if (checkdate(2, 29, intval($yy1Str)))  // 閏年
			$maxitem = 29;
		else
			$maxitem = 28;
			
		$maxitem = $maxitem + 1;  // 翌月1日分(00:05:00のみ)を加算
		//echo "maxitem=".strval($maxitem)."<br />";
		
		$kwh = array();
		for ($i=0; $i<$maxitem; ++$i) 
			$kwh[$i] = 0;

		// 今月のkwh読み込み
		//$sql = "SELECT * FROM bundenban3 WHERE yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY channel, yymmddhms";		
		$sql = "SELECT * FROM bundenban3 WHERE yymmddhms LIKE '$from_date1' OR yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";		
		$query = mysql_query($sql,$db);
		$count = mysql_num_rows($query);
		//echo "count=".strval($count)."<br />";
		while($rec = mysql_fetch_array($query))
		{
			//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
			$channel = intval($rec["channel"]);
			
			$mm = intval(mb_substr($rec["yymmddhms"], 5, 2));  // 月
			$dd = intval(mb_substr($rec["yymmddhms"], 8, 2));  // 日
			if ($channel <= $chanCnt || $channel > 99)  // チャンネル数以下または100チャンネル以上なら
			{
				if ($sw1st == true)  // 最初の処理
				{
					$channel_old = $channel;
					$sw1st = false;
				}
				
				if ($channel == $channel_old)  // 同一チャンネル
				{
					if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
						$kwh[$dd-1] = intval($rec["kwh"]);
					else  // 翌月1日00:00のデータ
						$kwh[$maxitem-1] = intval($rec["kwh"]);
				}
				else // 異なるチャンネル 
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
					else
						$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前
						
					$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
					$senDataStr = $senDataStr."PERIOD;";
					for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
						$senDataStr = $senDataStr.strval($j).";";
					$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				
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
					if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
						$kwh[$dd-1] = intval($rec["kwh"]);
					else  // 翌月1日00:00のデータ
						$kwh[$maxitem-1] = intval($rec["kwh"]);
	
					 $channel_old = $channel;  // old key 更新
				}
			}  // if ($channel <= $chanCnt || $channel > 99)
		}  // while
			
		if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
		{
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
			else
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前
				
			$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;";
			for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
				$senDataStr = $senDataStr.strval($j).";";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
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
	
	function getSetInfo()
	{
		$setInfoRec = "";
		$filepath = "channel_name48A.txt";
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		return $setInfoRec;
	}
?>
