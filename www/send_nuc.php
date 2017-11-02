<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
</head>

<?php
	// http://192.168.1.100/send_nuc_2sun_sepa.php
	// http://viewe-morioka.ddo.jp/send_nuc_2sun_sepa.php
	// 全てを分電盤メータリングクラスに対応し、
	// 住宅用太陽光発電クラス(max2チャンネル)にも対応する 2017/5/25
	// 一括取得は止めて(初期のEcoEyeは対応していない)、各chにコマンド送信する方法 2017/5/31
	// http://192.168.1.100/send_nuc_battery.php
	// http://viewe-morioka.ddo.jp/send_nuc_battery.php
	// 蓄電器を拡張計測ユニットの第7チャンネルに接続し、双方向プロパティ(0xBA)で取得 2017/6/19
	// チャンネル数カウント方法変更 2017.6.27
	// 蓄電池の本番環境対応 2017.10.25
	
	date_default_timezone_set('Asia/Tokyo');

	// http://php.net/manual/ja/function.hex2bin.php
	
	$logStr = "send_nuc start";
	writeLog2($logStr);

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
	                echo $data;
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

	// DBへのコネクト
	$flag = TRUE;
	require_once 'db_info.php';  // DBの情報
	if (! $db = mysql_connect("localhost", $userStr, $pathStr))
	{
		$flag = FALSE;
	}
	
	if ($flag == TRUE)
	{
		$db_name = "ECOEyeDB01";
		if (! mysql_select_db($db_name, $db))
		{
			$flag = FALSE;
		}
	}
	
	if ($flag == TRUE)  // DB準備OK
	{
		$setInfoRec = getSetInfo();  // チャンネル名等の読み込み
		//echo "setInfoRec=".$setInfoRec."<br />";
		$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
		$itemCnt = count($setInfoArr);
		//echo "chanCnt=".$chanCnt."<br />";
		$batteryUsed = $setInfoArr [$itemCnt-1];  // 蓄電器使用flag (分電盤メータリングクラスの拡張計測ユニットの第7チャンネルを見る見ないを制御)
		$solarUsed = $setInfoArr [$itemCnt-2];  // 太陽光使用flag (基本的には未使用。$solarInstanceを使用)
		$solarInstance = false;  //  太陽光クラスの有無チェック結果)
		$cuteUsed = $setInfoArr [$itemCnt-3];  // エコキュート使用flag (未使用)
		//echo "batteryUsed = ".$batteryUsed."<br />";
		//echo "solarUsed = ".$solarUsed."<br />";
		//echo "cuteUsed = ".$cuteUsed."<br />";
		$chanCnt = $setInfoArr[0] * 4 + 16;  // 2017.6.27
		$logStr = "channel count=".$chanCnt." batteryUsed = ".$batteryUsed." solarUsed = ".$solarUsed. " cuteUsed = ".$cuteUsed;
		//echo $logStr."<br />";  // DEBUG
		writeLog2($logStr);
		
		$tbl_name = "bundenban3";  // 本番
		//$tbl_name = "bundenban_test";  // DEBUG

		if (! table_exists($db_name, $tbl_name, $db))
		{
			// テーブル作成用SQL文
			$str_sql = "CREATE TABLE {$tbl_name} ("
						."autokey INT(11) NOT NULL AUTO_INCREMENT,"
						."yymmddhms DATETIME NOT NULL,"
						."channel INT(4) NOT NULL,"
						."kwh INT(11) NOT NULL,"
						."PRIMARY KEY(autokey));";

			// SQL文の実行
			$query = mysql_query($str_sql, $db);
			$errNo = mysql_errno($db);
			if ($errNo)
			{
				$logStr = mysql_errno($db) . ": " . mysql_error($db);
				writeLog2($logStr);
			}
			else
			{
				$logStr = "テーブル「{$tbl_name}」を作成しました。";
				writeLog2($logStr);
			}
			
			// テーブルの一覧表示
			show_tables($db_name, $db);
			echo "<br />";
			
			// フィールド属性の一覧表示
			show_fields($db_name, $tbl_name, $db);
			echo "<br />";
		}
		else  // テーブルが存在する場合
		{
			$logStr = "テーブル「{$tbl_name}」は作成済みです。";
		}
		
// 		$setInfoRec = getSetInfo();  // チャンネル情報の読み込み
// 		echo "setInfoRec = " . $setInfoRec . "<br />";
// 		if ($setInfoRec == "") // チャンネル情報が無い時は、maxの32ch(typeが4)
// 		{
// 			$typeIndex = 4;
// 		}
// 		else
// 		{
// 			$setInfoArr = explode(',', $setInfoRec);  // csvを配列へ
// 			$typeIndex = intval($setInfoArr[0]);  // $setInfoArr[0]は分電盤タイプ 0:16ch 1:20ch 6:40ch
// 		}
// 		//echo "typeIndex = " . $typeIndex . "<br />";
// 		$maxChArr =  array(16, 20, 24, 28, 32);

		//$headData = hex2bin("108100010f01010f87016201");  // ユーザ定義コマンドのヘッダー (0f0101:　0f8701:EcoEye)
		$headData = hex2bin("1081000105ff010287016201");  // 分電盤メータリングクラス Get プロパティヘッダー(05FF01:コントローラ)
		//$headData_set = hex2bin("1081000105ff010287016101");  // 分電盤メータリングクラス SetC プロパティヘッダー
		$headData_sun1 = hex2bin("1081000105ff010279016201");  // 太陽光メータリングクラスインスタンス01 Get プロパティヘッダー
		$headData_sun2 = hex2bin("1081000105ff010279026201");  // 太陽光メータリングクラスインスタンス02 Get プロパティヘッダー
		
		$cmdArray = array();
		$cmdArray[0] = $headData.hex2bin("c000");  // 主幹正方向 : 買電量
		$cmdArray[1] = $headData.hex2bin("c100");  // 主幹逆方向 : 売電量
		$cmdArray[2] = $headData.hex2bin("d000");
		$cmdArray[3] = $headData.hex2bin("d100");
		$cmdArray[4] = $headData.hex2bin("d200");
		$cmdArray[5] = $headData.hex2bin("d300");
		$cmdArray[6] = $headData.hex2bin("d400");
		$cmdArray[7] = $headData.hex2bin("d500");
		$cmdArray[8] = $headData.hex2bin("d600");
		$cmdArray[9] = $headData.hex2bin("d700");
		$cmdArray[10] = $headData.hex2bin("d800");
		$cmdArray[11] = $headData.hex2bin("d900");
		$cmdArray[12] = $headData.hex2bin("da00");
		$cmdArray[13] = $headData.hex2bin("db00");
		$cmdArray[14] = $headData.hex2bin("dc00");
		$cmdArray[15] = $headData.hex2bin("dd00");
		$cmdArray[16] = $headData.hex2bin("de00");
		$cmdArray[17] = $headData.hex2bin("df00");
		$cmdArray[18] = $headData.hex2bin("e000");
		$cmdArray[19] = $headData.hex2bin("e100");
		$cmdArray[20] = $headData.hex2bin("e200");
		$cmdArray[21] = $headData.hex2bin("e300");
		$cmdArray[22] = $headData.hex2bin("e400");
		$cmdArray[23] = $headData.hex2bin("e500");
		$cmdArray[24] = $headData.hex2bin("e600");
		$cmdArray[25] = $headData.hex2bin("e700");
		$cmdArray[26] = $headData.hex2bin("e800");
		$cmdArray[27] = $headData.hex2bin("e900");
		$cmdArray[28] = $headData.hex2bin("ea00");
		$cmdArray[29] = $headData.hex2bin("eb00");
		$cmdArray[30] = $headData.hex2bin("ec00");
		$cmdArray[31] = $headData.hex2bin("ed00");
		$cmdArray[32] = $headData.hex2bin("ee00");
		$cmdArray[33] = $headData.hex2bin("ef00"); // ch1-ch32のコマンドはここまで
// 		if ($solarUsed == "YES")  // 太陽光使用
// 		{
//  			$cmdArray[34] = $headData_sun1.hex2bin("e100");  // 太陽光1の値をGet (headData_sun1とheadData_sun2が異なる)
//  			$cmdArray[35] = $headData_sun2.hex2bin("e100");  // 太陽光2の値をGet
// 		}
	    $ipAddr = getSelfIPaddr();  // 自分のIPアドレス
		$toIP = getEcoEyeIPaddr($ipAddr);  // EcoEye(多機能分電盤)IPアドレス
		//$toIP = "192.168.1.17";  // エミュレータipアドレス DEBUG
	    $port = 3610;  // 分電盤ポート番号
	    
	    if ($toIP != "0.0.0.0")  // 分電盤ポート検出
	    {
	    	$sendErrorNum = 0;  // 特定チャネルが2回送信エラーになったら以後の送信中止
	    	$receiveErrorNum = 0;  // 特定チャネルが2回受信エラーになったら以後の送信中止
	    	
		    // 送受信ソケット生成
			$sock_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 
			$sock_receive = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if ($sock_send && $sock_receive)  // ソケット生成OK
			{
				$errorFlag = FALSE;
				$hit_sun = false;  // 太陽光発電の計算有無
				$kwh_sun = 0;  // 太陽光発電の発電量
				
				$bindOK = socket_bind($sock_receive, $ipAddr, $port);  // 2017/2/6  forの最初だと受信漏れがあるかも
				socket_set_option($sock_send, SOL_SOCKET, SO_REUSEADDR, 1);  // host downになることがある <--これって1回切りの方が良いのでは？
				
				// 太陽光のインスタンスを返すか否かのチェックで、太陽光クラスにコマンドを送るか否か決定する
				// checkForEcoNetLite()を修正
				$solarInstArray = checkSolar($toIP, $sock_send, $sock_receive);  // 太陽光のインスタンスを返すか否かのチェック
				if ($solarInstArray[1] == true && $solarInstArray[2] == true)
				{
 					$cmdArray[34] = $headData_sun1.hex2bin("e100");  // 太陽光1の値をGet (headData_sun1とheadData_sun2が異なる)
 					$cmdArray[35] = $headData_sun2.hex2bin("e100");  // 太陽光2の値をGet
					$logStr = "solarInstArray[1] == true && solarInstArray[2] == true (太陽光発電クラス2つ)";
				}
				else if ($solarInstArray[1] == true)
				{
 					$cmdArray[34] = $headData_sun1.hex2bin("e100");  // 太陽光1の値をGet (headData_sun1とheadData_sun2が異なる)
					$logStr = "solarInstArray[1] == true (太陽光発電クラス1つ)";
				}
				else if ($solarInstArray[2] == true)
				{
					$cmdArray[34] = $headData_sun2.hex2bin("e100");  // 太陽光2の値をGet
					$logStr = "solarInstArray[2] == true (太陽光発電クラス1つ)";
				}
				else
				{
					$logStr = "solarInstArray[1] == no && solarInstArray[2] == no (太陽光発電クラスなし)";
				}
				//echo $logStr."<br />";  // DEBUG
				writeLog2($logStr);
				
				//$max_cmd = $maxChArr[$typeIndex] + 4;
				//echo "max channel=".$maxChArr[$typeIndex]."チャンネル<br />";
				if ($solarInstArray[0] == "30")
				{
					for ($i=0; $i<count($cmdArray); $i++)  // 全コマンド送信
					//for ($i=0; $max_cmd; $i++)  // 全コマンド送信
			    	{
			    		$k = $i;  // 保存
	
			    		// 送信処理
	// 		    		if ($i==0)  // 毎回やらなくてもいいよね
	// 						socket_set_option($sock_send, SOL_SOCKET, SO_REUSEADDR, 1);  // host downになることがある <--これって1回切りの方が良いのでは？
				    	$len = strlen($cmdArray[$i]);
				    	$len2 = socket_sendto($sock_send, $cmdArray[$i], $len, 0, $toIP, $port);
				    	
						if ($len2)  // 送信OKか
				  		{
							$sendErrorNum = 0;
							$memoStr = "";
							if ($i >= 34) $memoStr = " (太陽光発電クラス)";
				  			$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdArray[$i]). " の ".$len2." byteを送信しました。受信処理に入ります。".$memoStr;
				  			//echo $logStr."<br />";  // DEBUG
				  			writeLog2($logStr);
				  			
					  		// 受信処理
							if ($bindOK == true)  // バインドOKか
							{
								$fromIP = '';
							    $port = 0;
					  			if ($i==0)  // 毎回やらなくてもいいよね
					  			{
									socket_set_option($sock_receive, SOL_SOCKET, SO_REUSEADDR, 1);
									socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒  2016.06.17
								}
							    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
							    
							    if ($len > 0)  // 受信データあり
								 {
								    $hexStr = bin2hex($buf);
								    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。".$memoStr;
								    //echo $logStr."<br />";  // DEBUG
					  				writeLog2($logStr);
									
	 								if (substr($hexStr, 20 , 2) == "72")  // Get_Res プロパティ値読み出し応答 (ESV=0x62の応答)
	 								{
										$receiveErrorNum = 0;
	
										$sqlDate = date("Y-m-d H:i:s");  // 現在の日時
										
										if (substr($hexStr, 24 , 2) == "c0")  // 主幹 [正方向]
										{
											$cnl = 0;
										}
										else if (substr($hexStr, 24 , 2) == "c1")  // 主幹 [逆方向]
										{
											$cnl = 100;
										}
										else if (substr($hexStr, 8 , 4) == "0279")  // 太陽光(027901と027902を加算する)
										{
											$cnl = 102;
											$hit_sun = true;  // 太陽光発電有
										}
										else  // ch#1〜#32
										{
											$cnl = hexdec(substr($hexStr, 24 , 2)) - hexdec("d0") + 1;
										}
										
										//echo "cnl=".$cnl."<br />";
										
										if ($cnl == 102)  // 太陽光だ
										{
											$kwh_sun += hexdec(substr($hexStr, 28 , 8));  // 発電量加算
											// for抜けてからDB出力
										}
										else  // 太陽光でない == 主幹 [正方向] / 主幹 [逆方向] / ch1〜ch32
										{
											$kwh = hexdec(substr($hexStr, 28 , 8));  // 消費電力
											if ($kwh != 4294967294 && $kwh != 4278124286)  // 未使用(0xFFFFFFFE / 0xFEFEFEFE)でない
											{
												$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', $cnl, $kwh)";
												//echo "sql=".$sql."<br />****************************************<br />";  // DEBUG
												$query = mysql_query($sql, $db);
												$errNo = mysql_errno($db);
												if ($errNo)
												{
													$logStr = mysql_errno($db) . ": " . mysql_error($db);
													//echo $logStr."<br />";  // DEBUG
													writeLog2($logStr);
												}
												usleep(200000);  //0.2秒待つ
											}
										}
										
									}  // if (substr($hexStr, 20 , 2) == "72") 
									else  // 不可応答
									{
										 if (substr($hexStr, 20 , 1) == "5")  // "71"の肯定応答も返るので"5x"に絞る
										{
											$logStr = "不可応答受信5x " . $hexStr;
											//echo $logStr."<br />";  // DEBUG
											writeLog2($logStr);
											usleep(200000);  //0.2秒待つ
										}
									}
								}  //  if ($len > 0)
								else  // 受信NG
								{
									$errorCode = socket_last_error();
									$errorMsg = socket_strerror($errorCode);
									$logStr = $i+1 . "回目 受信時 socket_recvfrom2 に失敗しました。[" . $errorCode."] " . $errorMsg;
									//echo $logStr."<br />";  // DEBUG
									writeLog2($logStr);
	
									// 受信エラー後処理
									$receiveErrorNum += 1;
									if ($receiveErrorNum  == 1)  // 1回のエラー
									{
										$logStr = "受信エラー後処理 " . $receiveErrorNum. "回目";
										//echo $logStr."<br />";  // DEBUG
										writeLog2($logStr);
										$i = $k - 1;  // 同じチャンネルに送る
										usleep(200000);  //0.2秒待つ
									}
									else  // 2回以上のエラー
									{
										$logStr = "受信エラー後処理 " . $receiveErrorNum. "回目";
										//echo $logStr."<br />";  // DEBUG
										writeLog2($logStr);
										$errorFlag = TRUE;
										break;  // 中断
									}
								}
							}  // if ($bindOK == true)  // バインドOKか
							else  // バインドNG
							{
								$errorCode = socket_last_error();
								$errorMsg = socket_strerror($errorCode);
								$logStr = "受信時 socket_bind に失敗しました。[".$errorCode."] ".$errorMsg;   // 分電盤の電源が入ってないとここでエラーだ
								//echo $logStr."<br />";  // DEBUG
								writeLog2($logStr);
								usleep(200000);  //0.2秒待つ
							}
						}  // if ($len2)  // 送信OKか
						else  // 送信NG
						{
							$errorCode = socket_last_error();
							$errorMsg = socket_strerror($errorCode);
				    		$logStr = "送信時 socket_sendto2 に失敗しました。[".$errorCode."] ".$errorMsg;
				    		//echo $logStr."<br />";  // DEBUG
							writeLog2($logStr);
							
							// エラー後処理
							$sendErrorNum += 1;
							if ($sendErrorNum  == 1)  // 1回のエラー
							{
								$logStr = "送信エラー後処理 " . $sendErrorNum. "回目";
								//echo $logStr."<br />";  // DEBUG
								writeLog2($logStr);
								$i = $k - 1;  // 同じチャンネルに送る
								usleep(200000);  //0.2秒待つ
							}
							else  // 2回以上のエラー
							{
								$logStr = "送信エラー後処理 " . $sendErrorNum. "回目";
								//echo $logStr."<br />";  // DEBUG
								writeLog2($logStr);
								$errorFlag = TRUE;
								break;  // 中断
							}
						}
					}  // for ($i=0; $i<count($cmdArray); $i++)  // 全コマンド送信
					
					if (	$errorFlag == false)  // エラー無かった
					{
						if ($hit_sun == true)  // 太陽光あった(複数ch対応なのでfor抜けてから書き込む)
						{
							$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', 102, $kwh_sun)";  // 太陽光発電量
							//echo "sql=".$sql."<br />****************************************<br />";  // DEBUG
							$query = mysql_query($sql, $db);
							$errNo = mysql_errno($db);
							if ($errNo)
							{
								$logStr = mysql_errno($db) . ": " . mysql_error($db);
								//echo $logStr."<br />";  // DEBUG
								writeLog2($logStr);
							}
							usleep(200000);  //0.2秒待つ
						}
					}
					else  // エラーが発生していた
					{
						// EcoNet Lite規格で分電盤のステータスを聞いてlogを出力する
						// checkForEcoNetLite($ipAddr, $sock_send, $sock_receive);  //DEBUG
						usleep(200000);  //0.2秒待つ
					}
	
// 					// 蓄電器対応 ここから 2017.6.20
// 					if ($batteryUsed == "YES")  // 蓄電器使用
// 					{
// 						$headData1 = hex2bin("1081000105ff010287016101");  // 分電盤メータリングクラスへのコマンドヘッダー(応答必要)
// 						$cmdStr1 = $headData1.hex2bin("b9020107");  // 積算電力量計測チャネル範囲指定(双方向) [1chanelから7つ] 有効なチャンネル数を付加 (7つ目が蓄電器)
// 						//$headData1 = hex2bin("1081000105ff010287016201");  // 分電盤メータリングクラスへのコマンドヘッダー(応答必要) DEBUG
// 						//$cmdStr1 = $headData1.hex2bin("b800");  // 計測チャネル数 DEBUG
// 						$headData2 = hex2bin("1081000105ff010287016201");  // 分電盤メータリングクラスへのコマンドヘッダー
// 						$cmdStr2 = $headData2.hex2bin("ba00");  // 積算電力量計測値リスト(双方向）
// 						
// 						$len = strlen($cmdStr1);
// 						$len2 = socket_sendto($sock_send, $cmdStr1, $len, 0, $toIP, $port);  // 積算電力量計測チャネル範囲指定コマンド
// 						if ($len2)  // 送信OKか
// 						{
// 							$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdStr1). " の ".$len2." byteを送信しました。受信処理に入ります。(蓄電器)";
// 							//echo $logStr . "<br />";  // DEBUG
// 							writeLog2($logStr);
// 							$len3 = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
// 							 if ($len3 > 0) // 受信データあり
// 							 {
// 								$hexStr = bin2hex($buf);
// 								$logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。(蓄電器)";
// 								//echo $logStr . "<br />";  // DEBUG
// 								writeLog2($logStr);
// 								if (substr($hexStr, 20 , 2) == "71")  // Set_Res プロパティ値書き込み応答 (ESV=0x61の応答)
// 								{
// 									usleep(200000);  //0.2秒待つ
// 									$len4 = strlen($cmdStr2);
// 									$len5 = socket_sendto($sock_send, $cmdStr2, $len4, 0, $toIP, $port);  // 積算電力量計測値リスト取得コマンド
// 									if ($len5)  // 送信OKか
// 				  					{
// 										$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdStr2). " の ".$len5." byteを送信しました。受信処理に入ります。(蓄電器)";
// 										//echo $logStr . "<br />";  // DEBUG
// 										writeLog2($logStr);
// 										
// 										$fromIP = '';
// 										$port = 0;
// 										$len6 = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
// 										if ($len6 > 0)  // 受信データあり
// 										{
// 								    		$hexStr = bin2hex($buf);
// 											$logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。(蓄電器)";
// 											//echo $logStr . "<br />";  // DEBUG
// 											writeLog2($logStr);
// 											if (substr($hexStr, 20 , 2) == "72")  // Get_Res プロパティ値読み出し応答 (ESV=0x62の応答)
// 											{
// 												if (substr($hexStr, 22 , 6) == "01ba3a")  // 0xbaの応答で長さ0x3a
// 												{
// 													$kwh_meter1 = array(0, 0, 0, 0, 0, 0, 0);  // 正方向(太陽光発電の消費量、蓄電器の充電量)
// 													$kwh_meter2 = array(0, 0, 0, 0, 0, 0, 0);  // 逆方向(太陽光発電の発電量、蓄電器の放電量)
// 													for ($i=0; $i<7; $i++)
// 													{
// 														$kwhStr = substr($hexStr, 32+16*$i , 16);
// 														//echo "kwhStr=$kwhStr<br />";
// 														if (strlen($kwhStr) > 0)
// 														{
// 															$kwh_meter1[$i] = hexdec(substr($kwhStr, 0 , 8));  // 正方向(前半4byte)
// 															$kwh_meter2[$i] = hexdec(substr($kwhStr, 8 , 8));  // 逆方向(後半4byte)
// 														}
// 														
// 														//echo "kwh_meter1=$kwh_meter1[$i]<br />";
// 														//echo "kwh_meter2=$kwh_meter2[$i]<br />";
// 														
// 														if ($i == 6)  // 蓄電器(7ch)
// 														{
// 															if ($kwh_meter1[$i] != 4294967294 && $kwh_meter1[$i] != 4278124286)  // 未使用(0xFFFFFFFE / 0xFEFEFEFE)でない
// 															{
// 																$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', 105, $kwh_meter1[$i])";  // 正方向:蓄電器の充電量
// 																//echo "sql=".$sql."<br />****************************************<br />";  // DEBUG
// 																$query = mysql_query($sql, $db);  // 本番
// 																$errNo = mysql_errno($db);
// 																if ($errNo)
// 																{
// 																	$logStr = mysql_errno($db) . ": " . mysql_error($db);
// 																	//echo $logStr . "<br />";  // DEBUG
// 																	writeLog2($logStr);
// 																}
// 																usleep(200000);  //0.2秒待つ
// 															}
// 														}
// 														
// 														if ($i == 6)  // 蓄電器(7ch)
// 														{
// 															if ($kwh_meter2[$i] != 4294967294 && $kwh_meter2[$i] != 4278124286)  // 未使用(0xFFFFFFFE / 0xFEFEFEFE)でない
// 															{
// 																$sql = "INSERT INTO $tbl_name (yymmddhms, channel, kwh) VALUES ('$sqlDate', 104, $kwh_meter2[$i])";  //  逆方向:蓄電器の放電量
// 																//echo "sql=".$sql."<br />****************************************<br />";  // DEBUG
// 																$query = mysql_query($sql, $db);  // 本番
// 																$errNo = mysql_errno($db);
// 																if ($errNo)
// 																{
// 																	$logStr = mysql_errno($db) . ": " . mysql_error($db);
// 																	//echo $logStr . "<br />";  // DEBUG
// 																	writeLog2($logStr);
// 																}
// 																usleep(200000);  //0.2秒待つ
// 															}
// 														}
// 													}  // for
// 													
// 													//echo "太陽光発電の消費量=$kwh1<br />";
// 													//echo "太陽光発電の発電量=$kwh2<br />";
// 												}
// 											}  // if (substr($hexStr, 20 , 2) == "72") 
// 											else
// 											{
// 												// "72"じゃないエラー処理
// 												$logStr = "拡張計測ユニットの計測値リストGet不可応答";
// 												//echo $logStr . "<br />";  // DEBUG
// 												writeLog2($logStr);
// 												usleep(200000);  //0.2秒待つ
// 											}
// 										}  // if ($len6 > 0)  // 受信データあり
// 										else  // 受信NG
// 										{
// 											$errorCode = socket_last_error();
// 											$errorMsg = socket_strerror($errorCode);
// 											$logStr = $i . "回目 受信時 socket_recvfrom3 に失敗しました。[".$errorCode."] ".$errorMsg; 
// 											//echo $logStr . "<br />";  // DEBUG
// 											writeLog2($logStr);
// 											usleep(200000);  //0.2秒待つ
// 										}
// 									}  // if ($len5)  // 送信OKか
// 									else  // 送信NG
// 									{
// 										$errorCode = socket_last_error();
// 										$errorMsg = socket_strerror($errorCode);
// 							    		$logStr = "送信時 socket_sendto3 に失敗しました。[".$errorCode."] ".$errorMsg;
// 							    		//echo $logStr . "<br />";  // DEBUG
// 							    		writeLog2($logStr);
// 										usleep(200000);  //0.2秒待つ
// 									}
// 								}  // if (substr($hexStr, 20 , 2) == "71")
// 								else
// 								{
// 									// "71"じゃないエラー処理
// 									$logStr = "拡張計測ユニットのチャネル範囲Set不可応答";
// 									//echo $logStr . "<br />";  // DEBUG
// 									writeLog2($logStr);
// 									usleep(200000);  //0.2秒待つ
// 								}
// 							 }  //  if ($len3 > 0)
// 						}  // if ($len2)  // 送信OKか
// 						else  // 送信NG
// 						{
// 							$errorCode = socket_last_error();
// 							$errorMsg = socket_strerror($errorCode);
// 				    		$logStr = "送信時 socket_sendto4 に失敗しました。[".$errorCode."] ".$errorMsg;
// 						    //echo $logStr . "<br />";  // DEBUG
// 						   	writeLog2($logStr);
// 							usleep(200000);  //0.2秒待つ
// 						}
// 					}  // if ($batteryUsed == "YES")  蓄電器使用
// 					// 蓄電器対応 ここまで 2017.6.20
				}
				else  // if ($solarInstArray[0] == "30")
				{
		    		$logStr = $toIP . "のステータスが30でない";
				    //echo $logStr . "<br />";  // DEBUG
				   	writeLog2($logStr);
				}
				
				socket_close($sock_send);
				socket_close($sock_receive);
			}  // if ($sock_send && $sock_receive)  // ソケット生成OK
			else  // ソケット生成NG
			{
				$errorCode = socket_last_error();
				$errorMsg = socket_strerror($errorCode);
				$logStr = "受信時 socket_create に失敗しました。[".$errorCode."] ".$errorMsg;
				//echo $logStr."<br />";  // DEBUG
				writeLog2($logStr);
			}
		}  // 分電盤ポート検出NG
		else
		{
			$logStr = "分電盤のIPアドレスを取得出来ませんでした。";
			//echo $logStr."<br />";  // DEBUG
			writeLog2($logStr);
		}
		
		mysql_close($db);    // DBファイルを閉る
	}  // if ($flag == TRUE)  // DB準備OK
	else
	{
		$logStr = "データベース準備NG";
		//echo $logStr."<br />";  // DEBUG
		writeLog2($logStr);
	}
	
$logStr = "send_nuc end";
writeLog2($logStr);
$logStr = "";
writeLog2($logStr);


// チャンネル名取得
function getSetInfo()
{
	$setInfoRec = "";
	$filepath = "/var/www/channel_name48A.txt";
	//$filepath = "channel_name48A.txt";  // DEBUG
	if (file_exists($filepath))  // 在る
		$setInfoRec = file_get_contents($filepath);
		
	return $setInfoRec;
}


// 太陽光のインスタンスを返すか否かのチェック
// checkForEcoNetLite()を修正
function checkSolar($ecoEyeIP, $sock_send, $sock_receive)
{
	$solarInstArray = array("", false, false);

	$senCommand = hex2bin("108100010e01010ef00162028000d600");  // 動作状態&自ノードインスタンスリスト S(0x62 Get プロパティ値読み出し要求 0e0101:ノードプロファイルクラスの自分 0ef001:ノードプロファイルクラスの相手?)
	$toIP = "224.0.23.0";  // マルチキャスト用IPアドレス<--全機器に送信する場合(マルチキャスト) <--ルーターをマルチキャスト対応にする必要がある
	$port = 3610;  // 分電盤ポート番号

	$len = strlen($senCommand);
	$len2 = socket_sendto($sock_send, $senCommand, $len, 0, $toIP, $port);
	if ($len2)  // 送信OKか
	{
		//$logStr = "Message : サーバーが ipアドレス $toIP のポート $port へ 0x".bin2hex($senCommand). " の ".$len2." byteを送信しました。";
		//echo $logStr."<br />";
		
		socket_set_option($sock_receive, IPPROTO_IP, MCAST_JOIN_GROUP, array("group" => '224.0.23.0', "interface" => 0,));  // マルチキャストグループに参加 (PHP 5.4で追加)<--コマンドで何か聞かれる時に有効なんだろうな
		
		$ipArray = array();
		$stsArray = array();
		$instanceArray = array();
	
		do
		{
			$buf = null;
			$fromIP = '';
			$port = 0;
			socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒
		    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
			if (! is_null($buf))
			{
				$hexStr = bin2hex($buf);
				//$logStr = "Message : サーバーが ipアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
				//echo $logStr."<br />";
				// リモートアドレス 192.168.1.8(分電盤) のポート 3610 から 0x108100010ef0010e01017202800130d607020287010f8701 の 24 byteを受信しました。
				
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
		
		$cnt = count($instanceArray);  // EcoEyeの機器数
		//$logStr = "EcoNet Lite 機器数 : ".$cnt;
		//echo $logStr . "<br />";
		for ($i=0; $i<$cnt; ++$i)  // 機器数の繰り返し
		{
			//$logStr = "＜機器".strval($i+1) . "＞";
			//echo $logStr . "<br />";
			
			//$logStr = "　ipアドレス : ".$ipArray[$i];
			//echo $logStr . "<br />";
			if ($ipArray[$i] == $ecoEyeIP)  // EcoEyeだ
			{
				$logStr = "";
				$instArr = explode(',', $instanceArray[$i]);  // 各機器のインスタンス(csv形式)を配列へ
				$cnt2 = count($instArr);  // 各機器のインスタンス数
				for ($k=0; $k<$cnt2; ++$k)  // インスタンス数の繰り返し
				{
					$logStr .= "インスタンス ". strval($k+1) . " : " . $instArr[$k];
					if (strcmp($instArr[$k], "0f8701") == 0)  // 一致
						$logStr .= " (ユーザ定義クラス)　";
					else if (strcmp($instArr[$k], "028701") == 0)  // 一致
						$logStr .= " (分電盤メータリングクラス)　";
					else if (strcmp($instArr[$k], "027901") == 0)  // 一致
					{
						$solarInstArray[1] = true;
						$logStr .= " (太陽光クラス・インスタンス1)　";
					}
					else if (strcmp($instArr[$k], "027902") == 0)  // 一致
					{
						$solarInstArray[2] = true;
						$logStr .= " (太陽光クラス・インスタンス2)　";
					}
					else if (strcmp($instArr[$k], "027c01") == 0)  // 一致
						$logStr .= " (燃料電池クラス)　";
					else if (strcmp($instArr[$k], "028101") == 0)  // 一致
						$logStr .= " (水流量メータクラス・インスタンス1)　";
					else if (strcmp($instArr[$k], "028102") == 0)  // 一致
						$logStr .= " (水流量メータクラス・インスタンス2)　";
					else if (strcmp($instArr[$k], "028201") == 0)  // 一致
						$logStr .= " (ガスメータクラス・インスタンス1)　";
					else if (strcmp($instArr[$k], "028202") == 0)  // 一致
						$logStr .= " (ガスメータクラス・インスタンス2)　";
					else if (strcmp($instArr[$k], "028202") == 0)  // 一致
						$logStr .= " (ガスメータクラス・インスタンス2)　";
				}
				//echo $logStr;
				
				$solarInstArray[0] = $stsArray[$i];
				$logStr .= "ステータス : ".$stsArray[$i];
				if (strcmp($stsArray[$i], "30") == 0)  // 一致
						$logStr .= " (正常)";
				else if (strcmp($stsArray[$i], "31") == 0)  // 一致
						$logStr .= " (異常)";
				//echo $logStr . "<br />";
				writeLog2($logStr);
				break;
			}  // if ($ipArray[$i] == $ecoEyeIP)  // EcoEyeだ
		}  // for ($i=0; $i<$cnt; ++$i)  // 機器数の繰り返し
	}
	else  // 送信NG
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "Error : EcoNet Lite 送信時 socket_sendto5 に失敗しました。[".$errorCode."] ".$errorMsg;
		//echo $logStr . "<br />";
		writeLog2($logStr);
	}
	
	return $solarInstArray;
}


// EcoNet Lite規格で分電盤のステータスを聞いてlogを出力する
// function checkForEcoNetLite($ipAddr, $sock_send, $sock_receive)
// {
// 	$senCommand = hex2bin("108100010e01010ef00162028000d600");  // 動作状態&自ノードインスタン スリスト S
// 	$toIP = "224.0.23.0";  // マルチキャスト用IPアドレス<--全機器に送信する場合(マルチキャスト) <--ルーターをマルチキャスト対応にする必要がある
// 	$port = 3610;  // 分電盤ポート番号
// 
// 	$len = strlen($senCommand);
// 	$len2 = socket_sendto($sock_send, $senCommand, $len, 0, $toIP, $port);
// 	if ($len2)  // 送信OKか
// 	{
// 		$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($senCommand). " の ".$len2." byteを EcoNet Lite 送信しました。受信処理に入ります。";
// 		echo $logStr."<br />";
// 		//writeLog2($logStr);
// 		
// 		socket_set_option($sock_receive, IPPROTO_IP, MCAST_JOIN_GROUP, array("group" => '224.0.23.0', "interface" => 0,));  // マルチキャストグループに参加 (PHP 5.4で追加)<--コマンドで何か聞かれる時に有効なんだろうな
// 		
// 		$ipArray = array();
// 		$stsArray = array();
// 		$instanceArray = array();
// 	
// 		do
// 		{
// 			$buf = null;
// 			$fromIP = '';
// 		    $port = 0;
// 		    socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒
// 		    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
// 		    if (! is_null($buf))
// 		    {
// 				$hexStr = bin2hex($buf);
// 				$logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを EcoNet Lite 受信しました。";
// 				echo $logStr."<br />";
// 				//writeLog2($logStr);
// 				// リモートアドレス 192.168.1.8(分電盤) のポート 3610 から 0x108100010ef0010e01017202800130d607020287010f8701 の 24 byteを受信しました。
// 				
// 				//0e0101 72 02 80 01を探す、次が動作状態
// 				$searchStr = '0e010172028001';
// 				$pos1 = strpos($hexStr , $searchStr);
// 				$stsStr = '';
// 				$instanceStr = '';
// 				
// 				if ($pos1 !== FALSE)  //有り
// 				{
// 					$stsStr = substr($hexStr, $pos1+strlen($searchStr), 2);  // 動作状態プロパティ '30'/'31'
// 					if (substr($hexStr, $pos1+strlen($searchStr)+2, 2) == 'd6')  // インスタンスリストプロパティ
// 					{
// 						$instanceStr = substr($hexStr, strlen($hexStr)-6, 6);  //   ex.'013001' (家庭用エアコン)、'0f8701'(多機能分電盤)、'013501'(加湿空気清浄機)
// 					}
// 					$ipArray[] = $fromIP;
// 					$instanceArray[] = $instanceStr;
// 					$stsArray[] = $stsStr;
// 				}
// 			}
// 		} while( !is_null($buf) );
// 		
// 		$cnt = count($instanceArray);
// 		$logStr = "cnt=".$cnt;
// 		//echo $logStr."<br />";
// 		//writeLog2($logStr);
// 		for ($i=0; $i<$cnt; ++$i)
// 		{
// 			$logStr = "ipArray=".$ipArray[$i];
// 			//echo $logStr."<br />";
// 			//writeLog2($logStr);
// 			$logStr = "instanceArray=".$instanceArray[$i];
// 			//echo $logStr."<br />";
// 			//writeLog2($logStr);
// 			$logStr = "stsArray=".$stsArray[$i];
// 			//echo $logStr."<br />";
// 			//writeLog2($logStr);
// 		}
// 	}
// 	else  // 送信NG
// 	{
// 		$errorCode = socket_last_error();
// 		$errorMsg = socket_strerror($errorCode);
// 		$logStr = "EcoNet Lite 送信時 socket_sendto5 に失敗しました。[".$errorCode."] ".$errorMsg;
// 		//echo $logStr."<br />";
// 		writeLog2($logStr);
// 	}
// }


/* 自分(サーバー)のローカルIPアドレス取得 */
function getSelfIPaddr()
{
	$logStr = "getSelfIPaddr start";
	writeLog2($logStr);

	$ipAddress = "0.0.0.0";
	$ifcStr = shell_exec( '/sbin/ifconfig' );  // ターミナルのコマンドを実行

	$ifcArray = explode(" ", $ifcStr);  // 戻り値を配列に
	for ($i=0; $i<count($ifcArray); $i++)
	{
		if (strpos($ifcArray[$i], "192.168.") !==FALSE )  // ==だと0(先頭)が返ってくるとFALSEと判断してなしになる
		{
			$ipAddress = $ifcArray[$i];
			$ifcArray2 = explode(":", $ipAddress);  // 戻り値を配列に
			$ipAddress2 = $ifcArray2[1];
			
			$logStr = "getSelfIPaddr end";
			writeLog2($logStr);
			
			return $ipAddress2;
		}
	}
	
	if ($ipAddress == "0.0.0.0")
	{
		$logStr = "サーバーのローカルIPアドレスの取得に失敗しました。";
		//echo $logStr."<br />";
		writeLog2($logStr);
	}

	$logStr = "getSelfIPaddr end";
	writeLog2($logStr);

	return $ipAddress;
}


// EcoEye(多機能分電盤)IPアドレス
function getEcoEyeIPaddr($selfAddr)
{
	$logStr = "getEcoEyeIPaddr start";
	writeLog2($logStr);

	$targetAddr = "0.0.0.0";  // 分電盤のIPアドレス<--これを求める!!!
	$addrArray = explode(".", $selfAddr);  // 戻り値を配列に
	$addr333 = $addrArray[0].".".$addrArray[1].".".$addrArray[2].".";  // ex.'192.168.1.'
	
	// pingコマンドのループ
	for ($i=1; $i<=255; ++$i)
	{
		$swHit = FALSE;
		$address = $addr333.strval($i);
		$retStr1 = shell_exec('ping -c 1 -t 1 '.$address);  // 1回送ってタイムアウトは1秒

		// 受信データサーチ
		$pos1 = strpos($retStr1, '1 received');  // debian
		if ($pos1 !== FALSE)  // 応答有り(ping OK)
		{
			$retStr2 = shell_exec('ip neigh show');  // Mac NG
			// その時のIPアドレスの値を保持
			$retStr2 = str_replace(chr(0x0a), chr(0x20), $retStr2);  // 0x0aを0x20に置き換える
			$retArray = explode(" ", $retStr2);
			for ($j=0; $j<count($retArray); $j++)
			{
				$pos4 = strpos($retArray[$j], '00:11:03:');
				$pos5 = strpos($retArray[$j], '0:11:3:');
				if ($pos4 !== FALSE || $pos5 !== FALSE)  // 有り)
				{
					$targetAddr = $retArray[$j-4];  // 四つ前にIPアドレス有り  // ipの場合
					$swHit = TRUE;
					break;
				}
			}  // for ($j
		}  // if ($pos1

		if ($swHit == TRUE)
			break;
	}  // for
	
	if ($targetAddr == "0.0.0.0")
	{
		$logStr = "EcoEyeのローカルIPアドレスの取得に失敗しました。";
		//echo $logStr . "<br />";
		writeLog2($logStr);
	}
		
	$logStr = "getEcoEyeIPaddr end";
	writeLog2($logStr);

	return $targetAddr;
}


// ----------------------------------------------
// テーブルの一覧表示の関数の定義
// ----------------------------------------------
function show_tables($db_name, $db)
{
	// 指定されたデータベース内のテーブルリストの取得
	$rs = mysql_list_tables($db_name, $db);
	// 結果セット内のレコード数の取得
	$num_rows = mysql_num_rows($rs);
	echo "<table border=1 cellpadding=0 cellspacing=0>";
	echo "<tr>";
	echo "<td align=center>Tables in {$db_name}</td>";
	echo "</tr>";
	
	if ($num_rows > 0)  // テーブルがある場合
	{
		// 結果セット内のレコードを順次参照
		for($i = 0; $i < $num_rows; $i++)
		{
			// テーブル名の取得
			$table_name = mysql_table_name($rs,$i);
			// テーブル名の表示
			echo "<tr>";
			echo "<td>{$table_name}</td>";
			echo "</tr>";
		}
	}
	else  // テーブルが無い場合
	{
		echo "<tr>";
		echo "<td>テーブルはありません</td>";
		echo "</tr>";
	}
	echo "</table>";
	// 結果セットの解放
	mysql_free_result($rs);
}

// ----------------------------------------------
// テーブルの存在チェック関数の定義
// ----------------------------------------------
function table_exists($db_name, $tbl_name, $db)
{
	// テーブルリストの取得
	$rs = mysql_list_tables($db_name, $db);
	// 結果セットの1レコード分を添え字配列として取得する
	while ($arr_row = mysql_fetch_row($rs))
	{
		// 添え字配列内にテーブル名が存在する場合
		if(in_array($tbl_name, $arr_row))
		{
			return true;
		}
	}
	return false;
}

// ----------------------------------------------
// フィールド属性の一覧表示の関数の定義
// ----------------------------------------------
function show_fields($db_name,$tbl_name,$db)
{
	// 指定されたデータベース、テーブル内のフィールドリストの取得
	$rs = mysql_list_fields($db_name,$tbl_name,$db);
	// 結果セット内のレコード数の取得
	$num_rows = mysql_num_fields($rs);
	print "テーブル「{$tbl_name}」内のフィールド属性一覧\n";
	print "<table border=1 cellpadding=0 cellspacing=0>\n";
	print "<tr>\n";
	print "<td align=center>フィールド名</td>\n";
	print "<td align=center>データ型(長さ)</td>\n";
	print "<td align=center>フラグ</td>\n";
	print "</tr>\n";

	// フィールドがある場合
	if($num_rows > 0)
	{
		// 結果セット内のレコードを順次参照
		for($i = 0; $i < $num_rows; $i++)
		{
			// フィールド名の取得
			$field_name = mysql_field_name($rs,$i);
			// データ型の取得
			$field_type = mysql_field_type($rs,$i);
			// フィールドの長さの取得
			$field_len = mysql_field_len($rs,$i);
			// フィールドのフラグの取得
			$field_flags = mysql_field_flags($rs,$i);

			// フラグがヌルなら半角スペースとする
			if($field_flags == '')
			{
				$field_flags='&nbsp;';
			}

			// フィールド属性の表示
			print "<tr>\n";
			print "<td>{$field_name}</td>\n";
			print "<td>{$field_type}({$field_len})</td>\n";
			print "<td>{$field_flags}</td>\n";
			print "</tr>\n";
		}
	}
	else  // フィールドが無い場合
	{
		print "<tr>\n";
		print "<td>フィールドはありません</td>\n";
		print "</tr>\n";
	}
	print "</table>\n";
	
	// 結果セットの解放
	mysql_free_result($rs);
}


function writeLog2($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}

?>
