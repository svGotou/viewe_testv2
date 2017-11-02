<?php

// サーバーのグローバルIPを返す
//soundvision@debian:~$ wget -q -O - ipcheck.ieserver.net
//124.44.56.247
$ipAdd = shell_exec('wget -q -O - ipcheck.ieserver.net');
echo $ipAdd;

?>