<?php
	date_default_timezone_set('Asia/Tokyo');
	
	if (!function_exists('hex2bin')) {
	    function hex2bin($data) {
	        static $old;
	        if ($old === null) {
	            $old = version_compare(PHP_VERSION, '5.2', '<');
	        }
	        $isobj = false;
	        if (is_scalar($data) || (($isobj = is_object($data)) && method_exists($data, '__toString'))) {
	            if ($isobj && $old) {
	                ob_start();
	                //echo $data;
	                $data = ob_get_clean();
	            }
	            else {
	                $data = (string) $data;
	            }
	        }
	        else {
	            trigger_error(__FUNCTION__.'() expects parameter 1 to be string, ' . gettype($data) . ' given', E_USER_WARNING);
	            return;//null in this case
	        }
	        $len = strlen($data);
	        if ($len % 2) {
	            trigger_error(__FUNCTION__.'(): Hexadecimal input string must have an even length', E_USER_WARNING);
	            return false;
	        }
	        if (strspn($data, '0123456789abcdefABCDEF') != $len) {
	            trigger_error(__FUNCTION__.'(): Input string must be hexadecimal string', E_USER_WARNING);
	            return false;
	        }
	        return pack('H*', $data);
	    }
	}


// ファイルに記録されているIPアドレスを返す
function getIP_ByFile()
{
	$retStr = "";
	
	$filepath = "/var/www/batteryIP.txt";
	//chmod($filepath, 0777);  // パーミッションを変える
	// ターミナルからsoundvision@debian:~$ sudo chmod 777 /var/www/batteryIP.txtこれしないとだめだ
	
	if (file_exists($filepath))  // 在る
	{
		$addStr = file_get_contents($filepath);
// 		$fp = fopen($filepath, "r");
// 		$addStr = fread($fp, filesize($filepath));  // 1日前のcsvデータ
// 		fclose($fp);
//		echo "file有り-->addStr=" . $addStr . "<br />";
		
		if (strlen($addStr) >= 7)  // 7文字以上 "x.x.x.x"
		{
			$addArray = explode('.', $addStr);
			if (count($addArray) == 4)  // "x.x.x.x"
			{
				if (strlen($addArray[0]) > 0 && strlen($addArray[1]) > 0 && strlen($addArray[2]) > 0 && strlen($addArray[3]) > 0)
					$retStr = $addStr;
			}
		}
	}

	$logStr = "getIP_ByFile() " . $retStr;
	writeLog2($logStr);

	return $retStr;
}


// /* マルチキャストで蓄電池クラスの機器があるかどうかチェック (DEBUGは空気清浄器) */
function getIP_ByMulticast($sock_send, $sock_receive, $device_mode)
{
	$retStr = ",";
	$filepath = "/var/www/batteryIP.txt";
	
	// 蓄電池の有無を確認(ipアドレスとステータスを返す)
	//$sencmd = hex2bin("108100010e01010ef001620380008300d600");  // 動作状態&識別番号&自ノードインスタンスリスト S(0x62 Get プロパティ値読み出し要求 0e0101:ノードプロファイルクラスの自分 0ef001:ノードプロファイルクラスの相手?) 　MACアドレス取得する
	$sencmd = hex2bin("108100010e01010ef00162028000d600");  // MACアドレス取得しない
	// 10 81 0001 0e0101 0ef001　62 02 8000 d600
	// 10 --- EHD : ECHONET Header
	// 81 --- ECHONET Lite形式であることを示す(0x81 : 規定電文形式 0x82 : 任意電文形式)
	// 0001 --- TID:トランザクションID (電文の順番)
	// 0e0101 --- SEOJ:相手先ECHONET Liteオブジェクト　ノードプロファイルクラスの自分(NUC)
	// 0ef001 --- DEOJ:相手先ECHONET Liteオブジェクト　ノードプロファイルクラスの相手(マルチキャスト時の相手先)
	// 62 --- ESV:Get プロパティ値読み出し要求
	// 02 --- OPC:処理プロパティ数
	// 80 --- EPC1:動作状態と
	// 00 --- PDC1:EDTのサイズ
	// d6 --- EPC2:自ノードインスタンスリストSを知りたい
	// 00 --- PDC2:EDTのサイズ

	$toIP = "224.0.23.0";  // マルチキャスト用IPアドレス<--全機器に送信する場合(マルチキャスト) <--ルーターをマルチキャスト対応にする必要がある
	$port = 3610;  // 分電盤ポート番号

	$len = strlen($sencmd);
	$len2 = socket_sendto($sock_send, $sencmd, $len, 0, $toIP, $port);
	if ($len2)  // 送信OKか
	{
		$logStr = "Message : サーバーが ipアドレス $toIP のポート $port へ 0x".bin2hex($sencmd). " の ".$len2." byteを送信しました。";
		//echo $logStr."<br />";
		writeLog2($logStr);
		
		socket_set_option($sock_receive, IPPROTO_IP, MCAST_JOIN_GROUP, array("group" => '224.0.23.0', "interface" => 0,));  // マルチキャストグループに参加 (PHP 5.4で追加)<--コマンドで何か聞かれる時に有効なんだろうな
		
		$ipArray = array();
		$stsArray = array();
		$instanceArray = array();
	
		do
		{
			$buf = null;
			$fromIP = '';
		    $port = 0;
		   // socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒
		    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
		    if (! is_null($buf))
		    {
				$hexStr = bin2hex($buf);
				$logStr = "Message : サーバーが ipアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
				//echo $logStr."<br />";
				writeLog2($logStr);
				// サーバーが ipアドレス 192.168.1.9 のポート 3610 から 0x108100010ef0010e01017202800130d607020287010f8701 の 24 byteを受信しました。(EcoEye)
				// サーバーが ipアドレス 192.168.1.7 のポート 3610 から 0x108100010ef0010e01017202800130d60401013501 の 21 byteを受信しました。(DEBUGは空気清浄器)
// 				 0x10 81 0001 0ef001 0e0101 72 02 800130 d604 01013501 
// 				10 --- EHD : ECHONET Header
// 				81 --- ECHONET Lite形式であることを示す(0x81 : 規定電文形式 0x82 : 任意電文形式)
// 				0001 --- トランザクションID
// 				0ef001 --- SEOJ:送信元ECHONET Liteオブジェクト　空気清浄器？<--ノードだ
// 				0e0101 --- SEOJ:相手先ECHONET Liteオブジェクト　多分サーバー
// 				72 --- ESV:Get_Res プロパティ値読み出し応答
// 				02 --- OPC:処理プロパティ数
// 				80 --- EPC1:動作状態
// 				01 --- PDC1:1バイトの情報
// 				30 --- EDT1 : 起動中
// 				d6 --- EPC2:自ノードインスタンスリストS
// 				04 --- PDC2:4バイトの情報
// 				01013501 --- EDT2:空気清浄器
// 				　01 --- インスタンス総数?
// 				　013501 --- 空気清浄器クラス
// 				　　01 --- クラスグループコード
// 				　　35 --- クラスコード
// 				　　01 --- インスタンスコード

 				//0e0101 72 02 80 01を探す、次が動作状態
				$searchStr = '0e010172028001';
 				$pos1 = strpos($hexStr , $searchStr);
 				//echo "pos1=".$pos1."<br />";
 				$stsStr = '';
 				$instanceStr = '';
 				
				if ($pos1 !== FALSE)  //有り
 				{
					$stsStr = substr($hexStr, $pos1+strlen($searchStr), 2);  // 動作状態プロパティ '30'/'31'
					if (substr($hexStr, $pos1+strlen($searchStr)+2, 2) == 'd6')  // インスタンスリストプロパティ
					{
						//$instanceStr = substr($hexStr, $pos1+strlen($searchStr)+8, 6);
						//$instanceStr = substr($hexStr, strlen($hexStr)-6, 6);  //   ex.'013001' (家庭用エアコン)、'0f8701'(多機能分電盤)、'013501'(加湿空気清浄機)
						$instCnt = intval(substr($hexStr, $pos1+strlen($searchStr)+6, 2));  // インスタンスの数
						for ($i=0; $i<$instCnt; $i++)
						{
							if (strlen($instanceStr) > 0)
								$instanceStr .= ',';
							$instanceStr .= substr($hexStr, $pos1+strlen($searchStr)+8+$i*6, 6);  // 各機器のインスタンスをCSV形式で保存
						}
					}
					//echo "stsStr=".$stsStr."<br />";
					//echo "instanceStr=".$instanceStr."<br />";
					$ipArray[] = $fromIP;
					$instanceArray[] = $instanceStr;
					$stsArray[] = $stsStr;
				}
			}
		} while( !is_null($buf) );
		
		if ($device_mode != "battery")
			$searchStr = "013501";  // DEBUGは空気清浄器
		else  // 蓄電池
			$searchStr = "027d01";  // 本番は蓄電池
		
		$cnt = count($instanceArray);  // EcoEyeの機器数
		$logStr = "EcoNet Lite 機器数 : ".$cnt;
		//echo $logStr . "<br />";
		writeLog2($logStr);
		
		for ($i=0; $i<$cnt; ++$i)  // 機器数の繰り返し
		{
			$instArr = explode(',', $instanceArray[$i]);  // 各機器のインスタンス(csv形式)を配列へ
			$cnt2 = count($instArr);  // 各機器のインスタンス数
			for ($k=0; $k<$cnt2; ++$k)  // インスタンス数の繰り返し
			{
				//echo $instArr[$k] . "<br />";
				if (strcmp($instArr[$k], $searchStr) == 0)  // 空気清浄器/蓄電池クラスと一致
				{
					$retStr = $ipArray[$i] . "," . $stsArray[$i];  // 蓄電池のipアドレス検出できた
					file_put_contents($filepath, $ipArray[$i]);  // IPアドレスの書き込み
					break;
				}
			}
		}
	}
	else  // 送信NG
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "Error : EcoNet Lite 送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg;
		//echo $logStr . "<br />";
		writeLog2($logStr);
	}
	
	writeLog2("getIP_ByMulticast() retStr =  " . $retStr);
	
	return $retStr;
}


/* 蓄電池クラスのプロパティを取得 (DEBUGは空気清浄器) */
function getProperty_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port)
{
	$retStr = ",,";
	
	if ($device_mode != "battery")  // 空気清浄器
		$sencmd = hex2bin("1081000105ff0101350162038000a000c000");  // 動作、風量、空気汚れ (DEBUGは空気清浄器)
	else  // 蓄電池
		$sencmd = hex2bin("1081000105ff01027d0162038000cf00e400");  // 動作、運転モード、蓄電残量 (本番は蓄電池)
					
	// 空気清浄器のプロバティ
	// 10 81 0001 05ff01 013501 62 03 80 00 a0 00 c0 00
	// 10 --- EHD : ECHONET Header
	// 81 --- ECHONET Lite形式であることを示す(0x81 : 規定電文形式 0x82 : 任意電文形式)
	// 0001 --- TID:トランザクションID (電文の順番)
	// 05ff01 --- EOJが05FFはコントローラ
	// 013501 --- 01:グループコード 35:クラスコード(0135でEOJ=機器オブジェクト番号) 01:インスタンスコード = 空気清浄器クラス
	// 62 --- ESV:Get プロパティ値読み出し要求
	// 03 --- OPC:処理プロパティ数
	// 80 --- EPC1:動作状態
	// 00 --- PDC1:EDTのサイズ
	// a0 --- EPC2:風量
	// 00 --- PDC2:EDTのサイズ
	// c0 --- EPC3:空気汚れ検知状態
	// 00 --- PDC3:EDTのサイズ
	
	// 蓄電池のプロバティ
	// 10 81 0001 05ff01 027d01 62 03 8000 cf00 e400
	// 027d01 --- 02:グループコード 7D:クラスコード(027dでEOJ=機器オブジェクト番号) 01:インスタンスコード = 蓄電池クラス
	// 62 --- ESV:Get プロパティ値読み出し要求
	// 03 --- OPC:処理プロパティ数
	// 80 --- EPC1:動作状態
	// 00 --- PDC1:EDTのサイズ
	// cf --- EPC2:運転動作状態
	// 00 --- PDC2:EDTのサイズ
	// e4 --- EPC3:蓄電残量
	// 00 --- PDC3:EDTのサイズ
				
	// 受信処理
	$len = strlen($sencmd);
	$len2 = socket_sendto($sock_send, $sencmd, $len, 0, $retIP, $port);
	if ($len2)  // 送信OKか
	{
		$logStr = $retIP. " のポート ".$port." へ 0x".bin2hex($sencmd). " の ".$len2." byteを送信しました。受信処理に入ります。";
		//echo $logStr."<br />";
		writeLog2($logStr);
		
		// 受信処理
	    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
		if (! is_null($buf))
		{
		    $hexStr = bin2hex($buf);
		    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
			//echo $logStr."<br />";
			writeLog2($logStr);
			
			// 内容を調べてプロバティの値を返す				
			if (strlen($hexStr) == 21*2)  // 21byte返っているか
			{
				if ($device_mode != "battery")  // 空気清浄器(DEBUGは空気清浄器)
				{
					// DEBUGは空気清浄器
					// $hexStr = "1081000101350105ff017203800131a00131c00142"
					// echo "on,auto,0";  // 電源ONの時は、風量、空気汚れも返す
					// echo "off,,,";  // 電源OFFの時は、電源OFFのみ返す
					// echo ",,"
					$searchStr1 = '01350105ff0172038001';
					$pos1 = strpos($hexStr , $searchStr1);
					if ($pos1 !== FALSE)  //有り
					{
						$stsStr1 = substr($hexStr, $pos1+strlen($searchStr1), 2);  // 動作状態プロパティ '30'/'31'
						if ($stsStr1 == "30")  // 電源ON
						{
							$retStr = "on,";

							$searchStr2 = 'a001';  // 風量
							$pos2 = strpos($hexStr , $searchStr2);
							if ($pos2 !== FALSE)  // 風量有り
							{
								$stsStr2 = substr($hexStr, $pos2+strlen($searchStr2), 2);  // 風量プロパティ '41', '31' - '38'
								if ($stsStr2 == '41')
									$retStr = $retStr . 'auto';
								else
									$retStr = $retStr . substr($stsStr2, 1, 1); 
							}
							$retStr = $retStr . ',';
							
							$searchStr3 = 'c001';  // 空気汚れ
							$pos3 = strpos($hexStr , $searchStr3);
							if ($pos3 !== FALSE)  //  空気汚れ有り
							{
								$stsStr3 = substr($hexStr, $pos3+strlen($searchStr3), 2);  // 空気汚れプロパティ '41', '42'
								if ($stsStr3 == '41')  // 汚れ有り
									$retStr = $retStr . '1';
								else
									$retStr = $retStr . '0';
							}
						}
						else  // 電源OFF
						{
							$retStr = "off,,";
						}
					}  // if ($pos1 !== FALSE)  //有り
				}  // 空気清浄器
				else  // 蓄電池
				{
					// 本番は蓄電池
					// $hexStr = "10810001 027d01 05ff01 72 03 800131 cf0143 e40164"
					// echo "on,2,100%";  // 電源ONの時は、運転動作状態(1:充電/2:放電/3:待機)と蓄電残量を返す
					// echo "off,,";  // 電源OFFの時は、電源OFFのみを返す
					// echo ",,"
					$searchStr1 = '027d0105ff0172038001';
					$pos1 = strpos($hexStr , $searchStr1);
					if ($pos1 !== FALSE)  //有り
					{
						$stsStr1 = substr($hexStr, $pos1+strlen($searchStr1), 2);  // 動作状態プロパティ '30'/'31'
						if ($stsStr1 == "30")  // 電源ON
						{
							$retStr = "on,";

							$searchStr2 = 'cf01';  // 運転動作状態
							$pos2 = strpos($hexStr , $searchStr2);
							if ($pos2 !== FALSE)  // 運転動作状態有り
							{
								$stsStr2 = substr($hexStr, $pos2+strlen($searchStr2), 2);  // 運転動作状態 '42' '43' '44'
								if ($stsStr2 == '42')  // 充電
									$retStr = $retStr . '1';
								else if ($stsStr2 == '43')  // 放電
									$retStr = $retStr . '2';
								else if ($stsStr2 == '44')  // 待機
									$retStr = $retStr . '3';
							}
							$retStr = $retStr . ',';
						
							$searchStr3 = 'e401';  // 蓄電残量
							$pos3 = strpos($hexStr , $searchStr3);
							if ($pos3 !== FALSE)  //  蓄電残量有り
							{
								$stsStr3 = substr($hexStr, $pos3+strlen($searchStr3), 2);  // 蓄電残量プロパティ 0x00-0x64
								//$retStr = $retStr . hexdec($stsStr3) . '%';  // 16進文字列を10進文字列に変換(0-100%)
								$retStr = $retStr . hexdec($stsStr3);  // 16進文字列を10進文字列に変換(0-100%)
							}
						}
						else  // 電源OFF
						{
							$retStr = "off,,";
						}
					}  // if ($pos1 !== FALSE)  //有り
				}  // 蓄電池
			}  // if (strlen($hexStr) == 21*2)  // 21byte返っているか
		}  // if (! is_null($buf))
		else
		{
			$logStr = "bufなし";
			//echo $logStr . "<br />";
			writeLog2($logStr);
		}
	}  // if ($len2)  // 送信OKか
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logSt = "getProperty_ByIP() 送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg;
		//echo $logStr . "<br />";
		writeLog2($logStr);
	}
	
	return $retStr;
	
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
}



/* 蓄電池クラスのプロパティを設定 (DEBUGは空気清浄器) */
function setProperty($sock_send, $sock_receive, $device_mode, $retIP, $port, $cmdStr, $paramStr)
{
	$returnStr = "ng";
	
	//echo "device_mode=" . $device_mode . "<br />";
	//echo "retIP=" . $retIP . "<br />";
	//echo "port=" . $port . "<br />";
	
	// 蓄電池/空気清浄器のプロバティ設定する
	if ($device_mode != "battery")  // DEBUGは空気清浄器
	{
		if ($cmdStr == "power")
			$sencmd = hex2bin("1081000105ff0101350161018001" . $paramStr);  // 電源 : ON/OFF
		else if ($cmdStr == "wind")
			$sencmd = hex2bin("1081000105ff010135016101a001" . $paramStr);  // 風量 : 自動/1-8
			
		// 空気清浄器のプロバティ
		// 10 81 0001 05ff01 013501 61 01 80 01 30
		// 10 --- EHD : ECHONET Header
		// 81 --- ECHONET Lite形式であることを示す(0x81 : 規定電文形式 0x82 : 任意電文形式)
		// 0001 --- TID:トランザクションID (電文の順番)
		// 05ff01 --- EOJが05FFはコントローラ
		// 013501 --- 01:グループコード 35:クラスコード(0135でEOJ=機器オブジェクト番号) 01:インスタンスコード = 空気清浄器クラス
		// 61  --- SetC プロパティ値書き込み要求(応答要)
		// 01 --- OPC:処理プロパティ数
		// 80 --- EPC1:動作状態
		// 01 --- PDC1:EDTのサイズ
		// 30 --- 電源ON
	}
	else  // 本番は蓄電池
	{
		// if ($cmdStr == "power") <--不可
			// $sencmd = hex2bin("1081000105ff0101350161018001" . $paramStr);  // 電源 : ON/OFF
		if ($cmdStr == "drive")
		{
			if ($paramStr == '1')  // 充電
				$stsStr = '42';
			else if ($paramStr == '2')  // 放電
				$stsStr = '43';
			else if ($paramStr == '3')  // 待機
				$stsStr = '44';

			$sencmd = hex2bin("1081000105ff01027d016101da01" . $stsStr);  // 本番は蓄電池
			
			// 蓄電池のプロバティ
			// 運転モード(充電0x42 / 放電0x43 / 待機0x44) 電源off/onはできない
			// 10 81 0001 05ff01 027d01 61 01 da 01 42
			// 027d01 --- 02:グループコード 7D:クラスコード(027dでEOJ=機器オブジェクト番号) 01:インスタンスコード = 蓄電池クラス
			// 61  --- SetC プロパティ値書き込み要求(応答要)
			// 01 --- OPC:処理プロパティ数
			// da --- EPC1:運転モード設定
			// 01 --- PDC1:EDTのサイズ
			// 42--- 充電
		}
	}

	$len = strlen($sencmd);
	$len2 = socket_sendto($sock_send, $sencmd, $len, 0, $retIP, $port);
	if ($len2)  // 送信OKか
	{
		$logStr = $retIP. " のポート ".$port." へ 0x".bin2hex($sencmd). " の ".$len2." byteを送信しました。受信処理に入ります。";
		//echo $logStr."<br />";
		writeLog2($logStr);

		// 受信処理
	    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
		if (! is_null($buf))
		{
		    $hexStr = bin2hex($buf);
		    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
			//echo $logStr."<br />";
			writeLog2($logStr);
			
			// 内容を調べて返事を返す				
			if (strlen($hexStr) == 14*2)  // 28byte返っているか
			{
				if ($device_mode != "battery")  // 空気清浄器
				{
					// 0x1081000101350105ff0171018000
					$searchStr = '01350105ff017101';
					$pos = strpos($hexStr , $searchStr);
					if ($pos !== FALSE)  //有り
					{
						$stsStr = substr($hexStr, $pos+strlen($searchStr), 4);  // プロパティ '8000', 'a000'
						if ($stsStr == '8000' || $stsStr == 'a000')
							$returnStr = "ok";
					}
				}  // 空気清浄器
				else  // 蓄電池
				{
					$searchStr = '027d0105ff017101';
					$pos = strpos($hexStr , $searchStr);
					if ($pos !== FALSE)  //有り
					{
						$stsStr = substr($hexStr, $pos+strlen($searchStr), 4);  // プロパティ 'da00'
						if ($stsStr == 'da00')
							$returnStr =  "ok";
					}
				}
			}
		}
		else
		{
			$logStr = "bufなし";
			//echo $logStr . "<br />";
			writeLog2($logStr);
		}
	}
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		//echo "送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg."<br />";
		$logStr = "setProperty() 送信時 socket_sendto に失敗しました。[" . $errorCode . "] " . $errorMsg;
		//echo $logStr ."<br />";
		writeLog2($logStr);
		//usleep(200000);  //0.2秒待つ
	}  // if ($len2)  // 送信OKか
	
	//echo $returnStr;
	
	writeLog2("setProperty() returnStr = " . $returnStr);
	
	return $returnStr;
}


// 積算充電電力量計測値, 積算放電電力量計測値を得る
function getData_ByIP($sock_send, $sock_receive, $device_mode, $retIP, $port)
{
	$retStr = ",";   // 積算充電電力量計測値, 積算放電電力量計測値
		
	if ($device_mode != "battery")  // 空気清浄器
	{
		$sencmd = hex2bin("1081000105ff010135016202a000c000");  // 風量、空気汚れ (DEBUGは空気清浄器)
		$recDataLen = 18;
	}
	else  // 蓄電池
	{
		$sencmd = hex2bin("1081000105ff01027d016202a800a900");  // 積算充電電力量計測値, 積算放電電力量計測値 (本番は蓄電池)
		$recDataLen = 24;
	}
	
	// 空気清浄器のプロバティ
	// 10 81 0001 05ff01 013501 62 02 a000 c000
	// 10 --- EHD : ECHONET Header
	// 81 --- ECHONET Lite形式であることを示す(0x81 : 規定電文形式 0x82 : 任意電文形式)
	// 0001 --- TID:トランザクションID (電文の順番)
	// 05ff01 --- EOJが05FFはコントローラ
	// 013501 --- 01:グループコード 35:クラスコード(0135でEOJ=機器オブジェクト番号) 01:インスタンスコード = 空気清浄器クラス
	// 62 --- ESV:Get プロパティ値読み出し要求
	// 02 --- OPC:処理プロパティ数
	// a0 --- EPC1:風量
	// 00 --- PDC1:EDTのサイズ
	// c0 --- EPC2:空気汚れ検知状態
	// 00 --- PDC2:EDTのサイズ
	
	// 蓄電池のプロバティ
	// 10 81 0001 05ff01 027d01 62 02 a800 a900
	// 027d01 --- 02:グループコード 7D:クラスコード(027dでEOJ=機器オブジェクト番号) 01:インスタンスコード = 蓄電池クラス
	// 62 --- ESV:Get プロパティ値読み出し要求
	// 02 --- OPC:処理プロパティ数
	// cf --- EPC1:積算充電電力量計測値
	// 00 --- PDC1:EDTのサイズ
	// e4 --- EPC2:積算放電電力量計測値
	// 00 --- PDC2:EDTのサイズ
	
	// 送信処理
	$len = strlen($sencmd);
	$len2 = socket_sendto($sock_send, $sencmd, $len, 0, $retIP, $port);
	if ($len2)  // 送信OKか
	{
		$logStr = $retIP. " のポート ".$port." へ 0x".bin2hex($sencmd). " の ".$len2." byteを送信しました。受信処理に入ります。";
		//echo $logStr."<br />";
		writeLog2($logStr);
		
		// 受信処理
	    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
		if (! is_null($buf))
		{
		    $hexStr = bin2hex($buf);
		    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
			//echo $logStr."<br />";
			writeLog2($logStr);
			
			// 内容を調べてプロバティの値を返す				
			if (strlen($hexStr) == $recDataLen*2)  // $recDataLen byte返っているか(18 or 24)
			{
				//echo "18byte返っている<br />";
				//echo "device_mode=" . $device_mode . "<br />";
				if ($device_mode != "battery")  // 空気清浄器(DEBUGは空気清浄器)
				{
					// DEBUGは空気清浄器
					// $hexStr = "10810001 013501 05ff01 72 02 a00131 c00142"
					// echo "123456789,123456789";  // ダミーの積算充電電力量計測値と積算放電電力量計測値
					// echo ",,"
					$searchStr = '01350105ff017202';
					$pos = strpos($hexStr , $searchStr);
					if ($pos !== FALSE)  //有り
					{
						//echo "01350105ff017202有り<br />";
						$zyuStr = strval(round(intval(strtotime('0 day')) / 1000));  // ダミーの積算充電電力量計測値 (saveDataでも1/1000してるがいいか)
						$houStr = strval(round(intval(strtotime('0 day')) / 1000));  // ダミーの積算放電電力量計測値 (saveDataでも1/1000してるがいいか)
						$retStr = $zyuStr . "," . $houStr;
						
					}  // if ($pos1 !== FALSE)  //有り
				}  // 空気清浄器
				else  // 蓄電池
				{
					// 本番は蓄電池
					// $hexStr = "10810001 027d01 05ff01 72 02 800131 a80400aabbcc a90400aabbcc"
					// echo "on,2,100%";  // 電源ONの時は、運転動作状態(1:充電/2:放電/3:待機)と蓄電残量を返す
					// echo "off,,";  // 電源OFFの時は、電源OFFのみを返す
					// echo ",,"
					$searchStr1 = '027d0105ff017202';
					$pos1 = strpos($hexStr , $searchStr1);
					if ($pos1 !== FALSE)  //有り
					{
						$searchStr2 = 'a804';  // 積算充電電力量計測値
						$pos2 = strpos($hexStr , $searchStr2);
						if ($pos2 !== FALSE)  // 積算充電電力量計測値有り
						{
							$zyuStr = substr($hexStr, $pos2+strlen($searchStr2), 8);  // 積算充電電力量計測値
							$zyuStr = hexdec($zyuStr);  // 16進文字列を10進文字列に変換
						}
					
						$searchStr3 = 'a904';  // 積算放電電力量計測値
						$pos3 = strpos($hexStr , $searchStr3);
						if ($pos3 !== FALSE)  //  積算放電電力量計測値有り
						{
							$houStr = substr($hexStr, $pos3+strlen($searchStr3), 8);  // 積算放電電力量計測値
							//$houStr = hexdec($houStr) . '%';  // 16進文字列を10進文字列に変換(0-100%)
							$houStr = hexdec($houStr);  // 16進文字列を10進文字列に変換(0-100%)
						}
						
						$retStr = $zyuStr . "," . $houStr;
					}  // if ($pos1 !== FALSE)  //有り
				}  // 蓄電池
			}  // if (strlen($hexStr) == 21*2)  // 21byte返っているか
		}  // if (! is_null($buf))
		else
		{
			$logStr = "bufなし";
			//echo $logStr . "<br />";
			writeLog2($logStr);
		}
	}  // if ($len2)  // 送信OKか
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "getData_ByIP 送信時 socket_sendto に失敗しました。[" . $errorCode . "] " . $errorMsg;
		//echo $logStrg . "<br />";
		writeLog2($logStr);
	}

	//echo "retStr=" . $retStr . "<br />";
	//$logStr = "getData_ByIP() retStr = " . $retStr;
	//writeLog2($logStr);
	
	return $retStr;
}


/* 自分(サーバー)のローカルIPアドレス取得 */
function getSelfIPaddr()
{
	$ipAddress = "0.0.0.0";

	//$logStr = "getSelfIPaddr start";
	//writeLog2($logStr);

	$ifcStr = shell_exec( '/sbin/ifconfig' );  // ターミナルのコマンドを実行

	/*eth0      Link encap:イーサネット  ハードウェアアドレス ec:a8:6b:fe:1e:ec 
          inetアドレス:192.168.1.100 ブロードキャスト:192.168.1.255  マスク:255.255.255.0
          inet6アドレス: 2408:213:45c3:9700:eea8:6bff:fefe:1eec/64 範囲:グローバル
          inet6アドレス: fe80::eea8:6bff:fefe:1eec/64 範囲:リンク
          UP BROADCAST RUNNING MULTICAST  MTU:1500  メトリック:1
          RXパケット:508 エラー:0 損失:0 オーバラン:0 フレーム:0
          TXパケット:200 エラー:0 損失:0 オーバラン:0 キャリア:0
      衝突(Collisions):0 TXキュー長:1000 
          RXバイト:58188 (56.8 KiB)  TXバイト:27192 (26.5 KiB)
          割り込み:20 メモリ:f7c00000-f7c20000 

	lo        Link encap:ローカルループバック  
          inetアドレス:127.0.0.1 マスク:255.0.0.0
          inet6アドレス: ::1/128 範囲:ホスト
          UP LOOPBACK RUNNING  MTU:16436  メトリック:1
          RXパケット:8 エラー:0 損失:0 オーバラン:0 フレーム:0
          TXパケット:8 エラー:0 損失:0 オーバラン:0 キャリア:0
      衝突(Collisions):0 TXキュー長:0 
          RXバイト:480 (480.0 B)  TXバイト:480 (480.0 B)*/

	$ifcArray = explode(" ", $ifcStr);  // 戻り値を配列に
	for ($i=0; $i<count($ifcArray); $i++)
	{
		if (strpos($ifcArray[$i], "192.168.") !==FALSE )  // ==だと0(先頭)が返ってくるとFALSEと判断してなしになる
		{
			$ipAddress = $ifcArray[$i];
			$ifcArray2 = explode(":", $ipAddress);  // 戻り値を配列に
			$ipAddress2 = $ifcArray2[1];
			
			//$logStr = "getSelfIPaddr end";
			//writeLog2($logStr);
			
			return $ipAddress2;
		}
	}
	
	if ($ipAddress == "0.0.0.0")
	{
		$logStr = "サーバーのローカルIPアドレスの取得に失敗しました。";
		//echo $logStr."<br />";
		writeLog2($logStr);
	}

	//$logStr = "getSelfIPaddr end";
	//writeLog2($logStr);

	return $ipAddress;
}


function writeLog2($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}


?>