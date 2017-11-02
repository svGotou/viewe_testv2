<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="content-script-type" content="text/javascript" />
<meta http-equiv="content-style-type" content="text/css" />
<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">

<script src="http://code.jquery.com/jquery-1.10.2.js"></script>
<script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script type="text/javascript" src="viewe/common/js/code-check.js"></script>
<script type="text/javascript" src="viewe/request.js"></script>
<script type="text/javascript">
// <![CDATA[
	document.write('<meta name="viewport" content="width='+device_width+'">');
	document.write('<link rel="stylesheet" href="viewe/common/css/'+code+'/common.css" type="text/css" charset="UTF-8" title="style">');
	document.write('<link rel="stylesheet" href="viewe/common/css/'+code+'/setting.css" type="text/css" charset="UTF-8" title="style">');
	$(function() {
		$('button.dateselect').click(function(){
			$(this).prev('input').datepicker();
			$(this).prev('input').datepicker("show");
		});
	});
// ]]>
</script>

<!--カレンダー用-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1/i18n/jquery.ui.datepicker-ja.min.js"></script>
<script src="viewe/js/jquery.mtz.monthpicker.js"></script>
<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1/themes/ui-lightness/jquery-ui.css" >

<script>

$(window).on('touchmove.noScroll', function(e) {
    e.preventDefault();
});

$(function(){
$('#datepicker_day').datepicker({
        dateFormat: 'yymmdd',
    }).datepicker('show');
});

var time = new Date();
var year = time.getFullYear();
for (var i = year; i >= 2014; i--) {
    $('#year').append('<option value="' + i + '">' + i + '</option>');
}
</script>


<meta name="description" content="" />
<meta name="keywords" content="" />
<title>設定 | ViewE</title>
<link rel="stylesheet" href="viewe/common/css/adjuster.css" />
</head>
<body>
<div id="wrapper">
<!--header-->
<div id="header">
<div class="container">
<a href="viewe/main.html"><img src="viewe/common/images/pc/logo.png" alt="ViewE" id="logo"></a>
<div id="header-navi-set">
<a href="viewe/manual.html" id="header-manual">操作説明</a>
<a href="viewe/setting.html" id="header-setting">設定</a>
</div>
<!--
<div id="header-user">
ユーザーID:「USER1234」でログインしています。
</div>
-->
</div>
</div>
<!--header-->
<!--setting-->
<div class="container">
<div id="setting">
<h1>設定</h1>



<div class="inner">
<!--設定コンテンツ-->

<div id="container"><!--"container"を削除するとデザインが崩れます-->

<a name="top" id="top"></a>


<!--サイドメニューここから-->
<div id="sub">
  <ul class="sideNav">
<li><a href="viewe/setting.html">ViewE本体</a></li>
<li><a href="viewe/setting_target.html">消費目標値設定</a></li>
<li class="active"><a href="setting_download_year.php">消費電力の集計データ</a></li>
</ul>
</div>
<!--サイドメニューここまで-->

<!--[if !IE]>メインここから<![endif]-->
<div id="main">
<div class="category">
<h2>消費電力の集計データ</h2>


<div class="entry_body">
<div class="setting_form">

<h3>集計期間</h3>

<form action="dump_csv.php" method="get">

    
    <div id="unit-switch">
        <a href="setting_download_year.php" id="unit-year">年間</a>
        <a href="setting_download_month.php" id="unit-month">月間</a>
        <a href="setting_download_day.php" class="active" id="unit-day">１日</a>
	</div>

	<div id="kikan-from">
    <span class="small_text">集計データを取得したい期間を選択してください。</span><br>
         <span id="fm">期間</span><input type="text" name="ymd" id="datepicker_day">
	</div>
<input type="submit" id="send_dl" value="ダウンロード">
</form>



</div>
</div>
</div>
</div>
<!--[if !IE]>メインここまで<![endif]-->


</div><!--"container"-->


<!--設定コンテンツ終了-->
</div>
<div id="control">
<a href="viewe/main.html" id="back_dl">メインページに戻る</a>
</div>
</div>
</div>
<!--setting-->
</div>
<script type="text/javascript" src="viewe/js/date.js"></script>
<script type="text/javascript" src="viewe/js/pref.js"></script>
<script type="text/javascript" src="viewe/config.js"></script>
</body>
</html>

