<?php
$url = "http://localhost:8001/";
echo "Testing connection to $url\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "ERROR: $err\n";
} else {
    echo "SUCCESS: Code $code\n";
    echo "Response: $res\n";
}
