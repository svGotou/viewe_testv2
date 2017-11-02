<?php
//http://192.168.1.100/always_received.php
// ネットからのサンプルがベース
// sleep(1)を入れる
// 自動起動設定
// エラー処理入れる


// UDPグループ 224.0.23.0 に参加する処理が必要 @@@@@@@@@
// MCAST_JOIN_GROUP  // Joins a multicast group. (added in PHP 5.4)
// MCAST_LEAVE_GROUP  // Leaves a multicast group. (added in PHP 5.4)

// $ret = socket_set_option(
//             $socket_send,
//             IPPROTO_IP,
//             MCAST_JOIN_GROUP,
//             array('group' => $parts['host'], 'interface' => 0)
//         );

// $group = '239.255.1.1';
// $port = 1234;
// $iface = 'enp0s8';
// $socket_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
// socket_bind($socket_send, '0.0.0.0', $port);
// socket_set_option($socket_send, IPPROTO_IP, MCAST_JOIN_GROUP, [
//     'group' => $group,
//     'interface' => $iface,
// ]);

// error_reporting(E_ALL | E_STRICT);
// $socket_send = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
// $binded = socket_bind($socket_send, '0.0.0.0', 6073);
// $rval = socket_set_option($socket_send, getprotobyname("ip"), MCAST_JOIN_GROUP, array("group"=>"239.194.0.73","interface"=>0));
// $from = '';
// $port = 0;
// socket_recvfrom($socket_send, $buf, 12, MSG_WAITALL, $from, $port);
// echo "Received $buf from remote address $from and remote port $port" . PHP_EOL;

// for (;;) {
//     socket_recvfrom($socket_send, $buf, 1024, 0, $remoteAddr, $remotePort);
//     echo "[$remoteAddr:$remotePort] $buf\n";       
     
	// soundvision@debian:~$ pstree -cpn でプロセスIDツリーを表示する
	$logStr = $_SERVER['PHP_SELF'] . " [ " . getmypid() . " ]";  // プロセスIDを書き出し
	writeLog($logStr);
	
	class packet_format
	{
		public $type;
		public $message;
		public $recv_date;
	}
	
	$recv_data = new packet_format;
	$toAddr = '0.0.0.0';
	//$port = 5050;
	$port = 8345;
	//$port = 3610;  // EcoNet Lite

	//UDPのソケット作成
	$socket_receive = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
	$socket_send = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
	if ($socket_receive && $socket_send)  // ソケット生成OK
	{
			$logStr = "受信ソケット生成 OK";
			//echo $logStr . "<br />";
			writeLog($logStr);
			
			//socket_bind($socket_receive,'127.0.0.1',$port);
			$bindOK = socket_bind($socket_receive,$toAddr,$port);
			if ($bindOK == true)  // バインドOKか
				$logStr = "受信ソケットバインド OK";
			else
				$logStr = "受信ソケットバインド NG";
			writeLog($logStr);
				
			// グループ(EcoNet Lite)への参加
			//$group = '224.0.23.0';  // EcoNet Lite
			//socket_set_option($socket_receive, IPPROTO_IP, MCAST_JOIN_GROUP, ['group' => $group,'interface' => 0]);  // これなくても、データは届く
			
			$fromIP = '';
			while(true)
			{
				//UDPのソケットを受信
				socket_recvfrom($socket_receive, $buf, 4096, 0, $fromIP, $port);
				//バイナリで取得しているため、unpackでデータを取得
				//$packet_data = unpack("Stype/Smessage/ITimestamp", $buf);
				//$hexStr = bin2hex($buf);
				//$logStr = $fromIP . " のポート " . $port . " から " . $hexStr . " の ". strlen($buf) . " byteを受信しました。";
				$logStr = $fromIP . " のポート " . $port . " から " . $buf . " の ". strlen($buf) . " byteを受信しました。";
				//echo $logStr . "<br />";
				writeLog($logStr);
				
				if (strcmp($buf, 'end') == 0)
				{
					socket_close ($socket_receive);
					socket_close ($socket_send);
					$logStr = "アプリ抜けた";
					//echo $logStr . "<br />";
					writeLog($logStr);
					break;
				}
				else if (strcmp($buf, 'Search a server') == 0)
				{
					//対応する変数に代入
					//$recv_data->type = $array["type"];
					//$recv_data->message = $array["message"];
					//$recv_data->recv_date = date('D M d H:i:s Y',$array["Timestamp"]);
					/*
						確認応答パケットをUDPで送信(データは1,2,3,4)
						送るデータをpackでバイナリ文字列にパックした後送る
					*/
					// usleep入れるか @@@@@@@
					usleep(200000);  // 0.2秒待つ
					//$socket_send = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);  // 毎回していいのか？ @@@@@@@@
					//$sock_data = pack('SSSS',1,2,3,4);
					$sock_data = 'I am a ViewE server.';
					//$sock_data_hex = "108100010EF0010E01017202800130D604010105FF";
					//$sock_data_bin = hex2bin($sock_data_hex);
					$len = socket_sendto($socket_send, $sock_data, strlen($sock_data), 0, $fromIP, $port);
					if ($len)  // 送信OKか
					{
						$logStr = $fromIP . " のポート " . $port." へ " . $sock_data . " の ".$len." byteを送信しました。";
						//echo $logStr."<br />";
						writeLog($logStr);
					}
					else  // 送信NG
					{
							$errorCode = socket_last_error();
							$errorMsg = socket_strerror($errorCode);
				    		$logStr = "送信時 socket_sendto に失敗しましまた。[".$errorCode."] ".$errorMsg;
				    		writeLog($logStr);
					}
				}
				//usleep(200000);  // 0.2秒待つ
				sleep(1);  // 1秒待つ
			}
			
			//close socket
			socket_close($socket_receive);
			socket_close($socket_send);
	}
	else
	{
		$msgStr = "ソケット生成 NG";
		//echo $msgStr . "<br />";
		writeLog($msgStr);
	}
	
	
	
function writeLog($logStr)
{
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/mcast_log.txt');  // NUC
}


?>