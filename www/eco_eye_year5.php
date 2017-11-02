<?php
	// 余計なタグを外してクリーンなデータのみiOSに返す
	//http://192.168.1.100/eco_eye_year4.php?period=2014&cute=1&solar=1
	//http://viewe-iwate.ddo.jp/eco_eye_year5_test.php?period=2014&cute=1&solar=1
	//http://viewe-morioka.ddo.jp/eco_eye_year5_battery2.php?period=2017&cute=1&solar=1&battery=1
	//http://192.168.1.100/eco_eye_year5_battery2.php?period=2016&cute=1&solar=1&battery=1
	
	// 改版履歴
	// 2017.06.03　ゼロ件処理でエコキートと太陽光の有無を考慮してデータを返す 
	// 放電量と充電量の項目追加、蓄電池有無flag追加、0件処理に条件追加、チャンネル数カウント方法修正 2017/06
	// 0件の判断変更 2017/06
	// 1日のデータが無いときの処理変更 2017/06
	// 　各月のminとmaxを取得し、通常は翌月min-指定月min=指定月使用量とする(だから指定年全件を読んでしまう)
	// 　翌月min無いとき、指定月maxの値を使用して指定月max-指定月min=指定月使用量とする
	//　 指定年が今年よりも小さいなら、翌年の1月の最も古い値を使用する
	
	//echo date('Y-m-d H:i:s')." start eco_eye_year5_battery2.php<br />";
	
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
		//echo "setInfoRec=".$setInfoRec."<br />";
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

		//$thisDate = date('Y-m-d  H:i:s');  // 今の日時
		$thisYY = date('Y');   // 今年
		$thisMM = date('m');   // 今月
		//$thisDD = date('d');   // 今日
		//$thisHM = date('H:i');  // 今の時分
		//echo "thisDate=".$thisDate."<br />";
		//echo "thisYY=".$thisYY."<br />";
		//echo "thisMM=".$thisMM."<br />";
		//echo "thisDD=".$thisDD."<br />";
		//echo "thisHM=".$thisHM."<br />";
		
		$cuteStr = $_GET['cute'];  // エコキュート有無
		$solarStr = $_GET['solar'];  // 太陽光有無
		$batteryStr = $_GET['battery'];  // 蓄電池有無  2017.06
		$ymdStr = $_GET['period'];  // 指定日
		$yy1Str = mb_substr($ymdStr, 0, 4);
		$nextYear = date("Y-m-d",strtotime("1 year" ,strtotime($yy1Str."0101")));  // 翌年1月1日
		$yy2Str = mb_substr($nextYear, 0, 4);
		$senDataStr = "";  // 送信データ
		$sw1st = true;
		$channel_old = 999;  // 2015.05.10
				
		$tbl_name = "bundenban3";  // 本番
		//$tbl_name = "bundenban_test";  // DEBUG
		//$tbl_name = "bundenban_test2";  // DEBUG(福田さんサーバーのデータ)

		// 読み取り範囲(ひと月)の日付
	 	//$from_date1 = $yy1Str."-__-01 00:0_:__";  // これだと今4月の時、5/1のデータが無いので、4月の値はゼロになってしまう
		//$from_date2 = $yy2Str."-01-01 00:0_:__";
	 	$from_date1 = $yy1Str."-__-__ __:__:__";  // 今年の全データを取得 2017.6.29
	 	$from_date2 = $yy2Str."-01-__ __:__:__";  // 翌年1月データを取得 2017.6.29
		
		//echo "from_date1=".$from_date1."<br />";
		//echo "from_date2=".$from_date2."<br />";

		$maxitem = 13;  // 12ヶ月+1(翌月)
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
// 		$thisYMD = mb_substr($thisDate, 0, 11);  // 今日の年月日 2017.6.28
// 		$thisYY = mb_substr($thisDate, 0, 4);
// 		$thisMM = mb_substr($thisDate, 5, 2);
// 		$thisDD = mb_substr($thisDate, 8, 2);
// 		$thisHM = mb_substr($thisDate, 11, 5);
// 		$thisHH = mb_substr($thisDate, 11, 2);
// 		$thisMin = mb_substr($thisDate, 14, 2);
// 		
// 		if ($yy1Str == $thisYY)  // 今年が指定されていて
// 		{
// 			if (!($thisDD == "01" && $thisHM < "00:30"))  // 今日が1日で00:30になっていないなら
// 			{
// 				$newCalc = TRUE;
// // 				if ($thisMin >= "31")
// // 					$thisMin = "30";
// // 				else  // "01"〜"30" ("00"の値はここには来ない。"59"に変換されているから)
// // 					$thisMin = "00";
// // 				$new_date = $thisYMDH.$thisMin.":00";
// 				$new_date = $thisYMD."__:__:__";  //  その日の一番新しい値を得る 2017.6.28
// 				//$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms >= '$new_date' ORDER BY channel, yymmddhms";
// 				$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$new_date' ORDER BY channel, yymmddhms DESC";  //  2017.6.28
// 				echo "sql2=".$sql2."<br />";
// 				$query2 = mysql_query($sql2, $db);
// 				$count2 = mysql_num_rows($query2);
// 				echo "count2=".strval($count2)."<br />";
// 				while($rec2 = mysql_fetch_array($query2))
// 				{
// 					//echo "channel=".$rec2['channel']." yymmddhms=".$rec2['yymmddhms']." kwh=".$rec2['kwh']."<br />";  // debug
// 					if ($newkwh[$rec2['channel']] == 0)  // 最新のみセットする 2017.6.28
// 					{
// 						$newkwh[$rec2['channel']]  = $rec2["kwh"];  // (万一、同一チャネルが二つあっても、最新が入る)
// 						echo "rec2[kwh]=" . $rec2["kwh"] . "<br />";  // debug用
// 					}
// 				}
// 			}
// 		}

		// 指定年が今年よりも小さい時、翌年の1月のデータも読み込む 2017.6.28
		// チャンネル毎にselectすると遅くなるので、ここで読んでおく
		//echo "yy1Str=".$yy1Str." thisYY=".$thisYY."<br />";
		$recNum2 = 0;  // 翌年の1月のデータ件数
		if ($yy1Str < $thisYY)  // 指定年が今年よりも小さい
		{
			$channelVal = strval($channel_old);
			$sql2 = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date2' ORDER BY channel, yymmddhms";
			//echo "sql2=" . $sql2 . "<br />";  // debug用
			
			//echo date('Y-m-d H:i:s')." start eco_eye_year5_battery2.php mysql_query2<br />";
			$query2 = mysql_query($sql2, $db);
			//echo date('Y-m-d H:i:s')." end eco_eye_year5_battery2.php mysql_query2<br />";
			
			$recNum2 = mysql_num_rows($query2);
			//echo "recNum2=".strval($recNum2)."<br />";
		}

		// 今年のkwh読み込み
		$sql = "SELECT * FROM $tbl_name WHERE yymmddhms LIKE '$from_date1' ORDER BY channel, yymmddhms";
		//echo "sql=" . $sql . "<br />";  // debug用
		
		//echo date('Y-m-d H:i:s')." start eco_eye_year5_battery2.php mysql_query1<br />";
		$query = mysql_query($sql,$db);
		//echo date('Y-m-d H:i:s')." start eco_eye_year5_battery2.php mysql_query1<br />";

		$recNum = mysql_num_rows($query);
		//echo "recNum=".strval($recNum)."<br />";
		
		if ($recNum > 0)
		//if ($recNum == 0)  // DEBUG
		{
			while($rec = mysql_fetch_array($query))
			{
				//echo "channel=".$rec['channel']." yymmddhms=".$rec['yymmddhms']." kwh=".$rec['kwh']."<br />";  // debug
				$channel = intval($rec["channel"]);
				if (($channel >= 0 && $channel <= $chanCnt) || $channel > 99)   // チャンネル数以下または100チャンネル以上なら
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
						//if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
							//$kwh[$mm-1] = intval($rec["kwh"]);
							$kwh_max[$mm-1] = intval($rec["kwh"]);  // 最終(最大値)の値が入る @@@@ これに時間がかかるんだろう
							if ($kwh_min[$mm-1] == -1)
							{
								//echo "mm=" . $mm . " rec[kwh]=" . $rec["kwh"] . "<br />";
								$kwh_min[$mm-1] = intval($rec["kwh"]);  // 最小値が入る
							}
						//else  // 翌年1月1日00:00のデータ
							//$kwh[$maxitem-1] = intval($rec["kwh"]);
					}
					else // 異なるチャンネル 
					{
						//echo "channel_old=" . $channel_old ."<br />";
						// 今年が指定されていて、各チャンネルの今月の最新値(今の時刻から)を翌月1日の値にする @@@@
						// ただし、今月が1日で00:30になっていないなら、この処理は不要
						// 現在の日時を取得 2015/04/04なら2015/04/04の最新時刻の値を取得
						// $channel_oldチャネルの今日の日付で降順に読んで1つ目のデータ
						//if ($yy1Str == $thisYY)  // 今年が指定されていて
						//{
							//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今月が1日で00:30になっていないなら
// 							if ($newCalc == TRUE)  // 最新値をセットしなさい
// 							{
// 								//$sql2 = "SELECT * FROM $tbl_name WHERE channel = $channel_old AND yymmddhms <= '$thisDate' ORDER BY yymmddhms DESC LIMIT 1";
// 								//echo $sql2."<br />";  // debug用
// 								//$query2 = mysql_query($sql2, $db);
// 								//$count2 = mysql_num_rows($query2);
// 								//echo "count2=".strval($count2)."<br />";  // debug用
// 								//$rec2 = mysql_fetch_array($query2);
// 								//echo "channel=".$rec2['channel']." yymmddhms=".$rec2['yymmddhms']." kwh=".$rec2['kwh']."<br />";  // debug
// 								//$kwh[intval($thisMM)]  = intval($rec2["kwh"]);  // 翌月の値にする($kwhの添字は0が1月、1が2月なので、$thisMM-1が今月、$thisMMが翌月)
// 								//echo "kwh[intval(thisMM)]=".$kwh[intval($thisMM)]."<br />";  // debug用
// 								$kwh[intval($thisMM)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
// 							}
						//}
						
						// 指定年が今年よりも小さい時、翌年の1月のデータも読み込む 2017.6.28
						if ($recNum2 > 0)  // 翌年の1月のデータ件数
						{
							while($rec2 = mysql_fetch_array($query2))
							{
								if ($kwh_min[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 翌年1月の最も古い値
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
		
						$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
						$senDataStr = $senDataStr."PERIOD;";
						for ($j=1; $j<=$maxitem-1; ++$j)
							$senDataStr = $senDataStr.strval($j).";";
						$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
						
						// $kwh[$i]は累計値なので、差を求める
						$senDataStr = $senDataStr."KWH;";
						for ($i=0; $i<$maxitem-1; ++$i)
						{
							//if ($channel_old == 0)
							//{
								//echo "i=".$i."<br />";  // debug用
								//echo "kwh[i+1]=".$kwh[$i+1]."<br />";  // debug用
							//echo "kwh_min[".$i."]=".$kwh_min[$i]."<br />";  // debug用
							//echo "kwh_max[".$i."]=".$kwh_max[$i]."<br />";  // debug用
							//$ii = $i + 1;  // debug用
							//echo "kwh_min[".$ii ."]=".$kwh_min[$i+1]."<br />";  // debug用
							//echo "kwh_max[".$ii ."]=".$kwh_max[$i+1]."<br />";  // debug用
							//}
							
							//$after = $kwh[$i+1] - $initArray[$channel_old];
							$after = $kwh_min[$i+1];
							if ($after == -1)  // 翌月の最小値が無い
								$after = $kwh_max[$i];  // 今月の最大値
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
								
								//if ($sa >  $kwh_min[$i+1]*0.6  || $sa > $kwh_min[$i] * 0.6 || $sa < 0)  // 大きな値または前の方が値が大きいなら
								if ($sa >  $after*0.9 || $sa < 0)  // 大きな値または前の方が値が大きいなら
								{
									//echo "sa=".$sa." > 累計*0.9<br />";
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
		
						// 今のチャンネル処理
						for ($i=0; $i<$maxitem; ++$i)  // まず初期化
						{
							//$kwh[$i] = -1;  // /01/24 0-->-1
							$kwh_min[$i] = -1;  // 2017.6.28
							$kwh_max[$i] = -1;  // 2017.6.28
						}
						//if ($yy == intval($yy1Str))  // 今年1月〜12月00:00のデータ
							//$kwh[$mm-1] = intval($rec["kwh"]);
						$kwh_max[$mm-1] = intval($rec["kwh"]);  // 最終(最大値)の値が入る
						if ($kwh_min[$mm-1] == -1)
						{
							//echo "mm=" . $mm . " rec[kwh]=" . $rec["kwh"] . "<br />";
							$kwh_min[$mm-1] = intval($rec["kwh"]);  // 最小値が入る
						}
						//else  // 翌年1月1日00:00のデータ
							//$kwh[$maxitem-1] = intval($rec["kwh"]);
		
						 $channel_old = $channel;  // old key 更新
					}
				}  // if ($channel <= $chanCnt || $channel > 99)
			}  // while
	
			if (strlen($senDataStr) > 0)  // 送信データ有りなので、最後のチャンネルも処理する
			{
				//echo "channel_old=" . $channel_old ."<br />";
					//if ($yy1Str == $thisYY)  // 今年が指定されていて
					//{
						//if (!($thisDD == "01" && $thisHM < "00:30"))  // 今月が1日で00:30になっていないなら
// 						if ($newCalc == TRUE)  // 最新値をセットしなさい
// 						{
// 							//$sql2 = "SELECT * FROM $tbl_name WHERE channel = $channel_old AND yymmddhms <= '$thisDate' ORDER BY yymmddhms DESC LIMIT 1";
// 							//$query2 = mysql_query($sql2, $db);
// 							//$rec2 = mysql_fetch_array($query2);
// 							//$kwh[intval($thisMM)]  = intval($rec2["kwh"]);  // 翌月の値にする($kwhの添字は0が1月、1が2月なので、$thisMM-1が今月、$thisMMが翌月)
// 							$kwh[intval($thisMM)]  = intval($newkwh[$channel_old]);  // channel_oldの冒頭で取得していた最新値をセットする
// 						}
					//}
				
				if ($recNum2 > 0)  // 翌年の1月のデータ件数
				{
					while($rec2 = mysql_fetch_array($query2))
					{
						if ($kwh_min[$maxitem-1] == -1 && intval($rec2['channel']) == $channel_old)  // 翌年1月の最も古い値
						{
							$kwh_min[$maxitem-1] = intval($rec2["kwh"]);
							//echo "kwh_min[maxitem-1]=".$kwh_min[$maxitem-1]."<br />";  // debug用
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
				
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;";
				for ($j=1; $j<=$maxitem-1; ++$j)
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
					if ($after == -1)  // 翌月月初が無い
						$after = $kwh_max[$i];  // 今月の最大
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
						
						//if ($sa >  $kwh_min[$i+1]*0.6  || $sa > $kwh_min[$i] * 0.6 || $sa < 0)  // 大きな値または前の方が値が大きいなら
						if ($sa >  $after*0.9 || $sa < 0)  // 大きな値または前の方が値が大きいなら
						{
							//echo "sa=".$sa." > 累計*0.9<br />";
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
			$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
			$senDataStr = $senDataStr."KWH;";
			for ($i=0; $i<$maxitem-1; ++$i)
				$senDataStr = $senDataStr."0.0;";
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			
			//$chanCnt = count($setInfoArr)-3;;  // チャンネル数(タイプとエコキュートと太陽光の有無の3項目分引く)
			$chanCnt = $setInfoArr[0] * 4 + 16;  // .6.27
			//echo "chanCnt=" . $chanCnt . "<br>";

			for ($chan=1; $chan<=$chanCnt; ++$chan)
			{
				$senDataStr = $senDataStr."CHANNEL;CH".strval($chan).";".chr(0x0a);
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$chan]).";".chr(0x0a);  // ファイルに在る名前
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			if ($solarStr == "1")  // 太陽光有
			{
				$senDataStr = $senDataStr."CHANNEL;CH100;".chr(0x0a)."NAME;売電量;".chr(0x0a);
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
// 			if ($cuteStr == "1")  // エコキュート有
// 			{
// 				$senDataStr = $senDataStr."CHANNEL;CH101;".chr(0x0a)."NAME;エコキュート;".chr(0x0a);
// 				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
// 			}
			
			if ($solarStr == "1")  // 太陽光有
			{
				$senDataStr = $senDataStr."CHANNEL;CH102;".chr(0x0a)."NAME;発電量;".chr(0x0a);
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				
// 				$senDataStr = $senDataStr."CHANNEL;CH103;".chr(0x0a)."太陽光機器消費量;".chr(0x0a);
// 				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
// 				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
// 				$senDataStr = $senDataStr."KWH;";
// 				for ($i=0; $i<$maxitem-1; ++$i)
// 					$senDataStr = $senDataStr."0.0;";
// 				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
			}
			
			if ($batteryStr == "1")  // 蓄電池有 .6
			{
				$senDataStr = $senDataStr."CHANNEL;CH104;".chr(0x0a)."NAME;放電量;".chr(0x0a);
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
				$senDataStr = $senDataStr."KWH;";
				for ($i=0; $i<$maxitem-1; ++$i)
					$senDataStr = $senDataStr."0.0;";
				$senDataStr = $senDataStr.chr(0x0a);  // 改行付加
				
				$senDataStr = $senDataStr."CHANNEL;CH105;".chr(0x0a)."NAME;充電量;".chr(0x0a);
				$senDataStr = $senDataStr."YEAR;".mb_substr($from_date1, 0, 4).";".chr(0x0a);
				$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;".chr(0x0a);
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
	
	//echo "<br />" . date('Y-m-d H:i:s')." end eco_eye_year5_battery2.php<br />";

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
