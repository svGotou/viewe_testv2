<?php
// getCurrentValue.php
// 分電盤から最新の値取得し、textファイルに落とす<--落とさない。returnしている。
	date_default_timezone_set('Asia/Tokyo');
	
	// http://php.net/manual/ja/function.hex2bin.php
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


	// 分電盤メータリングクラスのチャンネル情報取得(Web版ViewEがセットしている) 2017/1/7
	$chanNum = 0;
	$chanNum2 = 0;
	$filepath = "additional_circuit.txt";
	if (file_exists($filepath))  // 在る
	{
		$dataStr = file_get_contents($filepath);
		//echo "dataStr=".$dataStr."<br />";
		if (strlen($dataStr) > 0)
		{
			$chanArry = explode(',', $dataStr);
			$chanNum = count($chanArry);  // 7のはず ex."1,2,0,0,0,0,0"
		}
	}
	for ($i=0; $i<$chanNum; $i++)
	{
		if (intval($chanArry[$i]) > 0)
			$chanNum2 += 1;  // 有効なチャンネル数
	}
	//echo "chanNum2=".strval($chanNum2)."<br />";


	// ファイルの準備
	$logFilePath = 'ecoEye_log2.txt';  // log
	//$fp = fopen($logFilePath, 'ab');
	$writeStr = "";
	$maxChan = getMaxChannel();
	
    $headData = hex2bin("108100010f01010f87016201");  // コマンドのヘッダー
    $cmdArray = array();
	$cmdArray[0] = $headData.hex2bin("c000");  // 主幹正方向 : 買電量
	$cmdArray[1] = $headData.hex2bin("d000");
	$cmdArray[2] = $headData.hex2bin("d100");
	$cmdArray[3] = $headData.hex2bin("d200");
	$cmdArray[4] = $headData.hex2bin("d300");
	$cmdArray[5] = $headData.hex2bin("d400");
	$cmdArray[6] = $headData.hex2bin("d500");
	$cmdArray[7] = $headData.hex2bin("d600");
	$cmdArray[8] = $headData.hex2bin("d700");
	$cmdArray[9] = $headData.hex2bin("d800");
	$cmdArray[10] = $headData.hex2bin("d900");
	$cmdArray[11] = $headData.hex2bin("da00");
	$cmdArray[12] = $headData.hex2bin("db00");
	$cmdArray[13] = $headData.hex2bin("dc00");
	$cmdArray[14] = $headData.hex2bin("dd00");
	$cmdArray[15] = $headData.hex2bin("de00");
	$cmdArray[16] = $headData.hex2bin("df00");
	$cmdArray[17] = $headData.hex2bin("e000");
	$cmdArray[18] = $headData.hex2bin("e100");
	$cmdArray[19] = $headData.hex2bin("e200");
	$cmdArray[20] = $headData.hex2bin("e300");
	$cmdArray[21] = $headData.hex2bin("e400");
	$cmdArray[22] = $headData.hex2bin("e500");
	$cmdArray[23] = $headData.hex2bin("e600");
	$cmdArray[24] = $headData.hex2bin("e700");
	$cmdArray[25] = $headData.hex2bin("e800");
	$cmdArray[26] = $headData.hex2bin("e900");
	$cmdArray[27] = $headData.hex2bin("ea00");
	$cmdArray[28] = $headData.hex2bin("eb00");
	$cmdArray[29] = $headData.hex2bin("ec00");
	$cmdArray[30] = $headData.hex2bin("ed00");
	$cmdArray[31] = $headData.hex2bin("ee00");
	$cmdArray[32] = $headData.hex2bin("ef00");
	$cmdArray[33] = $headData.hex2bin("f000");
	$cmdArray[34] = $headData.hex2bin("f100");
	$cmdArray[35] = $headData.hex2bin("f200");
	$cmdArray[36] = $headData.hex2bin("f300");
	$cmdArray[37] = $headData.hex2bin("f400");
	$cmdArray[38] = $headData.hex2bin("f500");
	$cmdArray[39] = $headData.hex2bin("f600");
	$cmdArray[40] = $headData.hex2bin("f700");  // コマンドの値はここまで
	$cmdArray[41] = $headData.hex2bin("c100");  // 主幹逆方向 : 売電量
	if ($chanNum2 == 0)  // 分電盤メータリングクラスでは無い 2017/1/7
	{
		$cmdArray[42] = $headData.hex2bin("c900");  // 追加回路1(正方向と逆方向) : エコキュート
		$cmdArray[43] = $headData.hex2bin("ca00");  // 追加回路2(正方向と逆方向) : :太陽光
	}
	
    $ipAddr = getSelfIPaddr();  // 自分のIPアドレス
	$toIP = getEcoEyeIPaddr($ipAddr);  // EcoEye(多機能分電盤)IPアドレス
    $port = 3610;  // 分電盤ポート番号
    
	 if ($toIP != "0.0.0.0")  // 分電盤ポート検出できず!
    {
	    // 送受信ソケット生成
		$sock_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 
		$sock_receive = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($sock_send && $sock_receive)  // ソケット生成OK
		{
				for ($i=0; $i<count($cmdArray); $i++)  // 全コマンド送信
		    	{
		    		if ($i <= $maxChan || $i > 40)
		    		{
		    			//echo "i=".$i."<br />";
			    		// 送信処理
					socket_set_option($sock_send, SOL_SOCKET, SO_REUSEADDR, 1);  // host downになることがある
				    	$len = strlen($cmdArray[$i]);
				    	$len2 = socket_sendto($sock_send, $cmdArray[$i], $len, 0, $toIP, $port);
					if ($len2)  // 送信OKか
			  		{
			  			$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdArray[$i]). " の ".$len2." byteを送信しました。受信処理に入ります。";
			  			//echo $logStr."<br />";
			  			//writeLog($fp, $logStr);
			  			//writeLog2($logStr);
			  			
				  		// 受信処理
						//socket_set_option($sock_receive, SOL_SOCKET, SO_REUSEADDR, 1);
				  		if ($i==0)  // 毎回やるとおかしくなるのでループの最初だけ
							//$bindOK = socket_bind($sock_receive, '192.168.1.100', 3610);  // 受信側のポートは3610にする。相手が3610を指定して来るので。iMac
							$bindOK = socket_bind($sock_receive, $ipAddr, $port);
							
						if ($bindOK == true)  // バインドOKか
						{
							$fromIP = '';
						    $port = 0;
							socket_set_option($sock_receive, SOL_SOCKET, SO_REUSEADDR, 1);
							socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'3', 'usec'=>'0'));  // タイムアウト3秒
						    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);  // これのタイムアウト処理をどうするの？ @@@@@@@@@@
						    $hexStr = bin2hex($buf);
						    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
							//echo $logStr."<br />";
							//writeLog($fp, $logStr);
			  				//writeLog2($logStr);
	
							// 主幹   0x108100010f87010f01017201 c0 04 00000042 [正方向]
							// ch#1  0x108100010f87010f01017201 d0 08 000000250000fefe
							// ch#2  0x108100010f87010f01017201 d1 08 0000000bfefe0000
							// ch#3  0x108100010f87010f01017201 d2 08 000000060000fefe
							// ch#4   0x108100010f87010f01017201 d3 08 00000008fefe0000
							// 主幹   0x108100010f87010f01017201 c1 04 00003b08 [逆方向]
							// 追加回路1 0x108100010f87010f01017201 c9 10 000011ae 00000000 00000003 0000 0000 [16byte]
							// 追加回路2 0x108100010f87010f01017201 ca 10 0000001a 000048c1 fffff612 ff87 0000 [16byte]
							
							if (substr($hexStr, 24 , 2) == "c0")  // 主幹 [正方向]
							{
								$cnl = 0;
								//echo "cnl=".$cnl."<br />";
								//$kwh = hexdec(substr($hexStr, 28 , 8));  // 消費電力[買電量]
							}
							else if (substr($hexStr, 24 , 2) == "c1")  // 主幹 [逆方向]
							{
								$cnl = 100;
								//echo "cnl=".$cnl."<br />";
								//$kwh = hexdec(substr($hexStr, 28 , 8));  // 売電量
							}
							else if (substr($hexStr, 24 , 2) == "c9")  // 追加回路1[エコキュート]
							{
								$cnl = 101;
								//echo "cnl=".$cnl."<br />";
								//$kwh = hexdec(substr($hexStr, 28 , 8));  // 消費電力
							}
							else if (substr($hexStr, 24 , 2) == "ca")  // 追加回路2 [太陽光]
							{
								$cnl = 102;
								//echo "cnl=".$cnl."<br />";
								//$kwh = hexdec(substr($hexStr, 36 , 8));  // 発電量(逆方向)
							}
							else  // ch#1〜#40
							{
								$cnl = hexdec(substr($hexStr, 24 , 2)) - hexdec("d0") + 1;
								//echo "cnl=".$cnl."<br />";
								//$kwh = hexdec(substr($hexStr, 28 , 8));  // 消費電力
							}
							$kwh2 = 0;  // 太陽光発電正方向(太陽光機器の発電量)
							if ($cnl != 102)  // 太陽光でない
							{
								$kwh = hexdec(substr($hexStr, 28 , 8));  // 消費電力
							}
							else  // 太陽光だ
							{
								$kwh = hexdec(substr($hexStr, 36 , 8));  // 発電量(逆方向)
								
								$cnl2 = 103;
								$kwh2 = hexdec(substr($hexStr, 28 , 8));  // 発電量(正方向 : 太陽光機器の発電量)
								//echo "kwh2=".$kwh2."<br />";
							}
							//echo "kwh=".$kwh."<br />";
							
							if ($kwh != 4278124286)  // 未使用でない
							{
								if (strlen($writeStr) > 0)
									$writeStr = $writeStr . ",";
								$writeStr = $writeStr . $cnl . "," . $kwh;
							}
							//usleep(200000);  //0.2秒待つ
							
							if ($cnl == 102)  // 太陽光だった
							{
								if ($kwh2 != 4278124286)  // 未使用でない
								{
									if (strlen($writeStr) > 0)
										$writeStr = $writeStr . ",";
									$writeStr = $writeStr . $cnl2 . "," . $kwh2;
									//usleep(200000);  //0.2秒待つ
								}
							}
						}  // if ($bindOK == true)  // バインドOKか
						else
						{
							$errorCode = socket_last_error();
							$errorMsg = socket_strerror($errorCode);
							//$errStr = "受信時 socket_bind に失敗しましまた。[".$errorCode."] ".$errorMsg;   // 分電盤の電源が入ってないとここでエラーだ
							echo $errStr."<br />";
							writeLog2($errStr);
							//usleep(200000);  //0.2秒待つ
						}
					}
					else
					{
						$errorCode = socket_last_error();
						$errorMsg = socket_strerror($errorCode);
						//$errStr = "送信時 socket_sendto に失敗しましまた。[".$errorCode."] ".$errorMsg;
						//echo $errStr."<br />";
						writeLog2($errStr);
						//sleep(1);
						usleep(200000);  //0.2秒待つ
					}  // if ($len2)  // 送信OKか
				}
			}  // for ($i=0; $i<count($cmdArray); $i++)
		
			if ($chanNum2 > 0)  // 分電盤メータリングクラスだ 2017/1/7
			{
				$headData1 = hex2bin("108100010f01010287016101");  // 分電盤メータリングクラスへのコマンドヘッダー(応答必要)
				$cmdStr1 = $headData1.hex2bin("b902010".strval($chanNum2));  // 積算電力量計測チャネル範囲指定(双方向) [1chanelから2つ] 有効なチャンネル数を付加
				$headData2 = hex2bin("108100010f01010287016201");  // 分電盤メータリングクラスへのコマンドヘッダー
				$cmdStr2 = $headData2.hex2bin("ba00");  // 積算電力量計測値リスト(双方向）
				
				$len = strlen($cmdStr1);
				$len2 = socket_sendto($sock_send, $cmdStr1, $len, 0, $toIP, $port);  // 積算電力量計測チャネル範囲指定コマンド
				if ($len2)  // 送信OKか
					{
					$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdStr1). " の ".$len2." byteを送信しました。受信処理に入ります。";
					writeLog2($logStr);
					$len3 = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
					 if ($len3 > 0) // 受信データあり
					 {
						$hexStr = bin2hex($buf);
						$logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
						writeLog2($logStr);
						
						usleep(200000);  //0.2秒待つ
						$len4 = strlen($cmdStr2);
						$len5 = socket_sendto($sock_send, $cmdStr2, $len4, 0, $toIP, $port);  // 積算電力量計測値リスト取得コマンド
						if ($len5)  // 送信OKか
	  					{
								$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdStr2). " の ".$len5." byteを送信しました。受信処理に入ります。";
							writeLog2($logStr);
							
							$fromIP = '';
							$port = 0;
							$len6 = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);
							if ($len6 > 0)  // 受信データあり
							{
					    		$hexStr = bin2hex($buf);
								$logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
								writeLog2($logStr);
								
								$kwh1 = 0;  // 太陽光発電の消費量
								$kwh2 = 0;  // 太陽光発電の発電量
								$kwh3 = 0;  // エコキュートの消費量
								$hit_sun = false;  // 太陽光発電の有無
								$hit_eco = false;  // エコキュートの有無
								for ($i=0; $i<$chanNum2; $i++)
								{
									$kwhStr = substr($hexStr, 32+16*$i , 16);
									//echo "kwhStr=$kwhStr<br />";
									if ($chanArry[$i] == 1)  // 太陽光発電
									{
										$hit_sun = true;
										$kwh1 += hexdec(substr($kwhStr, 0 , 8));  // 消費量(正方向 : 太陽光発電の消費量)
										$kwh2 += hexdec(substr($kwhStr, 8 , 8));  // 発電量(逆方向 : 太陽光発電の発電量)
									}
									else if ($chanArry[$i] == 2)  // エコキュート
									{
										$hit_eco = true;
										$kwh3 += hexdec(substr($kwhStr, 0 , 8));  // 消費量(正方向 : エコキュートの消費量)
									}
								}  // for
								
								//echo "エコキュートの消費量=$kwh3<br />";
								//echo "太陽光発電の消費量=$kwh1<br />";
								//echo "太陽光発電の発電量=$kwh2<br />";
								
								if ($hit_eco)  // エコキュートの回路があった
								{
									$writeStr = $writeStr . ",101," . $kwh3;  // 消費量
								}
								if ($hit_sun)  // 太陽光発電の回路があった
								{
									$writeStr = $writeStr . ",102," . $kwh2;  // 発電量
									$writeStr = $writeStr . ",103," . $kwh1;  // 消費量
								}
							}  // if ($len6 > 0)  // 受信データあり
						}  // if ($len5)  // 送信OKか
						else
						{
							$errorCode = socket_last_error();
							$errorMsg = socket_strerror($errorCode);
				    		//echo "送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg."<br />";
				    		writeLog2("送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg);
						}
					 }  //  if ($len3 > 0)
				}  // if ($len2)  // 送信OKか
				else
				{
					$errorCode = socket_last_error();
					$errorMsg = socket_strerror($errorCode);
		    		//echo "送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg."<br />";
				    writeLog2("送信時 socket_sendto に失敗しました。[".$errorCode."] ".$errorMsg);
				}
			}

			socket_close($sock_send);
			socket_close($sock_receive);
		}  // if ($sock_send && $sock_receive)  // ソケット生成OK
		else
		{
			$errorCode = socket_last_error();
			$errorMsg = socket_strerror($errorCode);
			//$errStr = "受信時 socket_create に失敗しましまた。[".$errorCode."] ".$errorMsg;
			//echo $errStr."<br />";
			writeLog2($errStr);
		}
	}
	else  // 分電盤ポート検出できず
	{
		//echo "分電盤のIPアドレスを取得出来ませんでした。<br />";
	}
	
	//echo "writeStr=".$writeStr."<br />";
	return $writeStr;  // 0,275382,100,77962,101,76390,102,47689,103,264
							//  0,275382,100,78856,101,76418,102,48265,103,264
							
// この環境の最大チャンネルを知る
function getMaxChannel()
{
	$maxChan = 0;
	$typeChan= array(16, 20, 24, 28, 32, 36, 40);
	
	//$filepath = "channel_name48.txt";
	$filepath = "channel_name48A.txt";
	if (file_exists($filepath))  // 在る
	{
		$dataStr = file_get_contents($filepath);
		if (strlen($dataStr) > 0)
		{
			$chanArry = explode(',', $dataStr);
			$maxChan = $typeChan[intval($chanArry[0])];
		}
	}
	
	//echo "maxChan=".strval($maxChan)."<br />";
	return $maxChan;
}

/* 自分(サーバー)のローカルIPアドレス取得 */
function getSelfIPaddr()
{
	$ipAddress = "0.0.0.0";
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
		//echo "ifcArray=".$ifcArray[$i]."<br>";
		if (strpos($ifcArray[$i], "192.168.") !==FALSE )  // ==だと0(先頭)が返ってくるとFALSEと判断してなしになる
		{
			$ipAddress = $ifcArray[$i];
			$ifcArray2 = explode(":", $ipAddress);  // 戻り値を配列に
			//echo "ifcArray2[0]=".$ifcArray2[0]."<br>";
			//echo "ifcArray2[1]=".$ifcArray2[1]."<br>";
			$ipAddress2 = $ifcArray2[1];
			//echo "ipAddress=".$ipAddress2."<br>";
			//exit;  // これで出るとダメみたい
			return $ipAddress2;
		}
	}
	
	if ($ipAddress == "0.0.0.0")
		writeLog2("サーバーのローカルIPアドレスの取得に失敗しました。");
	return $ipAddress;
	
	//return '192.168.3.14';
}


// EcoEye(多機能分電盤)IPアドレス
function getEcoEyeIPaddr($selfAddr)
{
	// 先ず自分のIPアドレスを知る
	// 192.168.1.xxx
	$targetAddr = "0.0.0.0";  // 分電盤のIPアドレス<--これを求める!!!
	//$selfAddr = getSelfIPaddr();  // サーバーのローカルIPアドレスを取得
	// aaa.bbb.ccc.dddのaaa.bbb.cccを得る
	$addrArray = explode(".", $selfAddr);  // 戻り値を配列に
	$addr333 = $addrArray[0].".".$addrArray[1].".".$addrArray[2].".";  // ex.'192.168.1.'
	//echo "addr333=".$addr333."<br />";
	
	// pingコマンドのループ(dddを変える)
	for ($i=1; $i<=255; ++$i)
	{
		$swHit = FALSE;
		//$address = '192.168.1.'.strval($i);
		$address = $addr333.strval($i);
		//$retStr = shell_exec('ping -c 1 -t 1 192.168.1.'.strval($i));  // 1回送ってタイムアウトは1秒
		$retStr1 = shell_exec('ping -c 1 -t 1 '.$address);  // 1回送ってタイムアウトは1秒
		//PING 192.168.1.7 (192.168.1.7) 56(84) bytes of data.
		//64 bytes from 192.168.1.7: icmp_req=1 ttl=80 time=0.250 ms
		//--- 192.168.1.7 ping statistics ---
		//1 packets transmitted, 1 received, 0% packet loss, time 0ms
		//rtt min/avg/max/mdev = 0.250/0.250/0.250/0.000 ms

		//echo 'ping -c 1 -t 1 '.$address."<br />";
		//echo "retStr1=".$retStr1."<br />";
		// 受信データサーチ
		//$pos1 = strpos($retStr1, '1 packets received');  // Mac
		$pos1 = strpos($retStr1, '1 received');  // debian
		//echo "pos1=".$pos1."<br />";
		if ($pos1 !== FALSE)  // 応答有り(ping OK)
		{
			//echo $pos;
			//echo '1 received 有り'.'<br />';
			//arpコマンドを送信
			//$retStr2 = shell_exec('arp -a -n');  // debian NG
			//soundvision@debian:~$ sudo arp -a -n
			//? (192.168.1.1) at 00:25:36:8c:1a:c6 [ether] on eth0
			//? (192.168.1.5) at e4:ce:8f:5e:81:8b [ether] on eth0
			//? (192.168.1.7) at 00:11:03:06:00:28 [ether] on eth0
			//echo 'arp -a -n<br />';
			
			$retStr2 = shell_exec('ip neigh show');  // Mac NG
			//fe80::225:36ff:fe8c:1ac6 dev eth0 lladdr 00:25:36:8c:1a:c6 router STALE 192.168.1.1 dev eth0 lladdr 00:25:36:8c:1a:c6 STALE 192.168.1.5 dev eth0 lladdr e4:ce:8f:5e:81:8b DELAY 192.168.1.7 dev eth0 lladdr 00:11:03:06:00:28 DELAY
			
			//echo "retStr2=".$retStr2."<br />";
			 // kawamura electric inc.のMACアドレスをサーチ
			//$pos2 = strpos($retStr2, ' 00:11:03:');
			//$pos3 = strpos($retStr2, ' 0:11:3:');
			//if ($pos2 !== FALSE || $pos3 !== FALSE)  // 有り
			//{
				//echo '00:11:03 有り'.'<br />';
			// その時のIPアドレスの値を保持
			$retStr2 = str_replace(chr(0x0a), chr(0x20), $retStr2);  // 0x0aを0x20に置き換える
			$retArray = explode(" ", $retStr2);
			for ($j=0; $j<count($retArray); $j++)
			{
				//echo "retArray=".$retArray[$j]."<br />";
				$pos4 = strpos($retArray[$j], '00:11:03:');
				$pos5 = strpos($retArray[$j], '0:11:3:');
				if ($pos4 !== FALSE || $pos5 !== FALSE)  // 有り)
				{
					//$targetAddr = $retArray[$j-2];  // 二つ前にIPアドレス有り  // arpの場合
					$targetAddr = $retArray[$j-4];  // 四つ前にIPアドレス有り  // ipの場合
					//$targetAddr = str_replace("(", "", $targetAddr);  // "("を取る  // arpの場合
					//$targetAddr = str_replace(")", "", $targetAddr);  // ")"を取る  // arpの場合
					//echo "targetAddr=".$targetAddr."<br />";
					$swHit = TRUE;
					break;
				}
			}  // for ($j
			//break;
		}  // if ($pos1

		if ($swHit == TRUE)
			break;
	}  // for
	
	if ($targetAddr == "0.0.0.0")
		writeLog2("EcoEyeのローカルIPアドレスの取得に失敗しました。");
		
	return $targetAddr;
}


function writeLog2($logStr)
{
	//echo "logStr=".$logStr."<br />";
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');
}
?>
