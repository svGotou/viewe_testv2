<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
</head>

<?php
	date_default_timezone_set('Asia/Tokyo');

	// DBへのコネクト
	$flag = TRUE;
	require_once 'db_info.php';  // DBの情報
	//if (! $db = mysql_connect("localhost", "root", "soundvision"))  // debain NUC
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
		$tbl_name = "bundenban_test";  // テスト
		//$tbl_name = "bundenban3";  // 本番
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
				echo mysql_errno($db) . ": " . mysql_error($db) . "<br />";
			else
				echo "テーブル「{$tbl_name}」を作成しました。<br />";
			
			// テーブルの一覧表示
			show_tables($db_name, $db);
			echo "<br />";
			
			// フィールド属性の一覧表示
			show_fields($db_name, $tbl_name, $db);
			echo "<br />";
		}
		else  // テーブルが存在する場合
		{
			echo "テーブル「{$tbl_name}」は作成済みです。<br />";
		}
		mysql_close($db);
	}
	else
	{
		echo "データベース準備NG<br />";
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
?>

</html>