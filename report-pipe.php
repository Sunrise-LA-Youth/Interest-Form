#!/opt/cpanel/ea-php73/root/usr/bin/php -q
<?php
error_reporting(0);
require_once 'vendor/autoload.php';
$fd = fopen("php://stdin", "r");
$email = ""; // This will be the variable holding the data.
while (!feof($fd)) {
    $email .= fread($fd, 1024);
}
fclose($fd);

$fdw = fopen(__DIR__."/email".time().".txt", "w+");
fwrite($fdw, $email);
fclose($fdw);
