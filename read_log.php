<?php
$file = 'var/log/dev.log';
$lines = 50;
$handle = fopen($file, "r");
$linecounter = $lines;
$pos = -2;
$beginning = false;
$text = array();
while ($linecounter > 0) {
    $t = " ";
    while ($t != "\n") {
        if(fseek($handle, $pos, SEEK_END) == -1) {
            $beginning = true;
            break;
        }
        $t = fgetc($handle);
        $pos --;
    }
    $linecounter --;
    if ($beginning) {
        rewind($handle);
    }
    $text[$lines-$linecounter-1] = fgets($handle);
    if ($beginning) break;
}
fclose ($handle);
echo implode("", array_reverse($text));
