<?php
	// CSVファイルを生成して、ファイル名を返す。NGの時、"ERROR 999"を返す。
	// 12か月分の時 http://192.168.1.100/dump_csv.php?ymd=2016
	// 12か月分の時 http://viewe-iwate.ddo.jp/dump_csv.php?ymd=2016
	// 31日分の時 http://viewe-iwate.ddo.jp/dump_csv.php?ymd=201611
	// 24時間分の時 http://viewe-iwate.ddo.jp/dump_csv.php?ymd=20161108
	
	// モード決定
	$ymdStr = $_GET['ymd'];  // 指定日
	$doMode = 0;  // モード
	if (strlen($ymdStr) == 4)  // 年モード(12か月分)
		$doMode = 1;
	elseif (strlen($ymdStr) == 6)  // 月モード(31日分)
		$doMode = 2;
	elseif (strlen($ymdStr) == 8)  // 日モード(24時間分)
		$doMode = 3;
	
	if (! ctype_digit($ymdStr))  // 年月が数字でない
	{
		echo "ERROR 000";
		die();
	}
	if ($doMode == 0)  // モードが決定しなかった
	{
		echo "ERROR 000";
		die();
	}

	#DB処理
	$flag = TRUE;
	//require_once '/db_info.php';  // DBの情報

	$userStr = 'root';
	$pathStr = 'soundvision';

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
		
		// 分電盤の初期値を得る・ここから
		$filepath = "initial_data.txt";  // 2014/8/18からが妥当-->2014/8/17 23:50の値をセット
		$inti_umu = FALSE;
		if (file_exists($filepath))  // 在る
		{
			$dataStr = file_get_contents($filepath);
			if (strlen($dataStr) > 0)
			{
				$inti_umu = TRUE;
			}
		}
		else
		{
			//echo "ファイル無し<br>";
		}
		
		$initArray = array();
		if ($inti_umu == TRUE)
		{
			$dataArray = array();
			$dataArray = explode(",", $dataStr);  // 戻り値(チャンネル,値,・・・)を配列に
			//print_r($dataArray);
			//echo "<br>";
			
			$dataCnt = count($dataArray) / 2;
			for ($i=0; $i<$dataCnt; ++$i)
			{
				$idx = intval($dataArray[$i*2]);
				//echo $idx . "<br>";
				$initArray[$idx] = intval($dataArray[$i*2+1]);
			}
		}
		else  // "initial_data.txt"に有効なデータが無かった
		{
			for ($i=0; $i<=40; ++$i)  // 標準チャンネル(max40)
				$initArray[$i] = 0;
			for ($i=100; $i<=104; ++$i)  // スペシャルチャンネル
				$initArray[$i] = 0;
		}
		//print_r($initArray);
		//echo "<br>";
		// 分電盤の初期値を得る・ここまで

		$thisDate = date('Y-m-d  H:i:s');  // 今の日時
		$thisYY = date('Y');   // 今年
		$thisMM = date('m');   // 今月
		$thisDD = date('d');   // 今日
		$thisHM = date('H:i');  // 今の時分
		$thisHH = mb_substr($thisDate, 11, 2);
		$thisMin = mb_substr($thisDate, 14, 2);
		//echo "thisDate=".$thisDate."<br />";
		//echo "thisYY=".$thisYY."<br />";
		//echo "thisMM=".$thisMM."<br />";
		//echo "thisDD=".$thisDD."<br />";
		//echo "thisHM=".$thisHM."<br />";
		
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10
		$yy1Str = mb_substr($ymdStr, 0, 4);
		
		if ($doMode == 1)  // 年モード(12か月分)
		{
			$nextYear = date("Y-m-d",strtotime("1 year" ,strtotime($yy1Str."0101")));  // 翌年1月1日
			$yy2Str = mb_substr($nextYear, 0, 4);
			$maxitem = 13;  // 12ヶ月+1(翌月)
			
			// 読み取り範囲(ひと月)の日付
	 		$from_date1 = $yy1Str."-__-01 00:0_:__";  // これだと今4月の時、5/1のデータが無いので、4月の値はゼロになってしまう
			$from_date2 = $yy2Str."-01-01 00:0_:__";
		}
		elseif ($doMode == 2)  // 月モード(31日分)
		{
			$mm1Str = mb_substr($ymdStr, 4, 2);  // $ymdStrは"201612"といった内容なので注意
			$nextMonth = date("Y-m-d",strtotime("1 month" ,strtotime($yy1Str.$mm1Str."01")));  // 翌日1日
			$yy2Str = mb_substr($nextMonth, 0, 4);
			$mm2Str = mb_substr($nextMonth, 5, 2);
			
			// 年月からmaxitemを求める
			if ($mm1Str == 1 || $mm1Str == 3 || $mm1Str == 5 || $mm1Str == 7 || $mm1Str == 8 || $mm1Str == 10 || $mm1Str == 12)  // 31日
				$maxitem = 31;  // 31日
			elseif ($mm1Str == 4 || $mm1Str == 6 || $mm1Str == 9 || $mm1Str == 11)  // 30日
				$maxitem = 30;  // 31日
			elseif (checkdate(2, 29, intval($yy1Str)))  // 閏年
				$maxitem = 29;
			else
				$maxitem = 28;
			$maxitem = $maxitem + 1;  // 翌月1日分(00:05:00のみ)を加算
			
			// 読み取り範囲(ひと月)の日付
		 	$from_date1 = $yy1Str."-".$mm1Str."-__ 00:0_:__";
			$from_date2 = $yy2Str."-".$mm2Str."-01 00:0_:__";
		}
		elseif ($doMode == 3)  // 日モード(24時間分)
		{
			$mm1Str = mb_substr($ymdStr, 4, 2);  // $ymdStrは"20161209"といった内容なので注意
			$dd1Str = mb_substr($ymdStr, 6, 2);
			$nextDay = date("Y-m-d",strtotime("1 day" ,strtotime($yy1Str.$mm1Str.$dd1Str)));  // 翌日
			$yy2Str = mb_substr($nextDay, 0, 4);
			$mm2Str = mb_substr($nextDay, 5, 2);
			$dd2Str = mb_substr($nextDay, 8, 2);  // 23:30台を求めるため、翌日00:00のkwhを求める為に必要
			$maxitem = 25;  // 24時間+1(翌日)
			
			// 読み取り範囲の日付
	 		$from_date1 = $yy1Str."-".$mm1Str."-".$dd1Str." 00:00:00";
			$from_date2 = $yy2Str."-".$mm2Str."-".$dd2Str." 00:05:00";  // 翌月1日分(00:05:00のみ)
		}
		
		//echo "from_date1=".$from_date1."<br />";
		//echo "from_date2=".$from_date2."<br />";
		//echo "maxitem=".strval($maxitem)."<br />";
		
		$kwh = array();
		for ($i=0; $i<$maxitem; ++$i) 
			$kwh[$i] = 0;
		
		if ($doMode == 1 || $doMode == 2)
		{
			// 今日の最新kwhを取得
			$newCalc = FALSE;
			$newkwh = array();
			$thisDate = date('Y-m-d H:i:s');  // 今の日時 2015-04-09 15:12:14  // 09と15の間が2byteだ
			$thisMin = mb_substr($thisDate, 15, 2);
			if ($thisMin == "00")  // 00分のデータはまだ無い可能性があるので
				$thisDate = date("Y-m-d H:i:s", strtotime("-1 minute"));  // 1分前にする
			$thisYMDH = mb_substr($thisDate, 0, 14);
			$thisYY = mb_substr($thisDate, 0, 4);
			$thisMM = mb_substr($thisDate, 5, 2);
			$thisDD = mb_substr($thisDate, 8, 2);
			$thisHM = mb_substr($thisDate, 11, 5);
			$thisHH = mb_substr($thisDate, 11, 2);
			$thisMin = mb_substr($thisDate, 14, 2);
			//if ($yy1Str == $thisYY)  // 今年が指定されていて
			if (($doMode == 1 && $yy1Str == $thisYY) || ($doMode == 2 && $yy1Str == $thisYY && $mm1Str == $thisMM))  // 今年/今月が指定されていて
			{
				if (!($thisDD == "01" && $thisHM < "00:30"))  // 今日が1日で00:30になっていないなら
				{
					$newCalc = TRUE;
					if ($thisMin >= "31")
						$thisMin = "30";
					else  // "01"〜"30" ("00"の値はここには来ない。"59"に変換されているから)
						$thisMin = "00";
					$new_date = $thisYMDH.$thisMin.":00";
					$sql2 = "SELECT * FROM bundenban3 WHERE yymmddhms >= '$new_date' ORDER BY channel, yymmddhms";
					//echo "sql2=".$sql2."<br />";
					$query2 = mysql_query($sql2, $db);
					$count2 = mysql_num_rows($query2);
					//echo "count2=".strval($count2)."<br />";
					while($rec2 = mysql_fetch_array($query2))
					{
						//echo "channel=".$rec2['channel']." yymmddhms=".$rec2['yymmddhms']." kwh=".$rec2['kwh']."<br />";  // debug
						$newkwh[$rec2['channel']]  = $rec2["kwh"];  // 万一、同一チャネルが二つあっても、最新が入る
					}
				}
			}
		}
		
		// 今年/今月/今日のkwh読み込み
		if ($doMode == 1 || $doMode == 2)
			$sql = "SELECT * FROM bundenban3 WHERE yymmddhms LIKE '$from_date1' OR yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";
		else  // $doMode == 3
			$sql = "SELECT * FROM bundenban3 WHERE yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY channel, yymmddhms";
		//echo $sql."<br />";  // debug用
		$query = mysql_query($sql,$db);
		$recNum = mysql_num_rows($query);
		//echo "count=".strval($recNum)."<br />";
		if ($recNum > 0)
		//if ($recNum == 0)  // DEBUG
		{
			while($rec = mysql_fetch_array($query))
			{
				//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
				$channel = intval($rec["channel"]);
				
				if ($channel <= $chanCnt || $channel > 99)  // チャンネル数以下または100チャンネル以上なら
				{
					$yy = intval(mb_substr($rec["yymmddhms"], 0, 4));  // 年
					$mm = intval(mb_substr($rec["yymmddhms"], 5, 2));  // 月
					$dd = intval(mb_substr($rec["yymmddhms"], 8, 2));  // 日
					$hh = intval(mb_substr($rec["yymmddhms"], 11, 2));  // 時
					$min = intval(mb_substr($rec["yymmddhms"], 14, 2));  // 分
					
					if ($sw1st == true)  // 最初の処理
					{
						$channel_old = $channel;
						$sw1st = false;
						
						// 見出し行
						if ($doMode == 1)  // 年モード(12か月分)
						{
							$senDataStr = ",".$yy1Str."年,";
							for ($j=1; $j<=12; ++$j)
								$senDataStr = $senDataStr.strval($j)."月,";
						}
						elseif ($doMode == 2)  // 月モード(31日分)
						{
							$senDataStr = ",".$yy1Str."年".$mm1Str."月,";
							for ($j=1; $j<=$maxitem-1; ++$j)
								$senDataStr = $senDataStr.strval($j)."日,";
						}
						elseif ($doMode == 3)  // 日モード(24時間分)
						{
							$senDataStr = ",".$yy1Str."年".$mm1Str."月".$dd1Str."日,";
							for ($j=0; $j<24; ++$j)
								$senDataStr = $senDataStr.strval($j)."時,";
						}
						$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
					}
		
					if ($channel == $channel_old)  // 同一チャンネル
					{
						if ($doMode == 1)
						{
							if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
								$kwh[$mm-1] = intval($rec["kwh"]);
							else  // 翌年1月1日00:00のデータ
								$kwh[$maxitem-1] = intval($rec["kwh"]);
						}
						elseif ($doMode == 2)
						{
							if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
								$kwh[$dd-1] = intval($rec["kwh"]);
							else  // 翌月1日00:00のデータ
								$kwh[$maxitem-1] = intval($rec["kwh"]);
						}
						elseif ($doMode == 3)
						{
							if ($dd == intval($dd1Str))  // 当日00:00から23:30のデータ
							{
								if ($min >= 0 && $min <= 5)  // 同じ時台に2件あるので最初の方
									$kwh[$hh] = intval($rec["kwh"]);
							}
							else  // 翌日00:00のデータ
							{
								$kwh[$maxitem-1] = intval($rec["kwh"]);
							}
						}
					}
					else // 異なるチャンネル 
					{
						// 今年が指定されていて、各チャンネルの今月の最新値(今の時刻から)を翌月1日の値にする @@@@
						// ただし、今月が1日で00:30になっていないなら、この処理は不要
						// 現在の日時を取得 2015/04/04なら2015/04/04の最新時刻の値を取得
						// $channel_oldチャネルの今日の日付で降順に読んで1つ目のデータ
						//if ($yy1Str == $thisYY)  // 今年が指定されていて
						//{
							//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今月が1日で00:30になっていないなら
							if ($newCalc == TRUE)  // 最新値をセットしなさい ($doMode == 1 || $doMode == 2の時)
							{
								if ($doMode == 1)
									$kwh[intval($thisMM)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
								elseif ($doMode == 2)
									$kwh[intval($thisDD)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
							}
						//}
						
						// 前のチャンネル処理(出力)
						$senDataStr = $senDataStr.strval($channel_old).",";
						if ($channel_old == 0)
							$senDataStr = $senDataStr."主幹,";  // ファイルに無い名前
						else if ($channel_old == 101)
							$senDataStr = $senDataStr."エコキュート,";  // ファイルに無い名前
						else if ($channel_old == 100)
							$senDataStr = $senDataStr."売電量,";  // ファイルに無い名前
						else if ($channel_old == 102)
							$senDataStr = $senDataStr."発電量,";  // ファイルに無い名前
						else if ($channel_old == 103)
							$senDataStr = $senDataStr."太陽光機器消費量,";  // ファイルに無い名前
						else
							$senDataStr = $senDataStr.strval($setInfoArr[$channel_old]).",";  // ファイルに在る名前
						
						// $kwh[$i]は累計値なので、差を求める
						for ($i=0; $i<$maxitem-1; ++$i)
						{
							$after = $kwh[$i+1] - $initArray[$channel_old];
							if ($after < 0) $after = 0;
							$before = $kwh[$i] - $initArray[$channel_old];
							if ($before < 0) $before = 0;
							$sa = $after - $before;

							// 前の時間帯との差
							if ($sa > 0)
								$senDataStr = $senDataStr.strval($sa/1000).",";  // kwhを付加
							else
								$senDataStr = $senDataStr."0.0,";
						}
						$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
		
						// 今のチャンネル処理
						for ($i=0; $i<$maxitem; ++$i)  // 初期化
							$kwh[$i] = 0;
						if ($doMode == 1)
						{
							if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
								$kwh[$mm-1] = intval($rec["kwh"]);
							else  // 翌年1月1日00:00のデータ
								$kwh[$maxitem-1] = intval($rec["kwh"]);
						}
						elseif ($doMode == 2)
						{
							if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
								$kwh[$dd-1] = intval($rec["kwh"]);
							else  // 翌月1日00:00のデータ
								$kwh[$maxitem-1] = intval($rec["kwh"]);
						}
						elseif ($doMode == 3)
						{
							if ($dd == intval($dd1Str))  // 当日00:00から23:30のデータ
							{
								if ($min >= 0 && $min <= 5)  // 同じ時台に2件あるので最初の方
									$kwh[$hh] = intval($rec["kwh"]);
							}
							else  // 翌日00:00のデータ
							{
								$kwh[$maxitem-1] = intval($rec["kwh"]);
							}
						}
						$channel_old = $channel;  // old key 更新
					}  // 異なるチャンネル 
				}  // if ($channel <= $chanCnt || $channel > 99)
			}  // while($rec = mysql_fetch_array($query))
			
			if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
			{
					//if ($yy1Str == $thisYY)  // 今年が指定されていて
					//{
						//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今月が1日で00:30になっていないなら
						if ($newCalc == TRUE)  // 最新値をセットしなさい ($doMode == 1 || $doMode == 2の時)
						{
							if ($doMode == 1)
								$kwh[intval($thisMM)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
							elseif ($doMode == 2)
								$kwh[intval($thisDD)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
						}
					//}
	
				$senDataStr = $senDataStr.strval($channel_old).",";
				if ($channel_old == 0)
					$senDataStr = $senDataStr."主幹,";  // ファイルに無い名前
				else if ($channel_old == 101)
					$senDataStr = $senDataStr."エコキュート,";  // ファイルに無い名前
				else if ($channel_old == 100)
					$senDataStr = $senDataStr."売電量,";  // ファイルに無い名前
				else if ($channel_old == 102)
					$senDataStr = $senDataStr."発電量,";  // ファイルに無い名前
				else if ($channel_old == 103)
					$senDataStr = $senDataStr."太陽光機器消費量,";  // ファイルに無い名前
				else
					$senDataStr = $senDataStr.strval($setInfoArr[$channel_old]).",";  // ファイルに在る名前
					
				// $kwh[$i]は累計値なので、差を求める
				for ($i=0; $i<$maxitem-1; ++$i)
				{
					// 累計値から初期値を引く
					$after = $kwh[$i+1] - $initArray[$channel_old];
					if ($after < 0) $after = 0;
					$before = $kwh[$i] - $initArray[$channel_old];
					if ($before < 0) $before = 0;
					$sa = $after - $before;

					// 前の時間帯との差
					if ($sa > 0)
						$senDataStr = $senDataStr.strval($sa/1000).",";  // kwhを付加
					else
						$senDataStr = $senDataStr."0.0,";
				}
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
		
				//echo $senDataStr;  // 全チャンネル送信
				write_csvFile($ymdStr, $senDataStr);
				
			}  // if (strlen(senDataStr) > 0)
			//else
			//{
				//echo "no data";  // データなし
			//}
		}  // if ($recNum > 0)
		else  // 0件処理
		{
			// 見出し行
			//$senDataStr = "," . mb_substr($from_date1, 0, 4)."年,";
			if ($doMode == 1)  // 年モード(12か月分)
			{
				$senDataStr = ",".$yy1Str."年,";
				for ($j=1; $j<=12; ++$j)
					$senDataStr = $senDataStr.strval($j)."月,";
			}
			elseif ($doMode == 2)  // 月モード(31日分)
			{
				$senDataStr = ",".$yy1Str."年".$mm1Str."月,";
				for ($j=1; $j<=$maxitem-1; ++$j)
					$senDataStr = $senDataStr.strval($j)."日,";
			}
			elseif ($doMode == 3)  // 日モード(24時間分)
			{
				$senDataStr = ",".$yy1Str."年".$mm1Str."月".$dd1Str."日,";
				for ($j=0; $j<24; ++$j)
					$senDataStr = $senDataStr.strval($j)."時,";
			}
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

			$senDataStr = $senDataStr."0,主幹,";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0,";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			$chanCnt = count($setInfoArr)-3;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
			for ($chan=1; $chan<=$chanCnt; ++$chan)
			{
				$senDataStr = $senDataStr.strval($chan).",".strval($setInfoArr[$chan]).",";  // ファイルに在る名前
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0,";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			$senDataStr = $senDataStr."100,売電量,";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0,";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			$senDataStr = $senDataStr."101,エコキュート,";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0,";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			$senDataStr = $senDataStr."102,発電量,";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0,";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			$senDataStr = $senDataStr."103,太陽光機器消費量,";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0,";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			//echo $senDataStr;  // 全チャンネル送信
			write_csvFile($ymdStr, $senDataStr);
		}
		mysql_close($db);
	}  // DB準備OK
	else
	{
		//echo "database error";  // データベースエラー
		echo "ERROR 001";
	}
	
	// チャンネル名取得
	function getSetInfo()
	{
		$setInfoRec = "";
		$filepath = "channel_name48A.txt";
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		else
			echo "ERROR 002";
		return $setInfoRec;
	}
	
	// download CSV file
	function write_csvFile($ymdStr, $fileRec)
	{
		// UTF-8 BOM for microsoft excel
		$BOM = chr(239) . chr(187) . chr(191);
		$fileData = $BOM . $fileRec;
		header('Pragma: no-cache');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private', false);
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'. $ymdStr . '.csv";');
		header('Content-Transfer-Encoding: UTF-8');
		header('Content-Length: ' . strlen($fileData));
		echo $fileData;
	}
	
	function writeLog2($logStr)
	{
		error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
	}
?>
