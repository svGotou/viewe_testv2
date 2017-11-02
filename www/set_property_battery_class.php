<?php
	// 蓄電池のプロパティを設定する  (DEBUGは空気清浄器)
	// http://192.168.1.100/set_property_battery_class.php?cmd=power&param=off  // 電源off
	// http://192.168.1.100/set_property_battery_class.php?cmd=power&param=on  // 電源on
	// http://192.168.1.100/set_property_battery_class.php?cmd=wind&param=0  // 風量自動
	// http://192.168.1.100/set_property_battery_class.php?cmd=wind&param=1  // 風量1-8
	// http://192.168.1.100/set_property_battery_class.php?cmd=drive&param=1  // 1:充電 2:放電 3:待機
	// インスタンスリストに、d6 04 01013501、インスタンスが1個で且つ0x7Dがあれば蓄電池クラスを持つ蓄電池と判断(0x35はテスト環境の空気清浄器)
	// 念の為、メーカーコード、MACアドレスからベンダーコードをチェックすると尚良いな

	require_once 'battery_function.php';  // battery_functionの読み込み

	$retIP = getIP_ByFile();  // ファイルに記録されているIPアドレスを返す
	//echo "getIP_ByFile retIP=" . $retIP . "<br />";

	$returnStr = "ng";  // web vieweに戻す値
	$device_mode = "battery";  // 本番 %%%%%
	//$device_mode = "airpurifier";  // DEBUGは空気清浄器 %%%%%
	
	$logStr = "set_property_battery_class start";
 	writeLog2($logStr);

	// コマンド&パラメータ解析
	$cmdStr = $_GET['cmd'];
	$paramStr = $_GET['param'];
	if ($paramStr == "on")  // 電源ON
		$paramStr = "30";
	else if ($paramStr == "off")  // 電源OFF
		$paramStr = "31";
	else if ($paramStr == "auto")  // 風量自動  (DEBUGは空気清浄器)
		$paramStr = "41";
	else
		$paramStr = "3" . $paramStr;
		
	$ipAddr = getSelfIPaddr();  // 自分のIPアドレス
	echo "サーバーのipアドレス ".$ipAddr."<br />";
	$port = 3610;  // 分電盤ポート番号
		
// 	// 送受信ソケット生成
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
				echo "getProperty_ByIP()実行1<br />";
				$retStr = getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 蓄電池クラスのプロパティを取得
				echo "getProperty_ByIP1 retStr=" . $retStr . "<br />";
				if ($retStr == ",,")  // 通信できなかった=別のIPだったかも
					$retIP = "";  // 以下で最初から
			}

			if (strlen($retIP) == 0)  // ファイルにIPアドレス無かった
			{
				echo "getIP_ByMulticast()実行<br />";
				$multiStr = getIP_ByMulticast($sock_send, $sock_receive, $device_mode);  //  マルチキャストで蓄電池クラスの機器があるかどうかチェック
				echo "getIP_ByMulticast multiStr=" . $multiStr . "<br />";
				
				$multiArray = explode(',', $multiStr);
				$logStr = "　ipアドレス : ".$multiArray[0] . "　ステータス : ".$multiArray[1];
				echo $logStr . "<br />";
				$retIP = $multiArray[0];
				$retSts = $multiArray[1];
				
				if (strlen($retIP) > 0 && strcmp($retSts, "30") == 0)  // 蓄電池有り&&状態ON
				{
					echo "getProperty_ByIP()実行2<br />";
					$retStr = getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 蓄電池クラスのプロパティを取得
					echo "getProperty_ByIP2 retStr=" . $retStr . "<br />";
				}
			}  // if (strlen($retIP) == 0)
			
			$retArray = explode(',', $retStr);
			$logStr = "　on/off : ".$retArray[0] . "　プロパティ1 : ".$retArray[1]. "　プロパティ2 : ".$retArray[2];
			echo $logStr . "<br />";
			$offonSts = $retArray[0];
			$prop1Sts = $retArray[1];
			$prop2Sts = $retArray[2];

			if ($cmdStr == "power" || ($cmdStr == "wind" && $offonSts == "on") || ($cmdStr == "drive" && $offonSts == "on"))  // windとdriveは電源ONである事
			{
				echo "setProperty()実行<br />";
				$returnStr = setProperty($sock_send, $sock_receive, $device_mode, $retIP, $port, $cmdStr, $paramStr);
			}
		} // if ($bindOK == true)  // バインドOKか
		else
		{
			$errorCode = socket_last_error();
			$errorMsg = socket_strerror($errorCode);
			$logStr = "Error : 受信時 socket_bind に失敗しました。[".$errorCode."] ".$errorMsg;   // 分電盤の電源が入ってないとここでエラーだ
			echo $logStr . "<br />";
		}
		
		socket_close($sock_send);
		socket_close($sock_receive);
	}  // if ($sock_send && $sock_receive)  // ソケット生成OK
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "Error : 受信時 socket_create に失敗しました。[".$errorCode."] ".$errorMsg;
		echo $logStr . "<br />";
	}
	
	$logStr = "set_property_battery_class end";
	writeLog2($logStr);

	echo $returnStr;
			
	// 　ケース1) "on,1,60%”
	// 　　on : 蓄電池の電源がON
	// 　	1 : 運転動作状態 (1:充電　2:放電　3:待機)
	// 	60% : 蓄電残量
	// 
	// 　ケース2) "off,,”
	// 　　off : 蓄電池の電源がOFF
	// 
	// 　ケース3) “,,”
	// 　　蓄電池との通信が出来ない

?>