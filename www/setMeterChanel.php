<?php
// http://192.168.1.100/setMeterChanel.php
// ViewE 検証ユニットテスト用(分電盤メータリングクラスのチャンネル範囲をセットする)
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

	// コマンド
// 	$meterClass = true;
// 	if ($meterClass)
// 	{
		//$headData = hex2bin("108100010f01010287016001");  // 分電盤メータリングクラスへのコマンドヘッダー(応答不要)
		$headData = hex2bin("108100010f01010287016101");  // 分電盤メータリングクラスへのコマンドヘッダー(応答必要)
		$cmdStr = $headData.hex2bin("b9020102");  // 積算電力量計測チャネル範囲指定(双方向)
// 	}
// 	else
// 	{
// 		$headData = hex2bin("108100010f01010f87016201");  // ユーザ定義クラスへのコマンドヘッダー
// 		//$cmdStr = $headData.hex2bin("fc00");  // ：パルス計測回路1-1,2
// 		$cmdStr = $headData.hex2bin("fd00");  // ：パルス計測回路2-1,2
// 	}

	//$ipAddr = getSelfIPaddr();  // NUC 自分のIPアドレス(これもファイル保存)
	//echo "ipAddr=".$ipAddr."<br />";
	$ipAddr = "192.168.1.100";  // NUC 自分のIPアドレス(これもファイル保存)
	//$toIP = getEcoEyeIPaddr($ipAddr);  // EcoEye(多機能分電盤)IPアドレス
	//$toIP = "192.168.1.37";  // 分電盤検証ユニットIPアドレス  // 本番で変更 @@@@@@@@@@
	$toIP = "192.168.1.4";  // 分電盤検証ユニットIPアドレス  // 本番で変更 
	$port = 3610;  // 分電盤ポート番号

	$sock_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 
	$sock_receive = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock_send && $sock_receive)  // ソケット生成OK
	{
		echo "ソケット生成OK<br />";
		socket_set_option($sock_send, SOL_SOCKET, SO_REUSEADDR, 1);  // host downになることがある
    	$len = strlen($cmdStr);
    	$len2 = socket_sendto($sock_send, $cmdStr, $len, 0, $toIP, $port);
		if ($len2)  // 送信OKか
  		{
  			$logStr = $toIP. " のポート ".$port." へ 0x".bin2hex($cmdStr). " の ".$len2." byteを送信しました。受信処理に入ります。";
			echo $logStr."<br />";
			$bindOK = socket_bind($sock_receive, $ipAddr, $port);
			if ($bindOK == true)  // バインドOKか
			{
				$fromIP = '';
			    $port = 0;
				socket_set_option($sock_receive, SOL_SOCKET, SO_REUSEADDR, 1);
				socket_set_option( $sock_receive, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>'6', 'usec'=>'0'));  // タイムアウト6秒  2016.06.17
			    $len = socket_recvfrom($sock_receive, $buf, 512, 0, $fromIP, $port);  // これのタイムアウト処理をどうするの？ @@@@@@@@@@
			    if ($len > 0)
			    {
				    $hexStr = bin2hex($buf);
				    $logStr = "リモートアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
					echo $logStr."<br />";
				}  //  if ($len > 0)
			} // if ($bindOK == true)  // バインドOKか
			else
			{
				$errorCode = socket_last_error();
				$errorMsg = socket_strerror($errorCode);
				echo "受信時 socket_bind に失敗しましまた。[".$errorCode."] ".$errorMsg."<br />";   // 分電盤の電源が入ってないとここでエラーだ
			}
		}  // if ($len2)  // 送信OKか
		else
		{
			$errorCode = socket_last_error();
			$errorMsg = socket_strerror($errorCode);
    		echo "送信時 socket_sendto に失敗しましまた。[".$errorCode."] ".$errorMsg."<br />";
		}
	}  // if ($sock_send && $sock_receive)  // ソケット生成OK
	else
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		echo "受信時 socket_create に失敗しましまた。[".$errorCode."] ".$errorMsg."<br />";
	}
	
// 	ソケット生成OK
// 	192.168.1.4 のポート 3610 へ 0x108100010f01010287016201b9020102 の 16 byteを送信しました。受信処理に入ります。
// 	リモートアドレス 192.168.1.4 のポート 3610 から 0x108100010287010f01015201b900 の 14 byteを受信しました。
?>
