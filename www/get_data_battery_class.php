<?php
	// 蓄電池のプロパティを取得する
	// http://viewe-morioka.ddo.jp/get_data_battery_class.php
	// http://192.168.1.100/get_data_battery_class.php
	// インスタンスリストに、d6 04 01013501、インスタンスが1個で且つ0x7Dがあれば蓄電池クラスを持つ蓄電池と判断(0x35はテスト環境の空気清浄器)
	// 念の為、メーカーコード、MACアドレスからベンダーコードをチェックすると尚良いな

	require_once 'battery_function.php';  // battery_functionの読み込み

	$logStr = "get_data_battery_class start";
 	writeLog2($logStr);

	$retIP = getIP_ByFile();  // ファイルに記録されているIPアドレスを返す
	//echo "getIP_ByFile retIP=" . $retIP . "<br />";

	$device_mode = "battery";  // 本番 %%%%%
	//$device_mode = "airpurifier";  // DEBUG 空気清浄器 %%%%%
				
	$ipAddr = getSelfIPaddr();  // 自分のIPアドレス
	//echo "サーバーのipアドレス ".$ipAddr."<br />";
	$port = 3610;  // 分電盤ポート番号
		
	// 送受信ソケット生成
	$sock_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$sock_receive = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	
	if ($sock_send && $sock_receive)  // ソケット生成OK
	{
		//$errorFlag = FALSE;  // DEBUG
		$bindOK = socket_bind($sock_receive, $ipAddr, $port);  // 2017/2/6  forの最初だと受信漏れがあるかも
		
		if ($bindOK == true)  // バインドOKか
		{
			socket_set_option($sock_send, SOL_SOCKET, SO_REUSEADDR, 1);  // 1回切りの方が良い
			socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒  2016.06.17
			
			if (strlen($retIP) > 0)  // ファイルにIPアドレス有った
			{
				$logStr = "getProperty_ByIP()実行1";
				//echo $logStr . "<br />";  // DEBUG
				writeLog2($logStr);

				$retStr = getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 蓄電池クラスのプロパティを取得
				//echo "getProperty_ByIP1 retStr=" . $retStr . "<br />";
				if ($retStr == ",,")  // 通信できなかった=別のIPだったかも
					$retIP = "";  // 以下で最初から
			}

			if (strlen($retIP) == 0)
			{
				//echo "getIP_ByMulticast()実行<br />";
				$logStr = "getIP_ByMulticast()実行";
				//echo $logStr . "<br />";  // DEBUG
				writeLog2($logStr);

				$multiStr = getIP_ByMulticast($sock_send, $sock_receive, $device_mode);  //  マルチキャストで蓄電池クラスの機器があるかどうかチェック
				$multiArray = explode(',', $multiStr);
				
				$logStr = "　ipアドレス : ".$multiArray[0] . "　ステータス : ".$multiArray[1];
				//echo $logStr . "<br />";
				writeLog2($logStr);
				
				$retIP = $multiArray[0];
				$retSts = $multiArray[1];
				
				if (strlen($retIP) > 0 && strcmp($retSts, "30") == 0)  // 蓄電池有り&&状態ON
				{
					//echo "getProperty_ByIP()実行2<br />";
					$logStr = "getProperty_ByIP()実行2";
					//echo $logStr . "<br />";  // DEBUG
					writeLog2($logStr);
	
					$retStr = getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 蓄電池クラスのプロパティを取得
					//echo "getProperty_ByIP2 retStr=" . $retStr . "<br />";
				}
			}
			
			$retArray = explode(',', $retStr);
// 			if ($retArray[0] == "on")  // 電源が入っている
// 			//if ($retArray[0] == "off")  // 電源が入っていない DEBUG
// 			{
// 				echo "電源入ってる<br />";
// 				$powerStr = getData_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 電力値を得る
// 				echo "powerStr=" . $powerStr . "<br />";
// 				
// 				$powerArray = explode(',', $powerStr);
// 				//if (strlen($powerArray[0]) > 0 && strlen($powerArray[1]))
// 				if (strlen($powerArray[0]) > 0 && strlen($powerArray[1]) > 0)
// 					saveData($powerArray[0], $powerArray[1]);
// 			}
// 			else
// 			{
// 				echo "電源入ってない<br />";
// 			}
			//if ($retArray[0] == "on")  // 電源が入っている
				//echo "電源入ってる<br />";  // DEBUG
			//else
				//echo "電源入ってない<br />";  // DEBUG
			
			if ($retArray[0] == "on")  // 電源が入っている
			{
				$powerStr = getData_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 電力値を得る(電源が入って無くても取得出来るかも)
				
				$logStr = "powerStr = " . $powerStr;
				//echo $logStr . "<br />";
				writeLog2($logStr);
				
				$powerArray = explode(',', $powerStr);
				if (strlen($powerArray[0]) == 0)
					$powerArray[0] = "0";
				if (strlen($powerArray[1]) == 0)
					$powerArray[1] = "0";
				saveData($powerArray[0], $powerArray[1]);
			}
			else
			{
					$logStr = "電源入ってない";
					//echo $logStr . "<br />";  // DEBUG
					writeLog2($logStr);
			}
		} // if ($bindOK == true)  // バインドOKか
		else
		{
			$errorCode = socket_last_error();
			$errorMsg = socket_strerror($errorCode);
			$logStr = "Error : 受信時 socket_bind に失敗しました。[".$errorCode."] ".$errorMsg;   // 分電盤の電源が入ってないとここでエラーだ
			//echo $logStr . "<br />";
			writeLog2($logStr);
		}
		
		socket_close($sock_send);
		socket_close($sock_receive);
	}  // if ($sock_send && $sock_receive)  // ソケット生成OK
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "Error : 受信時 socket_create に失敗しました。[".$errorCode."] ".$errorMsg;
		//echo $logStr . "<br />";
		writeLog2($logStr);
	}
	
	$logStr = "get_data_battery_class end";
	writeLog2($logStr);


// 積算充電電力量計測値と積算放電電力量計測値のDB保存
function saveData($zyuStr, $houStr)
{
	// DBへのコネクト
	$flag = TRUE;
	require_once 'db_info.php';  // DBの情報
	
	//echo "userStr=" . $userStr . "<br />";
	//echo "pathStr=" . $pathStr . "<br />";
	
	if (! $db = mysql_connect("localhost", $userStr, $pathStr))
		$flag = FALSE;
	
	if ($flag == TRUE)
	{
		$db_name = "ECOEyeDB01";
		if (! mysql_select_db($db_name, $db))
			$flag = FALSE;
	}
	
	if ($flag == TRUE)  // DB準備OK
	{
		$tbl_name = "bundenban3";  // 本番
		//$tbl_name = "bundenban_test";  // DEBUG

// 		if (! table_exists($db_name, $tbl_name, $db))
// 		{
// 			// テーブル作成用SQL文
// 			$str_sql = "CREATE TABLE {$tbl_name} ("
// 						."autokey INT(11) NOT NULL AUTO_INCREMENT,"
// 						."yymmddhms DATETIME NOT NULL,"
// 						."channel INT(4) NOT NULL,"
// 						."kwh INT(11) NOT NULL,"
// 						."PRIMARY KEY(autokey));";
// 
// 			// SQL文の実行
// 			$query = mysql_query($str_sql, $db);
// 			$errNo = mysql_errno($db);
// 			if ($errNo)
// 			{
// 				$logStr = mysql_errno($db) . ": " . mysql_error($db);
// 				writeLog2($logStr);
// 			}
// 			else
// 			{
// 				$logStr = "テーブル「{$tbl_name}」を作成しました。";
// 				writeLog2($logStr);
// 			}
// 			
// 			// テーブルの一覧表示
// 			show_tables($db_name, $db);
// 			echo "<br />";
// 			
// 			// フィールド属性の一覧表示
// 			show_fields($db_name, $tbl_name, $db);
// 			echo "<br />";
// 		}
// 		else  // テーブルが存在する場合
// 		{
// 			$logStr = "テーブル「{$tbl_name}」は作成済みです。";
// 		}
		
		$sqlDate = date("Y-m-d H:i:s");  // 現在の日時
		
		//$zyuVal = hexdec($zyuStr);  // 0.001Kwh
		//$zyuVal = round($zyuVal / 1000);  // 小数点以下四捨五入
		//$zyuVal = round(intval($zyuVal) / 1000);
		//$zyuVal = round(intval($zyuStr) / 1000);
		$zyuVal = intval($zyuStr);
		$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', 105, $zyuVal)";  // 正方向:蓄電器の充電量
		//echo "充電量sql=" . $sql . "<br />";
		$query = mysql_query($sql, $db);
		$errNo = mysql_errno($db);
		if ($errNo)
		{
			$logStr = mysql_errno($db) . ": " . mysql_error($db);
			//echo $logStr . "<br />";  // DEBUG
			writeLog2($logStr);
		}
		usleep(200000);  //0.2秒待つ
		
		//$houVal = hexdec($houStr);
		//$houVal = round($houVal / 1000);
		//$houVal = round(intval($houVal) / 1000);
		//$houVal = round(intval($houStr) / 1000);
		$houVal = intval($houStr);
		$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', 104, $houVal)";  //  逆方向:蓄電器の放電量
		//echo "放電量sql=" . $sql . "<br />";
		$query = mysql_query($sql, $db);
		$errNo = mysql_errno($db);
		if ($errNo)
		{
			$logStr = mysql_errno($db) . ": " . mysql_error($db);
			//echo $logStr . "<br />";  // DEBUG
			writeLog2($logStr);
		}
		
		mysql_close($db);    // DBファイルを閉る
	}
	else
	{
		$logStr = "データベース準備NG";
		//echo $logStr."<br />";  // DEBUG
		writeLog2($logStr);
	}
}

?>