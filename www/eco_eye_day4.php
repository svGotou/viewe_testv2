<?php
	// eco_eye01.phpから余計なタグを外してクリーンなデータのみiOSに返す
	#DB処理
	$flag = TRUE;
	require_once 'db_info.php';  // DBの情報
//	if (! $db = mysql_connect("localhost", "root", "root_password"))  // ローカル(Mac)
//	if (! $db = mysql_connect("localhost", "root", "soundvision"))  // ローカル(debain)
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
		//echo "flag=".$flag."<br />";
		$umu = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);  // データ有無 31item(31日がmaxの数値)<--データ補正に使用(欠損データがある時、直近のデータを捜す為)
		$kwh = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);  // 電力

		//$commandStr = $_GET['command'];
		$cuteStr = $_GET['cute'];  // エコキュート有無
		//if (is_numeric($cuteStr))
			//echo "cuteStrは数字<br />";
		$solarStr = $_GET['solar'];  // 太陽光有無
		//if (is_numeric($solarStr))
			//echo "solarStrは数字<br />";
		//echo "cuteStr=".$cuteStr."<br />";
		//echo "solarStr=".$solarStr."<br />";
		
		$ymdStr = $_GET['period'];
		$yyStr = mb_substr($ymdStr, 0, 4);
		$mmStr = mb_substr($ymdStr, 5, 2);
		$ddStr = mb_substr($ymdStr, 8, 2);
		//echo "commandStr=".$commandStr."<br />";
		//echo "ymdStr=".$ymdStr."<br />";
		//echo "yyStr=".$yyStr."<br />";
		//echo "mmStr=".$mmStr."<br />";
		//echo "ddStr=".$ddStr."<br />";
		
		 // いち日(時刻単位で集計)
	 	$from_date1 = $yyStr."-".$mmStr."-".$ddStr." 00:00:00";
		$from_date2 = $yyStr."-".$mmStr."-".$ddStr." 23:59:59";
		//echo "from_date1=".$from_date1."<br />";
		//echo "from_date2=".$from_date2."<br />";
		//$breakPos = 13;  // 未使用<--なんだっけ
		$maxitem = 24;  // 24時間
		
		$setInfoRec = getSetInfo();  // 設定情報の読み込み
		//echo "setInfoRec=".$setInfoRec."<br />";
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
		$maxChan = 16+4*intval($setInfoArr[0]);  // チャンネル数 ($setInfoArr[0]は分電盤タイプの0,1,2・・・)
		
		// チャンネルの配列を作る
		$chanArray = array();
		for ($chan=0; $chan<=$maxChan; ++$chan)
		{
			$chanArray[] = $chan;
		}
		if ($cuteStr == 1)  // エコキュートあり
		{
			$chanArray[] = 101;
			$maxChan += 1;
		}
		if ($solarStr == 1)  // 太陽光あり
		{
			$chanArray[] = 100;
			$chanArray[] = 102;
			$chanArray[] = 103;
			$maxChan += 3;
		}
		//var_dump($chanArray);
		
		//for ($chan=0; $chan<=32; ++$chan)
		//for ($chan=0; $chan<=5; ++$chan)  // test(本番は32chanまで)
		for ($chan=0; $chan<=$maxChan; ++$chan)  // 本番は32chanまで(0CHは主幹、1〜32は分岐)
		{
			//echo "channel=".strval($chan)."<br />";
			// チャネル毎初期化
			for ($i=0; $i<=$maxitem; ++$i)
			{
				$umu[$i] = 0;  // $umu[$maxitem]は翌日用umu
				$kwh[$i] = 0;  // $kwh[$maxitem]は翌日用kwh
			}
			
			// 翌日00:00のkwhを求める。今日の23:00台を求めるため。
			//$nextDay = date("Y-m-d ",strtotime("1 day"));  // 翌日
			$nextDay = date("Y-m-d",strtotime("1 day" ,strtotime($yyStr.$mmStr.$ddStr)));
			$from_nextDay = $nextDay." 00:00:00";
			$to_nextDay = $nextDay." 23:59:59";
			//$sql = "SELECT * FROM bundenban3 WHERE channel = $chan AND yymmddhms >= '$from_nextDay' AND yymmddhms <= '$to_nextDay' ORDER BY yymmddhms DESC";
			$sql = "SELECT * FROM bundenban3 WHERE channel = $chanArray[$chan] AND yymmddhms >= '$from_nextDay' AND yymmddhms <= '$to_nextDay' ORDER BY yymmddhms DESC";
			//echo $sql."<br />";  // debug用
			$query = mysql_query($sql,$db);
			while($rec = mysql_fetch_array($query))
			{
				// maxitem番目に保存(最後に入るのがDESCなので最も若い時刻の値)
				if (intval($rec["kwh"]) > 0)
				{
					$umu[$maxitem] = 1;  // 翌日用umu
					$kwh[$maxitem] = intval($rec["kwh"]);  // 翌日用kwh
				}
			}

			// 今日のkwh読み込み
			//$sql = "SELECT * FROM bundenban3 WHERE channel = $chan AND yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY yymmddhms";
			//$sql = "SELECT * FROM bundenban3 WHERE channel = $chan AND yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY yymmddhms";
			$sql = "SELECT * FROM bundenban3 WHERE channel = $chanArray[$chan] AND yymmddhms >= '$from_date1' AND yymmddhms <= '$from_date2' ORDER BY yymmddhms";
			//echo $sql."<br />";  // debug用
			
			$query = mysql_query($sql,$db);
			while($rec = mysql_fetch_array($query))
			{
				// 以降はdayモードのみ @@@@@@@@
				//echo "データあった。<br />";
				//echo "rec['kwh']=".$rec['kwh']."<br />";
				$hh = intval(mb_substr($rec["yymmddhms"], 11, 2));  // 時
				$hun = intval(mb_substr($rec["yymmddhms"], 14, 2));  // 分
				//if ($hun == 0)  // 9:00とか10:00は各々8:00台、9:00台の値とする (9:05や9:20は読まなくなるがいいか？)
				if ($hun >= 0 && $hun <= 5)  // 20140519
				{
					$umu[$hh] = 1;
					$kwh[$hh] = intval($rec["kwh"]);
				}
			}  // while
			
			// 1channel分送信データ作成
			//$senDataStr = $senDataStr."CHANNEL;CH".strval($chan).";".chr(0x0a);
			$senDataStr = $senDataStr."CHANNEL;CH".strval($chanArray[$chan]).";".chr(0x0a);
			//if ($chan > 0)
				//$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$chan]).";".chr(0x0a);  // 名前
			//else
				//$senDataStr = $senDataStr."NAME;主幹;".chr(0x0a);  // 名前
			if ($chanArray[$chan] == 0)
				$senDataStr = $senDataStr."NAME;主幹;".chr(0x0a);  // 名前
			else if ($chanArray[$chan] == 101)
				$senDataStr = $senDataStr."NAME;エコキュート;".chr(0x0a);  // 名前
			else if ($chanArray[$chan] == 100)
				$senDataStr = $senDataStr."NAME;売電量;".chr(0x0a);  // 名前
			else if ($chanArray[$chan] == 102)
				$senDataStr = $senDataStr."NAME;発電量;".chr(0x0a);  // 名前
			else if ($chanArray[$chan] == 103)
				$senDataStr = $senDataStr."NAME;太陽光機器消費量;".chr(0x0a);  // 名前
			else
				$senDataStr = $senDataStr."NAME;".strval($setInfoArr[$chan]).";".chr(0x0a);  // 名前
			
			$senDataStr = $senDataStr."DAY;".mb_substr($from_date1, 0, 10).";".chr(0x0a);
			$senDataStr = $senDataStr."PERIOD;1;2;3;4;5;6;7;8;9;10;11;12;13;14;15;16;17;18;19;20;21;22;23;24;".chr(0x0a);
			
			// $kwh[$i]は累計値なので、差を求める
			//echo "maxitem=".$maxitem."<br />"; 
			$senDataStr = $senDataStr."KWH;";
			for ($i=0; $i<$maxitem; ++$i)
			{
				// 前の時間帯との差
				//echo "kwh[".$i."+1]=".$kwh[$i+1]."<br />";
				//echo "kwh[".$i."]=".$kwh[$i]."<br />"; 
				if ($kwh[$i+1]-$kwh[$i] > 0)
					$senDataStr = $senDataStr.strval(($kwh[$i+1]-$kwh[$i])/1000).";";  // kwhを付加
					//$senDataStr = $senDataStr.strval(rand(1, 1000)/1000).";";  // kwhを付加  <-- マニュアル作成の為のダミーデータ
				else
					$senDataStr = $senDataStr."0.0;";
					//$senDataStr = $senDataStr.strval(rand(1, 1000)/1000).";";  // kwhを付加  <-- マニュアル作成の為のダミーデータ
			}
			$senDataStr = $senDataStr.chr(0x0a);  // 改行付加

			//echo "senDataStr=".$senDataStr."<br />"; 
		}  // fore
		
		echo $senDataStr;  // 全チャンネル送信
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
