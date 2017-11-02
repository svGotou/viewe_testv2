<?php

$uuid = @file_get_contents('/etc/security/soundvision.d/uuid');

if (!$uuid) {
	echo '';
	exit();
}

echo clean($uuid);

/***
Utility function(s)
**/
function clean($str) {
	return str_replace(array("\n", "\r"), '', $str);
}
