<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<?php
	// http://viewe-morioka.ddo.jp/check_EcoEye_status.php
	// http://192.168.1.100/check_EcoEye_status.php
	// 現場などで、EcoEyeとの通信可否をチェックするツール
	// 2017.8.24 空気清浄器クラス、蓄電池クラスを追加
	
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

    $ipAddr = getSelfIPaddr();  // 自分のIPアドレス
	echo "Message : サーバーのipアドレス ".$ipAddr."<br />";
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
			checkForEcoNetLite($sock_send, $sock_receive);
			
		}  // if ($bindOK == true)  // バインドOKか
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

// EcoNet Lite規格で分電盤のステータスを聞いてlogを出力する
function checkForEcoNetLite($sock_send, $sock_receive)
{
	$senCommand = hex2bin("108100010e01010ef00162028000d600");  // 動作状態&自ノードインスタンスリスト S(0x62 Get プロパティ値読み出し要求 0e0101:ノードプロファイルクラスの自分 0ef001:ノードプロファイルクラスの相手?)
	$toIP = "224.0.23.0";  // マルチキャスト用IPアドレス<--全機器に送信する場合(マルチキャスト) <--ルーターをマルチキャスト対応にする必要がある
	$port = 3610;  // 分電盤ポート番号

	$len = strlen($senCommand);
	$len2 = socket_sendto($sock_send, $senCommand, $len, 0, $toIP, $port);
	if ($len2)  // 送信OKか
	{
		$logStr = "Message : サーバーが ipアドレス $toIP のポート $port へ 0x".bin2hex($senCommand). " の ".$len2." byteを送信しました。";
		echo $logStr."<br />";
		
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
				$logStr = "Message : サーバーが ipアドレス $fromIP のポート $port から 0x".$hexStr. " の ".strlen($buf)." byteを受信しました。";
				echo $logStr."<br />";
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
		$logStr = "EcoNet Lite 機器数 : ".$cnt;
		echo $logStr . "<br />";
		for ($i=0; $i<$cnt; ++$i)  // 機器数の繰り返し
		{
			$logStr = "＜機器".strval($i+1) . "＞";
			echo $logStr . "<br />";
			
			$logStr = "　ipアドレス : ".$ipArray[$i];
			echo $logStr . "<br />";
			
			$logStr = "";
			$instArr = explode(',', $instanceArray[$i]);  // 各機器のインスタンス(csv形式)を配列へ
			$cnt2 = count($instArr);  // 各機器のインスタンス数
			for ($k=0; $k<$cnt2; ++$k)  // インスタンス数の繰り返し
			{
				$logStr .= "　インスタンス ". strval($k+1) . " : " . $instArr[$k];
				if (strcmp($instArr[$k], "0f8701") == 0)  // 一致
					$logStr .= " (ユーザ定義クラス)";
				else if (strcmp($instArr[$k], "013501") == 0)  // 一致 2017.8.24
					$logStr .= " (空気清浄器クラス)";
				else if (strcmp($instArr[$k], "028701") == 0)  // 一致
					$logStr .= " (分電盤メータリングクラス)";
				else if (strcmp($instArr[$k], "027901") == 0)  // 一致
					$logStr .= " (太陽光クラス・インスタンス1)";
				else if (strcmp($instArr[$k], "027902") == 0)  // 一致
					$logStr .= " (太陽光クラス・インスタンス2)";
				else if (strcmp($instArr[$k], "027c01") == 0)  // 一致
					$logStr .= " (燃料電池クラス)";
				else if (strcmp($instArr[$k], "027d01") == 0)  // 一致 2017.8.24
					$logStr .= " (蓄電池クラス)";
				else if (strcmp($instArr[$k], "028101") == 0)  // 一致
					$logStr .= " (水流量メータクラス・インスタンス1)";
				else if (strcmp($instArr[$k], "028102") == 0)  // 一致
					$logStr .= " (水流量メータクラス・インスタンス2)";
				else if (strcmp($instArr[$k], "028201") == 0)  // 一致
					$logStr .= " (ガスメータクラス・インスタンス1)";
				else if (strcmp($instArr[$k], "028202") == 0)  // 一致
					$logStr .= " (ガスメータクラス・インスタンス2)";
				else if (strcmp($instArr[$k], "028202") == 0)  // 一致
					$logStr .= " (ガスメータクラス・インスタンス2)";
				$logStr .= "<br />";	
			}
			echo $logStr;
			
			$logStr = "　ステータス : ".$stsArray[$i];
			if (strcmp($stsArray[$i], "30") == 0)  // 一致
					$logStr .= " (正常)";
			else if (strcmp($stsArray[$i], "31") == 0)  // 一致
					$logStr .= " (異常)";
			echo $logStr . "<br />";
		}
	}
	else  // 送信NG
	{
		$errorCode = socket_last_error();
		$errorMsg = socket_strerror($errorCode);
		$logStr = "Error : EcoNet Lite 送信時 socket_sendto5 に失敗しました。[".$errorCode."] ".$errorMsg;
		echo $logStr . "<br />";
	}
}

/* 自分(サーバー)のローカルIPアドレス取得 */
function getSelfIPaddr()
{
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
						
			return $ipAddress2;
		}
	}
	
	if ($ipAddress == "0.0.0.0")
	{
		$logStr = "Error : サーバーのローカルIPアドレスの取得に失敗しました。";
		echo $logStr . "<br />";
	}
	
	return $ipAddress;
}


// Message : サーバーのipアドレス 192.168.1.100
// Message : サーバーが ipアドレス 224.0.23.0 のポート 3610 へ 0x108100010e01010ef00162028000d600 の 16 byteを送信しました。
// Message : サーバーが ipアドレス 192.168.1.4 のポート 3610 から 0x108100010ef0010e01017202800130d607020287010f8701 の 24 byteを受信しました。
// EcoNet Lite : インスタンス数 1
// No.1: ipアドレス 192.168.1.4
// No.1: インスタンス 0f8701 (EcoEye)
// No.1: ステータス 30

// 108100010e01010ef00162028000d600
// 10  ECHONET Header
// 81  規定電文形式
// 0001 連番
// 0e0101 NUC
// 0ef001 ノードプロファイルクラス
// 62 Get
// 02 二つ
// 80 動作状態
// 00
// d6 自ノードインスタンスリストS
// 00

// 108100010ef0010e01017202800130d607020287010f8701
// 10  ECHONET Header
// 81  規定電文形式
// 0001 連番
// 0ef001 ノードプロファイルクラス
// 0e0101 NUC
// 72 Get_Res プロパティ値読み出し応答 (ESV=0x62の応答)
// 02 二つ
// 80 動作状態
// 01 1byte
// 30 OKプロパティ
// d6 自ノードインスタンスリストS
// 07 7byte
// 02 インスタンス2つ
// 028701 分電盤メータリングクラス
// f8701 ユーザ定義クラス

// 検証機をLANに接続すると
// Message : サーバーのipアドレス 192.168.1.100
// Message : サーバーが ipアドレス 224.0.23.0 のポート 3610 へ 0x108100010e01010ef00162028000d600 の 16 byteを送信しました。
// Message : サーバーが ipアドレス 192.168.1.4 のポート 3610 から 0x108100010ef0010e01017202800130d607020287010f8701 の 24 byteを受信しました。
// Message : サーバーが ipアドレス 192.168.1.17 のポート 3610 から 0x108100010ef0010e01017202800130d61c09027901027902027c010281010281020282010282020287010f8701 の 45 byteを受信しました。
// EcoNet Lite 機器数 : 2
// ＜機器1＞
// 　ipアドレス : 192.168.1.4
// 　インスタンス 1 : 028701 (分電盤メータリングクラス)
// 　インスタンス 2 : 0f8701 (ユーザ定義クラス)
// 　ステータス : 30 (正常)
// ＜機器2＞
// 　ipアドレス : 192.168.1.17
// 　インスタンス 1 : 027901 (太陽光クラス・インスタンス1)
// 　インスタンス 2 : 027902 (太陽光クラス・インスタンス2)
// 　インスタンス 3 : 027c01 (燃料電池クラス)
// 　インスタンス 4 : 028101 (水流量メータクラス・インスタンス1)
// 　インスタンス 5 : 028102 (水流量メータクラス・インスタンス2)
// 　インスタンス 6 : 028201 (ガスメータクラス・インスタンス1)
// 　インスタンス 7 : 028202 (ガスメータクラス・インスタンス2)
// 　インスタンス 8 : 028701 (分電盤メータリングクラス)
// 　インスタンス 9 : 0f8701 (ユーザ定義クラス)
// 　ステータス : 30 (正常)

// 027901 太陽光1
// 027902 太陽光2
// 027c01 燃料電池
// 028101 水流量メータクラス1
// 028102 水流量メータクラス2
// 028201 ガスメータクラス1
// 028202 ガスメータクラス1
// 028701 分電盤メータリングクラス
// 0f8701 ユーザ定義クラス


?>

</html>