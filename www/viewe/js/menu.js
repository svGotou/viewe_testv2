
$(function($) {
    WindowHeight = $(window).height();
    $('.gblnv_block').css('height', WindowHeight);
	
    $(document).ready(function() {
        $('.menu-trigger').click(function(){ 
            $('.gblnv_block').animate({width:'toggle'}); 
            $(this).toggleClass('active');
			
		if($(this).hasClass('active')){
			document.getElementById("menu_text").innerHTML = '閉じる';
		} else {
			document.getElementById("menu_text").innerHTML = 'メニュー';
        }
		
		});
    });
});



