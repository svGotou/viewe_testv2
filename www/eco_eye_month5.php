<?php
	// 余計なタグを外してクリーンなデータのみiOSに返す
	//http://192.168.1.100/eco_eye_month4.php?period=2015.01&cute=1&solar=1
	//http://192.168.1.100/eco_eye_month5_test.php?period=2016.09&cute=1&solar=1
	//http://viewe-iwate.ddo.jp/eco_eye_month5_test.php?period=2016.09&cute=1&solar=1
	//http://viewe-morioka.ddo.jp/eco_eye_month5_battery.php?period=2017.06&cute=1&solar=1&battery=1
	//http://192.168.1.100/eco_eye_month5_battery2.php?period=2017.05&cute=1&solar=1&battery=1
	
	// 改版履歴
	// 2017.06.03　ゼロ件処理でエコキートと太陽光の有無を考慮してデータを返す 
	// 放電量と充電量の項目追加、蓄電池有無flag追加、0件処理に条件追加、チャンネル数カウント方法修正 2017/06
	// 0件の判断変更 2017/06
	// 00:00台のデータが無いときの処理変更 2017/06
	// 　各日のminとmaxを取得し、通常は翌日min-指定日min=指定日使用量とする(だから指定月全件を読んでしまう)
	// 　翌日min無いとき、指定日maxの値を使用して指定日max-指定日min=指定日使用量とする
	//　 指定月が今月よりも小さいなら、翌月の1日の最も古い値を使用する

	//echo date('Y-m-d H:i:s')." start eco_eye_month5_battery2.php<br />";

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
		//$chanCnt = count($setInfoArr)-3;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
		$chanCnt = $setInfoArr[0] * 4 + 16;  // 2017.6.27
		//echo "chanCnt=".strval($chanCnt)."<br />";

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

		$thisYY = date('Y');   // 今年
		$thisMM = date('m');   // 今月
		$thisYM = date('Y-m');  // 2017.6.29
		
		$cuteStr = $_GET['cute'];  // エコキュート有無
		$solarStr = $_GET['solar'];  // 太陽光有無
		$batteryStr = $_GET['battery'];  // 蓄電池有無  2017.06
		$ymdStr = $_GET['period'];  // 指定日
		$yy1Str = mb_substr($ymdStr, 0, 4);
		$mm1Str = mb_substr($ymdStr, 5, 2);
		$yymm1Str = $yy1Str . "-" . $mm1Str;  // 2017.6.29
		$nextMonth = date("Y-m-d",strtotime("1 month" ,strtotime($yy1Str.$mm1Str."01")));  // 翌月1日
		$yy2Str = mb_substr($nextMonth, 0, 4);
		$mm2Str = mb_substr($nextMonth, 5, 2);
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10

		$tbl_name = "bundenban3";  // 本番
		//$tbl_name = "bundenban_test";  // DEBUG
		//$tbl_name = "bundenban_test2";  // DEBUG(福田さんサーバーのデータ)

		// 読み取り範囲(ひと月)の日付
	 	//$from_date1 = $yy1Str."-".$mm1Str."-__ 00:0_:__";  // 0件判断の日付にも使用
		//$from_date2 = $yy2Str."-".$mm2Str."-01 00:0_:__";
	 	$from_date1 = $yy1Str."-".$mm1Str."-__ __:__:__";  // 今月の全データを取得 2017.6.29
		$from_date2 = $yy2Str."-".$mm2Str."-01 __:__:__";  // 翌月1日データを取得 2017.6.29
		
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
			
		$maxitem = $maxitem + 1;  // 翌月1日分(最も古い値)を加算
		//echo "maxitem=".strval($maxitem)."<br />";
		$kwh_min = array();
		$kwh_max = array();
		for ($i=0; $i<$maxitem; ++$i)
		{
			$kwh_min[$i] = -1;  // 2017/01/24 0-->-1
			$kwh_max[$i] = -1;  // 2017.6.28
		}
		
// 		// 今日の最新kwhを取得
// 		$newCalc = FALSE;
// 		$newkwh = array();
// 		$thisDate = date('Y-m-d H:i:s');  // 今の日時 2015-04-09 15:12:14  // 09と15の間が2byteだ
// 		$thisMin = mb_substr($thisDate, 15, 2);
// 		if ($thisMin == "00")  // 00分のデータはまだ無い可能性があるので
// 			$thisDate = date("Y-m-d H:i:s", strtotime("-1 minute"));  // 1分前にする
// 		$thisYMDH = mb_substr($thisDate, 0, 14);
// 		$thisYY = mb_substr($thisDate, 0, 4);
// 		$thisMM = mb_substr($thisDate, 5, 2);
// 		$thisDD = mb_substr($thisDate, 8, 2);
// 		$thisHM = mb_substr($thisDate, 11, 5);
// 		$thisHH = mb_substr($thisDate, 11, 2);
// 		$thisMin = mb_substr($thisDate, 14, 2);
// 		if ($yy1Str == $thisYY && $mm1Str == $thisMM)  // 今月が指定されていて
// 		{
// 			if (!($thisDD == "01" && $thisHM < "00:30"))  // 今日が1日で00:30になっていないなら
// 			{
// 				$newCalc = TRUE;
// 				if ($thisMin >= "31")
// 					$thisMin = "30";
// 				else  // "01"〜"30" ("00"の値はここには来ない。"59"に変換されているから)
// 					$thisMin = "00";
// 				$new_date = $thisYMDH.$thisMin.":00";
// 				$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms >= '$new_date' ORDER BY channel, yymmddhms";
// 				//echo "sql2=".$sql2."<br />";
// 				$query2 = mysql_query($sql2, $db);
// 				$count2 = mysql_num_rows($query2);
// 				//echo "count2=".strval($count2)."<br />";
// 				while($rec2 = mysql_fetch_array($query2))
// 				{
// 					//echo "channel=".$rec2['channel']." yymmddhms=".$rec2['yymmddhms']." kwh=".$rec2['kwh']."<br />";  // debug
// 					$newkwh[$rec2['channel']]  = $rec2["kwh"];  // 万一、同一チャネルが二つあっても、最新が入る
// 				}
// 			}
// 		}

		// 指定月が今月よりも小さい時、翌月の1日のデータも読み込む 2017.6.28
		//echo "yymm1Str=".$yymm1Str." thisYM=".$thisYM."<br />";
		$recNum2 = 0;  // 翌月1日のデータ件数
		if ($yymm1Str < $thisYM)  // 指定月が今月よりも小さい
		{
			$channelVal = strval($channel_old);
			$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";
			//echo "sql2=" . $sql2 . "<br />";  // debug用
			
			//echo date('Y-m-d H:i:s')." start eco_eye_month5_battery2.php mysql_query2<br />";
			$query2 = mysql_query($sql2, $db);
			//echo date('Y-m-d H:i:s')." end eco_eye_month5_battery2.php mysql_query2<br />";
			
			$recNum2 = mysql_num_rows($query2);
			//echo "recNum2=".strval($recNum2)."<br />";
		}

		// 今月のkwh読み込み
		$sql = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date1' ORDER BY channel, yymmddhms";
		//echo "sql=" . $sql . "<br />";  // debug
		
		//echo date('Y-m-d H:i:s')." start eco_eye_month5_battery2.php mysql_query1<br />";
		$query = mysql_query($sql,$db);
		//echo date('Y-m-d H:i:s')." start eco_eye_month5_battery2.php mysql_query1<br />";
		
		$recNum = mysql_num_rows($query);
		//echo "recNum=".strval($recNum)."<br />";
		
		if ($recNum > 0)
		//if ($recNumCheck == 0)  // DEBUG
		{
			while($rec = mysql_fetch_array($query))
			{
				//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
				$channel = intval($rec["channel"]);
				
				$mm = intval(mb_substr($rec["yymmddhms"], 5, 2));  // 月
				$dd = intval(mb_substr($rec["yymmddhms"], 8, 2));  // 日
				if (($channel >= 0 && $channel <= $chanCnt) || $channel > 99)  // チャンネル数以下または100チャンネル以上なら
				{
					if ($sw1st == true)  // 最初の処理
					{
						$channel_old = $channel;
						$sw1st = false;
					}
					
					if ($channel == $channel_old)  // 同一チャンネル
					{
						//if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
						//{
							$kwh_max[$dd-1] = intval($rec["kwh"]);
							if ($kwh_min[$dd-1] == -1)
							{
								//echo "dd=" . $dd . " rec[kwh]=" . $rec["kwh"] . "<br />";
								$kwh_min[$dd-1] = intval($rec["kwh"]);  // 最小値が入る
							}
							//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
							//$idx = $dd-1;
							//echo "idx=" . $idx . " kwh[dd-1]=" . $kwh[$dd-1]."<br />";
						//}
// 						else  // 翌月1日00:00のデータ
// 						{
// 							$kwh[$maxitem-1] = intval($rec["kwh"]);
// 							//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
// 							$idx = $maxitem-1;
// 							//echo "idx=" . $idx . " kwh[maxitem-1]=" . $kwh[$maxitem-1]."<br />";
// 						}
					}
					else // 異なるチャンネル 
					{
						// 今月が指定されていて、各チャンネルの今日の最新値(今の時刻から)を翌日の値にする @@@@
						// ただし、今日が1日で00:30になっていないなら、この処理は不要
						// 現在の日時を取得 2015/04/04なら2015/04/04の最新時刻の値を取得
						// $channel_oldチャネルの今日の日付で降順に読んで1つ目のデータ
						//if ($yy1Str == $thisYY && $mm1Str == $thisMM)  // 今月が指定されていて
						//{
							//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今日が1日で00:30になっていないなら
// 							if ($newCalc == TRUE)  // 最新値をセットしなさい
// 							{
// 								//$sql2 = "SELECT * FROM $tbl_name WHERE channel = $channel_old AND yymmddhms <= '$thisDate' ORDER BY yymmddhms DESC LIMIT 1";
// 								//$query2 = mysql_query($sql2, $db);
// 								//$rec2 = mysql_fetch_array($query2);
// 								//$kwh[intval($thisDD)]  = intval($rec2["kwh"]);  // 翌日の値にする($kwhの添字は0が1日、1が2日なので、$thisDD-1が今日、$thisDDが翌日)
// 								//echo "newkwh[channel_old]=".$newkwh[$channel_old]."<br />";
// 								
// 								$kwh[intval($thisDD)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
// 							}
						//}
						
							if ($recNum2 > 0)  // 翌月の1日のデータ件数
							{
								while($rec2 = mysql_fetch_array($query2))
								{
									if ($kwh_min[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 翌月1日の最も古い値
									{
										$kwh_min[$maxitem-1] = intval($rec2["kwh"]);
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
							$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前 .06
						else if ($channel_old == 105)
							$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前 .06
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
							//echo "kwh_min[".$i."]=".$kwh_min[$i]."<br />";  // debug用
							//echo "kwh_max[".$i."]=".$kwh_max[$i]."<br />";  // debug用

							//$after = $kwh[$i+1] - $initArray[$channel_old];
							$after = $kwh_min[$i+1];
							if ($after == -1)  // 翌日の最小値が無い
								$after = $kwh_max[$i];  // 当日の最大値
							//if ($after < 0) $after = 0;
							//$before = $kwh[$i] - $initArray[$channel_old];
							$before = $kwh_min[$i];
							//if ($before < 0) $before = 0;
							//echo "after=" . $after . " before=" . $before."<br />";
							
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
						{
							//$kwh[$i] = -1;  // 2017/1/24 0-->-1
							$kwh_min[$i] = -1;  // 2017.6.28
							$kwh_max[$i] = -1;  // 2017.6.28
						}
							
						// break後の今のチャンネル処理
						//if ($mm == intval($mm1Str))  // 当月1日〜末日00:00のデータ
						//{
							//$kwh[$dd-1] = intval($rec["kwh"]);
						$kwh_max[$dd-1] = intval($rec["kwh"]);  // 最終(最大値)の値が入る
						if ($kwh_min[$dd-1] == -1)
						{
							//echo "dd=" . $dd . " rec[kwh]=" . $rec["kwh"] . "<br />";
							$kwh_min[$dd-1] = intval($rec["kwh"]);  // 最小値が入る
						}
							//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
							//$idx = $dd-1;
							//echo "idx=" . $idx . " kwh[dd-1]=" . $kwh[$dd-1]."<br />";
						//}
// 						else  // 翌月1日00:00のデータ
// 						{
// 							$kwh[$maxitem-1] = intval($rec["kwh"]);
// 							//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
// 							$idx = $maxitem-1;
// 							//echo "idx=" . $idx . " kwh[maxitem-1]=" . $kwh[$maxitem-1]."<br />";
// 						}

						 $channel_old = $channel;  // old key 更新
					}
				}  // if (($channel >= 0 && $channel <= $chanCnt) || $channel > 99)
			}  // while
			
			if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
			{
				//if ($yy1Str == $thisYY && $mm1Str == $thisMM)  // 今月が指定されていて
				//{
					//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今日が1日で00:30になっていないなら
// 					if ($newCalc == TRUE)  // 最新値をセットしなさい
// 					{
// 						//$sql2 = "SELECT * FROM $tbl_name WHERE channel = $channel_old AND yymmddhms <= '$thisDate' ORDER BY yymmddhms DESC LIMIT 1";
// 						//$query2 = mysql_query($sql2, $db);
// 						//$rec2 = mysql_fetch_array($query2);
// 						//$kwh[intval($thisDD)]  = intval($rec2["kwh"]);  // 翌日の値にする($kwhの添字は0が1日、1が2日なので、$thisDD-1が今日、$thisDDが翌日)
// 						//echo "newkwh[channel_old]=".$newkwh[$channel_old]."<br />";
// 						$kwh[intval($thisDD)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
// 					}
				//}
	
				if ($recNum2 > 0)  // 翌月の1日のデータ件数
				{
					while($rec2 = mysql_fetch_array($query2))
					{
						if ($kwh_min[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 翌月1日の最も古い値
						{
							$kwh_min[$maxitem-1] = intval($rec2["kwh"]);
							break;
						}
					}
				}

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
					$senDataStr = $senDataStr."NAME;放電量;".chr(0x0a);  // ファイルに無い名前 .06
				else if ($channel_old == 105)
					$senDataStr = $senDataStr."NAME;充電量;".chr(0x0a);  // ファイルに無い名前 .06
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
					//echo "kwh_min[".$i."]=".$kwh_min[$i]."<br />";  // debug用
					//echo "kwh_max[".$i."]=".$kwh_max[$i]."<br />";  // debug用

					// 累計値から初期値を引く
					//$after = $kwh[$i+1] - $initArray[$channel_old];
					$after = $kwh_min[$i+1];
					if ($after == -1)  // 翌日の最小値が無い
						$after = $kwh_max[$i];  // 当日の最大値
					//if ($after < 0) $after = 0;
					//$before = $kwh[$i] - $initArray[$channel_old];
					$before = $kwh_min[$i];
					//if ($before < 0) $before = 0;
					//echo "after=" . $after . " before=" . $before."<br />";
					
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
					
					// 前の時間帯との差
					//if ($kwh[$i+1]-$kwh[$i] > 0)
					if ($sa > 0)
						//$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
						$senDataStr = $senDataStr.strval($sa/1000).";";  // kwhを付加
					else
						$senDataStr = $senDataStr."0.0;";
				}
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
	
				echo $senDataStr;  // 全チャンネル送信
			}  // if (strlen(senDataStr) > 0)
			//else
			//{
				//echo "no data";  // データなし
			//}
		}
		else  // 0件処理
		{
			$senDataStr = "CHANNEL;CH0;".chr(0x0a)."NAME;主幹;".chr(0x0a);
			$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;";
			for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
				$senDataStr = $senDataStr.strval($j).";";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			$senDataStr = $senDataStr."KWH;";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0;";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			//$chanCnt = count($setInfoArr)-3;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
			$chanCnt = $setInfoArr[0] * 4 + 16;  // 2017.6.27
			//echo "chanCnt=" . $chanCnt . "<br>";
			
			for ($chan=1; $chan<=$chanCnt; ++$chan)
			{
				$senDataStr = $senDataStr."CHANNEL;CH".strval($chan).";".chr(0x0a);
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$chan]).";".chr(0x0a);  // ファイルに在る名前
				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
					$senDataStr = $senDataStr.strval($j).";";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			if ($solarStr == "1")  // 太陽光有
			{
				$senDataStr = $senDataStr."CHANNEL;CH100;".chr(0x0a)."NAME;売電量;".chr(0x0a);
				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
					$senDataStr = $senDataStr.strval($j).";";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
// 			if ($cuteStr == "1")  // エコキュート有
// 			{
// 				$senDataStr = $senDataStr."CHANNEL;CH101;".chr(0x0a)."NAME;エコキュート;".chr(0x0a);
// 				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;";
// 				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
// 					$senDataStr = $senDataStr.strval($j).";";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
// 			}
			
			if ($solarStr == "1")  // 太陽光有
			{
				$senDataStr = $senDataStr."CHANNEL;CH102;".chr(0x0a)."NAME;発電量;".chr(0x0a);
				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
					$senDataStr = $senDataStr.strval($j).";";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				
// 				$senDataStr = $senDataStr."CHANNEL;CH103;".chr(0x0a)."太陽光機器消費量;".chr(0x0a);
// 				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;";
// 				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
// 					$senDataStr = $senDataStr.strval($j).";";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			if ($batteryStr == "1")  // 蓄電池有 2017.6
			{
				$senDataStr = $senDataStr."CHANNEL;CH104;".chr(0x0a)."NAME;放電量;".chr(0x0a);
				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
					$senDataStr = $senDataStr.strval($j).";";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				
				$senDataStr = $senDataStr."CHANNEL;CH105;".chr(0x0a)."NAME;充電量;".chr(0x0a);
				$senDataStr = $senDataStr."MONTH;".mb_substr($from_date1, 0, 7).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)  // 28,29,30,31をちゃんとしている
					$senDataStr = $senDataStr.strval($j).";";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			echo $senDataStr;  // 全チャンネル送信
		}
		mysql_close($db);
	}  // DB準備OK
	else
	{
		echo "database error";  // データベースエラー
	}

	//echo "<br />" . date('Y-m-d H:i:s')." end eco_eye_month5_battery2.php<br />";
	
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
?>
