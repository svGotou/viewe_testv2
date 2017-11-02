var xhr = new XMLHttpRequest();
xhr.open('GET', '../../get_property_battery_class.php?q=str', true);
xhr.send();


xhr.onreadystatechange = function() {
  if(xhr.readyState === 4 && xhr.status === 200) {

	var rcvStr = ( xhr.responseText )
	//var rcvStr = "off";  //xhr.responseText
	var propArry = rcvStr.split( "," );
	var mode;
	
 	if (propArry[0] == "on")
 	{
 		if (propArry[1] == "1"){
 			mode = "充電中";
		}else if (propArry[1] == "2"){
 			mode = "放電中";
		}else{
 			mode = "待機中";
		}
 			document.getElementById("state").innerHTML = mode;
  			document.getElementById("battery_num").innerHTML = propArry[2]+"%";
			document.getElementById("battery-bgm").style.width = propArry[2]-"6"+"%";
 	}
 	else
 	{
 		document.getElementById("state").innerHTML = "電源OFF";
   		document.getElementById("battery_charge").innerHTML = "蓄電池";
   		document.getElementById("battery_num").innerHTML = "停止中";
	}
}  		
}



