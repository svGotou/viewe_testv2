<?php
	//http://192.168.1.100/eco_eye_day5_30mini_2017.php?period=2016.09.28&cute=1&solar=1&battery=1&zoon=current
	//http://192.168.1.100/eco_eye_day5_30mini_2017.php?period=2017.06.01&cute=1&solar=1&battery=1
	//http://viewe-iwate.ddo.jp/eco_eye_day5_30mini_2017.php?period=2017.06.01&cute=1&solar=1&battery=1
	//http://viewe-morioka.ddo.jp/eco_eye_day5_30mini_battery2.php?period=2017.06.01&cute=1&solar=1&battery=1
	//http://192.168.1.100/eco_eye_day5_30mini_battery2.php?period=2017.06.01&cute=1&solar=1&battery=1

	writeLog2("eco_eye_day5_30mini START");
	//echo date('Y-m-d H:i:s')." start eco_eye_day5_30mini_battery2.php<br />";

	// time=currentの時、分電盤から最新の値取得し、現在の時間帯にセットして送信する
	date_default_timezone_set('Asia/Tokyo');

	// 分電盤の初期値をtextファイルから読み込み、その値を引くことで、導入時の大きな値表示を防止する
	// 取り損なった直後の正常値は捨てて、差分を送信する 2017/01
	// 放電量と充電量の項目追加、蓄電池有無flag追加、0件処理に条件追加 2017/06
	// 0件の判断変更 2017/06
	// 翌日00:00台のデータが無いときの処理変更 2017/06
	// currentは止めよう 2017/06

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
		// 分電盤から直接データ取得
// 		$zoonStr = $_GET['zoon'];  // 時間帯
// 		if ($zoonStr == 'current')  // 現在が指定されていた
// 		{
// 			$currentDateStr = date('2017-10-30 15:20');  // 取得しようとしている日時
// 			$cYY = intval(mb_substr($currentDateStr, 0, 4, "UTF-8"));
// 			$cMM = intval(mb_substr($currentDateStr, 5, 2, "UTF-8"));
// 			$cDD = intval(mb_substr($currentDateStr, 8, 2, "UTF-8"));
// 			$cHH = intval(mb_substr($currentDateStr, 11, 2, "UTF-8"));
// 			$cMin = intval(mb_substr($currentDateStr, 14, 2, "UTF-8"));
//
// 			$currentValueStr = getCurrentValue();
// 			$currentValArray = explode(',', $currentValueStr);  // チャンネル毎最新値を文字列配列へ
// 			//var_dump($currentValArray);
// 			//echo "<br />";
// 			$chanValArray = array();
// 			for ($i=0; $i<count($currentValArray); $i=$i+2)
// 				$chanValArray[intval($currentValArray[$i])] = intval($currentValArray[$i+1]);  // チャンネル毎最新値を整数配列へ(chanValArray[102]=> int(3366478))
// 			//var_dump($chanValArray);
// 			//echo "<br />";
// 		}

		$setInfoRec = getSetInfo();  // チャンネル名等の読み込み
		//echo "setInfoRec=" . $setInfoRec . "<br>";
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
		$maxChannel = $setInfoArr[0] * 4 + 16;  // 2017.6.2
		//echo "maxChannel=" . $maxChannel . "<br>";

// 		// 分電盤の初期値を得る・ここから
// 		$filepath = "initial_data.txt";  // 2014/8/18からが妥当-->2014/8/17 23:50の値をセット
// 		$inti_umu = FALSE;
// 		if (file_exists($filepath))  // 在る
// 		{
// 			//echo "ファイル在り<br>";
// 			$dataStr = file_get_contents($filepath);
// 			if (strlen($dataStr) > 0)
// 			{
// 				$inti_umu = TRUE;
// 				//echo "$dataStr<br>";
// 			}
// 		}
// 		else
// 		{
// 			//echo "ファイル無し<br>";
// 		}
//
// 		$initArray = array();
// 		if ($inti_umu == TRUE)
// 		{
// 			$dataArray = array();
// 			$dataArray = explode(",", $dataStr);  // 戻り値(チャンネル,値,・・・)を配列に
// 			//print_r($dataArray);
// 			//echo "<br>";
//
// 			$dataCnt = count($dataArray) / 2;
// 			for ($i=0; $i<$dataCnt; ++$i)
// 			{
// 				$idx = intval($dataArray[$i*2]);
// 				//echo $idx . "<br>";
// 				$initArray[$idx] = intval($dataArray[$i*2+1]);
// 			}
// 		}
// 		else  // 有効なデータが無かった
// 		{
// 			for ($i=0; $i<=32; ++$i)  // 標準チャンネル(40-->32 2017.6.27)
// 				$initArray[$i] = 0;
// 			for ($i=100; $i<=105; ++$i)  // スペシャルチャンネル
// 				$initArray[$i] = 0;
// 		}
// 		//print_r($initArray);
// 		//echo "<br>";
// 		// 分電盤の初期値を得る・ここまで

		//$kwh = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);  // 電力(49item)
		//$kwh = array(-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1);  // 電力(49item)

		$thisYMD = date('Y-m-d');  // 2017.6.30

		$maxitem = 49;  // 24時間x2(30分間隔)+1(翌日)
		$kwh = array();
		for ($i=0; $i<$maxitem; ++$i)
			$kwh[$i] = -1;
		$cuteStr = $_GET['cute'];  // エコキュート有無
		$solarStr = $_GET['solar'];  // 太陽光有無
		$batteryStr = $_GET['battery'];  // 蓄電池有無  2017.06
		$ymdStr = $_GET['period'];  // 指定日
		$yy1Str = mb_substr($ymdStr, 0, 4);
		$mm1Str = mb_substr($ymdStr, 5, 2);
		$dd1Str = mb_substr($ymdStr, 8, 2);
		$yymmdd1Str = $yy1Str . "-" . $mm1Str . "-" . $dd1Str;  // 2017.6.30
		$nextDay = date("Y-m-d",strtotime("1 day" ,strtotime($yy1Str.$mm1Str.$dd1Str)));  // 翌日
		$yy2Str = mb_substr($nextDay, 0, 4);
		$mm2Str = mb_substr($nextDay, 5, 2);
		$dd2Str = mb_substr($nextDay, 8, 2);  // 23:30台を求めるため、翌日00:00のkwhを求める為に必要
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10

		$tbl_name = "bundenban3";  // 本番
		//$tbl_name = "bundenban_test";  // DEBUG
		//$tbl_name = "bundenban_test2";  // DEBUG(福田さんサーバーのデータ)

		 // 読み取り範囲の日付
	 	$from_date1 = $yy1Str."-".$mm1Str."-".$dd1Str." __:__:__";  // 2017.6.30
	 	//$from_date1a = $yy1Str."-".$mm1Str."-".$dd1Str." 23:59:59";  // 本当の1日の範囲
		$from_date2 = $yy2Str."-".$mm2Str."-".$dd2Str." __:__:__";  // 2017.6.30

		// 指定日が今日よりも小さい時、翌日のデータも読み込む 2017.6.30
		//echo "yymm1Str=".$yymmdd1Str." thisYMD=".$thisYMD."<br />";
		$recNum2 = 0;  // 翌日のデータ件数
		if ($yymmdd1Str < $thisYMD)  // 指定日が今日よりも小さい
		{
			$channelVal = strval($channel_old);
			$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";
			//echo "sql2=" . $sql2 . "<br />";  // debug用

			//echo date('Y-m-d H:i:s')." start eco_eye_day5_30mini_battery2.php mysql_query2<br />";
			$query2 = mysql_query($sql2, $db);
			//echo date('Y-m-d H:i:s')." end eco_eye_day5_30mini_battery2.php mysql_query2<br />";

			$recNum2 = mysql_num_rows($query2);
			//echo "recNum2=".strval($recNum2)."<br />";
		}

		// 今日のkwh読み込み
		$sql = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date1' ORDER BY channel, yymmddhms";
		//echo "sql=" . $sql . "<br />";  // debug

		//echo date('Y-m-d H:i:s')." start eco_eye_day5_30mini_battery2.php mysql_query1<br />";
		$query = mysql_query($sql, $db);
		//echo date('Y-m-d H:i:s')." end eco_eye_day5_30mini_battery2.php mysql_query1<br />";

		$recNum = mysql_num_rows($query);
		//echo "recNum=" . $recNum . "<br />";  // debug

		if ($recNum > 0)
		{
			while($rec = mysql_fetch_array($query))
			{
				//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
				$channel = intval($rec["channel"]);
				//if ($channel >= 100 ||  ($channel < 100 && $channel <= $maxChannel))
				if ($channel >= 100 ||  ($channel >= 0 && $channel <= $maxChannel))  // 2017.6.30
				{
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
// 						if ($dd == intval($dd1Str))  // 当日00:00から23:30のデータ
// 						{
							//if ($mm >= 0 && $mm <= 5)
							if ($mm >= 0 && $mm <= 29)
							{
								if ($kwh[$hh*2] == -1)
									$kwh[$hh*2] = intval($rec["kwh"]);  // xx:00-xx:29の最も古い値がセットされる
							}
							//else if ($mm >= 30 && $mm <= 35)
							else if ($mm >= 30 && $mm <= 59)
							{
								if ($kwh[$hh*2+1] == -1)
									$kwh[$hh*2+1] = intval($rec["kwh"]);  // xx:30-xx:59の最も古い値がセットされる
							}
// 						}
// 						else  // 翌日00:00のデータ
// 						{
// 							$kwh[$maxitem-1] = intval($rec["kwh"]);
// 						}
					}
					else  // 異なるチャンネル
					{
// 						if ($zoonStr == 'current')  // 現在が指定されていた
// 						{
// 							if (is_int($chanValArray[$channel_old]))
// 							{
// 								//echo "chanValArray[channel_old]=".$chanValArray[$channel_old]."<br />";
// 								//echo "cMin=".$cMin."<br />";
// 								if ($cMin >= 01 && $cMin <= 29)  // 00分は書き込まれているはずだから含めない
// 									$kwh[$cHH*2+1] = $chanValArray[$channel_old];  // 同じ時刻の30分以降にする
// 								else if ($cMin >= 31 && $cMin <= 59)  // 30分は書き込まれているはずだから含めない
// 									$kwh[($cHH+1)*2] = $chanValArray[$channel_old];  // 次の時刻の30分以前にする
// 							}
// 						}

						if ($recNum2 > 0)  // 翌日のデータ件数
						{
							while($rec2 = mysql_fetch_array($query2))
							{
								if ($kwh[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 当日の最も古い値
								{
									$kwh[$maxitem-1] = intval($rec2["kwh"]);  // 翌日の最も古い値がセットされる
									//echo "kwh_min[maxitem-1]=".$kwh[$maxitem-1]."<br />";  // debug用
									break;
								}
							}
						}

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
							$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前 2017.06
						else if ($channel_old == 105)
							$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前 2017.06
						else
							$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前

						$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
						$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);

						//var_dump($kwh);
						//echo "<br />";

						// 差を求める前の$kwh[$i]の値をログに出す
						$senDataDegug = $senDataDegug . chr(0x0a) . $channel_old . "ch";
						for ($i=0; $i<$maxitem-1; ++$i)
							$senDataDegug = $senDataDegug . "," . $kwh[$i];

						// $kwh[$i]は累計値なので、差を求める
						$senDataStr = $senDataStr."KWH;";
						for ($i=0; $i<$maxitem-1; ++$i)
						{
							// 累計値から初期値を引く
	// 						if ($channel_old == 7)
	// 						{
	// 							echo "i = " . $i . "<br />";
	// 							echo "initArray = " . $initArray[$channel_old] . "<br />";
	// 							echo "kwh[i+1] = " . $kwh[$i+1] . "<br />";
	// 							echo "kwh[i] = " . $kwh[$i] . "<br />";
	// 						}
							//$after = $kwh[$i+1] - $initArray[$channel_old];  // 初期値を引く
							$after = $kwh[$i+1];
							//echo "after = " . $after . "<br />";
							//if ($after < 0) $after = 0;  // 初期値をちゃんと設定すれば有りえない
							//$before = $kwh[$i] - $initArray[$channel_old];  // 初期値を引く
							$before = $kwh[$i];
							//echo "before = " . $before . "<br />";
							//echo "after=" . $after . " before=" . $before."<br />";

							//if ($before < 0) $before = 0;  // 初期値をちゃんと設定すれば有りえない
							if ($after < 0 || $before < 0)  // どちらかに有効なデータが無かったので使用量はゼロにする
							{
								//echo "after<0 || before<0<br />";
								$sa = 0;
							}
							else
							{
								$sa = $after - $before;
								//echo "channel=" . $channel_old . " sa=" . $sa . "<br />";

								//if ($sa >  $kwh[$i+1]*0.6  || $sa > $kwh[$i] * 0.6 || $sa < 0)  // 大きな値または前の方が値が大きいなら
								if ($sa >  $after*0.9 || $sa < 0)  // 大きな値または前の方が値が大きいなら
								{
									//echo "sa > 累計*0.9<br />";
									$sa = 0;
								}
							}
							//echo "sa = " . strval($sa/1000) . "<br />";

	// 						if ($channel_old == 7)
	// 						{
	// 							echo "after = " . $after . "<br />";
	// 							echo "before = " . $before . "<br />";
	// 							echo "after - before = " . $sa . "<br />";
	// 						}
							// 前の時間帯との差
							//if ($kwh[$i+1]-$kwh[$i] > 0)
							if ($sa > 0)
								//$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
								$senDataStr = $senDataStr.strval($sa/1000).";";  // kwhを付加
							else
								$senDataStr = $senDataStr."0.0;";
						}
						$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
						//echo "senDataStr=".$senDataStr."<br />";

						for ($i=0; $i<$maxitem; ++$i)  // 初期化
							$kwh[$i] = -1;

						// break後の今のチャンネル処理
						//if ($dd == intval($dd1Str))
						//{
							//if ($mm >= 0 && $mm <= 5)
							if ($mm >= 0 && $mm <= 29)
							{
								if ($kwh[$hh*2] == -1)
									$kwh[$hh*2] = intval($rec["kwh"]);  // xx:00-xx:29の最も古い値がセットされる
							}
							//else if ($mm >= 30 && $mm <= 35)
							else if ($mm >= 30 && $mm <= 59)
							{
								if ($kwh[$hh*2+1] == -1)
									$kwh[$hh*2+1] = intval($rec["kwh"]);  // xx:30-xx:59の最も古い値がセットされる
							}
						//}
// 						else  // 翌日00:00のデータ
// 						{
// 							$kwh[$maxitem-1] = intval($rec["kwh"]);
// 						}

						 $channel_old = $channel;  // old key 更新
					}
				}
			}  // while

			if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
			{
// 				if ($zoonStr == 'current')  // 現在が指定されていた
// 				{
// 					if (is_int($chanValArray[$channel_old]))
// 					{
// 						//echo "chanValArray[channel_old]=".$chanValArray[$channel_old]."<br />";
// 						//echo "cMin=".$cMin."<br />";
// 						if ($cMin >= 01 && $cMin <= 29)  // 00分は書き込まれているはずだから含めない
// 							$kwh[$cHH*2+1] = $chanValArray[$channel_old];  // 同じ時刻の30分以降にする
// 						else if ($cMin >= 31 && $cMin <= 59)  // 30分は書き込まれているはずだから含めない
// 							$kwh[($cHH+1)*2] = $chanValArray[$channel_old];  // 次の時刻の30分以前にする
// 					}
// 				}

				if ($recNum2 > 0)  // 翌日のデータ件数
				{
					while($rec2 = mysql_fetch_array($query2))
					{
						if ($kwh[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 翌日の最も古い値
						{
							$kwh[$maxitem-1] = intval($rec2["kwh"]);  // 翌日の最も古い値がセットされる
							//echo "kwh_min[maxitem-1]=".$kwh_min[$maxitem-1]."<br />";  // debug用
							break;
						}
					}
				}

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
					$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前 2017.06
				else if ($channel_old == 105)
					$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前 2017.06
				else
					$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$channel_old]).";".chr(0x0a);  // ファイルに在る名前

				//echo "from_date1=".mb_substr($from_date1, 0, 10)."<br />";
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);

				//var_dump($kwh);
				//echo "<br />";

				// 差を求める前の$kwh[$i]の値をログに出す
				$senDataDegug = $senDataDegug . chr(0x0a) . $channel_old . "ch";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataDegug = $senDataDegug . "," . $kwh[$i];

				// $kwh[$i]は累計値なので、差を求める
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
				{
					// 累計値から初期値を引く
					//$after = $kwh[$i+1] - $initArray[$channel_old];  // 初期値を引く
					$after = $kwh[$i+1];
					//echo "after = " . $after . "<br />";
					//if ($after < 0) $after = 0;  // 初期値をちゃんと設定すれば有りえない
					//$before = $kwh[$i] - $initArray[$channel_old];  // 初期値を引く
					$before = $kwh[$i];
					//echo "before = " . $before . "<br />";
					//echo "after=" . $after . " before=" . $before."<br />";

					//if ($before < 0) $before = 0;  // 初期値をちゃんと設定すれば有りえない
					if ($after < 0 || $before < 0)  // どちらかに有効なデータが無かったので使用量はゼロにする
					{
						//echo "after<0 || before<0<br />";
						$sa = 0;
					}
					else
					{
						$sa = $after - $before;
						//echo "channel=" . $channel_old . " sa=" . $sa . "<br />";

						//if ($sa >  $kwh[$i+1]*0.6  || $sa > $kwh[$i] * 0.6 || $sa < 0)  // 大きな値または前の方が値が大きいなら
						if ($sa >  $after*0.9 || $sa < 0)   // 大きな値または前の方が値が大きいなら
						{
							//echo "sa > 累計*0.9<br />";
							$sa = 0;
						}
					}
					//echo "sa = " . strval($sa/1000) . "<br />";

					// 前の時間帯との差
					//if ($kwh[$i+1]-$kwh[$i] > 0)
					if ($sa > 0)
						//$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
						$senDataStr = $senDataStr.strval($sa/1000).";";  // kwhを付加
					else
						$senDataStr = $senDataStr."0.0;";
				}  // for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

				echo $senDataStr;  // 全チャンネル送信

				writeLog2($senDataDegug);
				writeLog2($senDataStr);
			}  // if (strlen(senDataStr) > 0)
			/*else
			{
				echo "no data";  // データなし
			}*/
		}
		else  // 0件処理
		{
			$senDataStr = "CHANNEL;CH0;".chr(0x0a)."NAME;主幹;".chr(0x0a);
			$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
			$senDataStr = $senDataStr."KWH;";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0;";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

			//$chanCnt = count($setInfoArr)-3;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
			//echo "chanCnt1=" . $chanCnt . "<br>";
			$chanCnt = $setInfoArr[0] * 4 + 16;  // 2017.6.2
			//echo "chanCnt2=" . $chanCnt . "<br>";

			for ($chan=1; $chan<=$chanCnt; ++$chan)
			{
				$senDataStr = $senDataStr."CHANNEL;CH".strval($chan).";".chr(0x0a);
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$chan]).";".chr(0x0a);  // ファイルに在る名前
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}

			if ($solarStr == "1")  // 太陽光有 2017.6
			{
				$senDataStr = $senDataStr."CHANNEL;CH100;".chr(0x0a)."NAME;売電量;".chr(0x0a);
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}

// 			if ($cuteStr == "1")  // 2017.6
// 			{
// 				$senDataStr = $senDataStr."CHANNEL;CH101;".chr(0x0a)."NAME;エコキュート;".chr(0x0a);
// 				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
// 			}

			if ($solarStr == "1")  // 太陽光有 2017.6
			{
				$senDataStr = $senDataStr."CHANNEL;CH102;".chr(0x0a)."NAME;発電量;".chr(0x0a);
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

// 				$senDataStr = $senDataStr."CHANNEL;CH103;".chr(0x0a)."太陽光機器消費量;".chr(0x0a);
// 				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}

			if ($batteryStr == "1")  // 蓄電池有 2017.6
			{
				$senDataStr = $senDataStr."CHANNEL;CH104;".chr(0x0a)."放電量;".chr(0x0a);
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

				$senDataStr = $senDataStr."CHANNEL;CH105;".chr(0x0a)."充電量;".chr(0x0a);
				$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;;2;;3;;4;;5;;6;;7;;8;;9;;10;;11;;12;;13;;14;;15;;16;;17;;18;;19;;20;;21;;22;;23;;24;;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}

			echo $senDataStr;  // 全チャンネル送信

			writeLog2($senDataStr);
		}
		mysql_close($db);
	}  // DB準備OK
	else
	{
		echo "database error";  // データベースエラー
	}

	//echo "<br />" . date('Y-m-d H:i:s')." end eco_eye_day5_30mini_battery2.php<br />";
	writeLog2("eco_eye_day5_30mini END");

	// チャンネル名取得
	function getSetInfo()
	{
		$setInfoRec = "";
		$filepath = "channel_name48A.txt";
		//$filepath = "channel_name48A_test.txt";  // DEBUG
		if (file_exists($filepath))  // 在る
			$setInfoRec = file_get_contents($filepath);
		return $setInfoRec;
	}

	// 分電盤から現在の値を直接得る
// 	function getCurrentValue()
// 	{
// 		require_once 'getCurrentValue.php';  // textファイルへの現在値書き込み処理
// 		//echo "writeStr=".$writeStr."<br />";
// 		return $writeStr;
// 		// 0,4781933,1,132914,2,611321,3,1064622,4,38077,5,70538,6,200331,7,5986,8,136768,9,18021,10,37745,11,0,12,0,13,0,14,216,15,158281,16,54285,17,83302,18,42895,19,743,20,0,100,2521598,101,2675268,102,3366307,103,9548
// 	}

	function writeLog2($logStr)
	{
		error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
	}

?>
