<?php
	// 余計なタグを外してクリーンなデータのみiOSに返す
	//http://192.168.1.100/eco_eye_year4.php?period=2014&cute=1&solar=1
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
		$nextYear = date("Y-m-d",strtotime("1 year" ,strtotime($yy1Str."0101")));  // 翌年1月1日
		$yy2Str = mb_substr($nextYear, 0, 4);
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10
		
		// 読み取り範囲(ひと月)の日付
	 	$from_date1 = $yy1Str."-__-01 00:0_:__";
		$from_date2 = $yy2Str."-01-01 00:0_:__";
		//echo "from_date1=".$from_date1."<br />";
		//echo "from_date2=".$from_date2."<br />";

		$maxitem = 13;  // 12ヶ月+1(翌月)
		$kwh = array();
		for ($i=0; $i<$maxitem; ++$i) 
			$kwh[$i] = 0;
			
		// 今年のkwh読み込み
		//$sql = "SELECT * FROM bundenban3 WHERE channel = $chanArray[$chan] AND yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY yymmddhms DESC";
		$sql = "SELECT * FROM bundenban3 WHERE yymmddhms LIKE '$from_date1' OR yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";		
		//echo $sql."<br />";  // debug用
		$query = mysql_query($sql,$db);
		$count = mysql_num_rows($query);
		//echo "count=".strval($count)."<br />";
		while($rec = mysql_fetch_array($query))
		{
			//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
			$channel = intval($rec["channel"]);
			if ($channel <= $chanCnt || $channel > 99)  // チャンネル数以下または100チャンネル以上なら
			{
				$yy = intval(mb_substr($rec["yymmddhms"], 0, 4));  // 年
				$mm = intval(mb_substr($rec["yymmddhms"], 5, 2));  // 月
				
				if ($sw1st == true)  // 最初の処理
				{
					$channel_old = $channel;
					$sw1st = false;
				}
	
				if ($channel == $channel_old)  // 同一チャンネル
				{
					if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
						$kwh[$mm-1] = intval($rec["kwh"]);
					else  // 翌年1月1日00:00のデータ
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
	
					$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
					$senDataStr = $senDataStr."PERIOD;";
					for ($j=1; $j<=$maxitem-1; ++$j)
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
					if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
						$kwh[$mm-1] = intval($rec["kwh"]);
					else  // 翌年1月1日00:00のデータ
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
				
			$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;";
			for ($j=1; $j<=$maxitem-1; ++$j)
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
	}
	
	function getSetInfo()
	{
		$setInfoRec = "";
		$filepath = "channel_name48A.txt";
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		return $setInfoRec;
	}
?>
