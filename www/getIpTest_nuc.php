<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
</head>

<?php
		date_default_timezone_set('Asia/Tokyo');
		
	    $ipAddr = getSelfIPaddr();
	    echo "ipAddr=".$ipAddr."<br />";
	   	$toIP = getEcoEyeIPaddr();  // EcoEye(多機能分電盤)IPアドレス
	   	echo "toIP=".$toIP."<br />";

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
function getEcoEyeIPaddr()
{
	// 先ず自分のIPアドレスを知る
	// 192.168.1.xxx
	$targetAddr = "0.0.0.0";  // 分電盤のIPアドレス<--これを求める!!!
	$selfAddr = getSelfIPaddr();  // サーバーのローカルIPアドレスを取得
	// aaa.bbb.ccc.dddのaaa.bbb.cccを得る
	$addrArray = explode(".", $selfAddr);  // 戻り値を配列に
	$addr333 = $addrArray[0].".".$addrArray[1].".".$addrArray[2].".";  // ex.'192.168.1.'
	echo "addr333=".$addr333."<br />";
	
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

		echo 'ping -c 1 -t 1 '.$address."<br />";
		echo "retStr1=".$retStr1."<br />";
		// 受信データサーチ
		//$pos1 = strpos($retStr1, '1 packets received');  // Mac
		$pos1 = strpos($retStr1, '1 received');  // debian
		echo "pos1=".$pos1."<br />";
		if ($pos1 !== FALSE)  // 応答有り(ping OK)
		{
			//echo $pos;
			echo '1 received 有り'.'<br />';
			//arpコマンドを送信
			//$retStr2 = shell_exec('arp -a -n');  // debian NG
			//soundvision@debian:~$ sudo arp -a -n
			//? (192.168.1.1) at 00:25:36:8c:1a:c6 [ether] on eth0
			//? (192.168.1.5) at e4:ce:8f:5e:81:8b [ether] on eth0
			//? (192.168.1.7) at 00:11:03:06:00:28 [ether] on eth0
			//echo 'arp -a -n<br />';
			
			$retStr2 = shell_exec('ip neigh show');  // Mac NG
			//fe80::225:36ff:fe8c:1ac6 dev eth0 lladdr 00:25:36:8c:1a:c6 router STALE 192.168.1.1 dev eth0 lladdr 00:25:36:8c:1a:c6 STALE 192.168.1.5 dev eth0 lladdr e4:ce:8f:5e:81:8b DELAY 192.168.1.7 dev eth0 lladdr 00:11:03:06:00:28 DELAY
			
			echo "retStr2=".$retStr2."<br />";
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
				echo "retArray=".$retArray[$j]."<br />";
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
	error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, 'ecoEye_log2.txt');  // Mac
	//error_log(date('Y-m-d H:i:s')." > ".$logStr.chr(0x0d).chr(0x0a), 3, '/var/www/ecoEye_log2.txt');  // debin
}


?>

</html>