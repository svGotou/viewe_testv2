<?php
	// 蓄電池のプロパティを取得する
	// http://viewe-morioka.ddo.jp/get_property_battery_class.php
	// http://192.168.1.100/get_property_battery_class.php
	// インスタンスリストに、d6 04 01013501、インスタンスが1個で且つ0x7Dがあれば蓄電池クラスを持つ蓄電池と判断(0x35はテスト環境の空気清浄器)
	// 念の為、メーカーコード、MACアドレスからベンダーコードをチェックすると尚良いな

	require_once 'battery_function.php';  // battery_functionの読み込み

	$logStr = "get_property_battery_class start";
 	writeLog2($logStr);

	$retIP = getIP_ByFile();  // ファイルに記録されているIPアドレスを返す
	//echo "getIP_ByFile retIP=" . $retIP . "<br />";

	$retStr = ",,";  // web vieweに戻す値
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
					//echo "getProperty_ByIP()実行1<br />";
					$logStr = "getProperty_ByIP()実行1";
					//echo $logStr . "<br />";  // DEBUG
					writeLog2($logStr);
					
					$retStr = getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port);  // 蓄電池クラスのプロパティを取得
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
				}
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
	
	writeLog2("retStr = " . $retStr);
	
	$logStr = "get_property_battery_class end";
	writeLog2($logStr);

	echo $retStr;
	//return $retStr;
	
	// 戻り値
	// 　ケース1) "on,1,60%”
	// 　　on : 蓄電池の電源がON
	// 　	    1 : 運転動作状態 (1:充電　2:放電　3:待機)
	//     60% : 蓄電残量
	// 
	// 　ケース2) "off,,”
	// 　　off : 蓄電池の電源がOFF
	// 
	// 　ケース3) “,,”
	// 　　蓄電池との通信が出来ない

?>