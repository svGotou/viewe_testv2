$('head').append(
    '<style type="text/css">#wrap-ld { display: none; } #loader-bg, #loader { display: block; }</style>'
);
 
jQuery.event.add(window,"load",function() { // 全ての読み込み完了後に呼ばれる関数
    var pageH = $("#wrap-ld").height();
 
    $("#loader-bg").css("height", pageH).delay(900).fadeOut(800);
    $("#loader").delay(600).fadeOut(300);
    $("#wrap-ld").css("display", "block");
});