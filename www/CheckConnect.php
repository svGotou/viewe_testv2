<?php

$serverIP = getSelfIPaddr();  // 自分(サーバー)のIPアドレス
$bundenIP = getEcoEyeIPaddr($serverIP);  // EcoEye(多機能分電盤)IPアドレス

echo "Server >>> $serverIP<br />EcoEye >>> $bundenIP<br />";


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
	
	return $ipAddress;
}

// EcoEye(多機能分電盤)IPアドレス
function getEcoEyeIPaddr($selfAddr)
{
	$targetAddr = "0.0.0.0";  // 分電盤のIPアドレス<--これを求める!!!
	$addrArray = explode(".", $selfAddr);  // 戻り値を配列に
	$addr333 = $addrArray[0].".".$addrArray[1].".".$addrArray[2].".";  // ex.'192.168.1.'
	
	// pingコマンドのループ(dddを変える)
	for ($i=1; $i<=255; ++$i)
	{
		$swHit = FALSE;
		$address = $addr333.strval($i);
		$retStr1 = shell_exec('ping -c 1 -t 1 '.$address);  // 1回送ってタイムアウトは1秒

		// 受信データサーチ
		$pos1 = strpos($retStr1, '1 received');  // debian
		if ($pos1 !== FALSE)  // 応答有り(ping OK)
		{
			$retStr2 = shell_exec('ip neigh show');
			
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
			//break;
		}  // if ($pos1

		if ($swHit == TRUE)
			break;
	}  // for
	
	return $targetAddr;
}
?>
