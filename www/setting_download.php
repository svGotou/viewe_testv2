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
	document.write('<meta name="viewport" content="width='+device_width+'charger_output">');
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


<script type="text/javascript">
$(".type").click(function() {
    if($("input[name='con_type']:checked").val() == 2){
        $("#color_type").prop("disabled",true).css('background', '#bdc3c7');
    }else{
        $("#color_type").prop("disabled",false).css('background', '');
    }
});
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
<body>

<div id="container"><!--"container"を削除するとデザインが崩れます-->

<a name="top" id="top"></a>


<!--サイドメニューここから-->
<div id="sub">
  <ul class="sideNav">
<li><a href="viewe/setting.html">ViewE本体</a></li>
<li><a href="viewe/setting_target.html">消費目標値設定</a></li>
<li class="active"><a href="viewe/setting_download.html">消費電力の集計データ</a></li>
</ul>
</div>
<!--サイドメニューここまで-->

<!--[if !IE]>メインここから<![endif]-->
<div id="main">
<div class="category">
<h2>消費電力の集計データ</h2>


<div class="entry_body">
<h3>集計期間</h3>
<p>
<input id="ecocute-off" type="radio" name="period" value="year" checked><label>1年分</label>
<input id="ecocute-off" type="radio" name="period" value="month"><label>1ヵ月</label>
<input id="ecocute-off" type="radio" name="period" value="day"><label>1日分</label>
</p>

<div id="ansdiv">
<p>
<select id="year" name="year" class="pull-down" value="" >
</select>年
<select id="month" name="month" class="pull-down" value="" >
</select>月
<select id="day" name="day" class="pull-down" value="" >
</select>日
</p>



</div>
</div>
</div>
<!--[if !IE]>メインここまで<![endif]-->


</div><!--"container"-->

</body>
</html>

<!--設定コンテンツ終了-->
</div>
<div id="control">
<a href="csvdata/20170211.csv"><button id="send">ダウンロード</button></a>
<a href="viewe/main.html" id="back">メインページに戻る</a>
</div>
</div>
</div>
<!--setting-->
</div>
<script src="viewe/js/date.js" type="text/javascript"></script>
<script src="viewe/js/pref.js" type="text/javascript"></script>
<script type="text/javascript" src="config.js"></script>
</body>
</html>

