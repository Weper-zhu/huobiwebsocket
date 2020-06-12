<?php
$url = urldecode($_GET['url']);
if ($url) {
    echo curl_get($url);
    die();
}else{
  echo "How are you";
}
function curl_get($url, $timeout = 5)
{
    // var_dump($url);
    $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
    $ch = curl_init();
    $headers = array(
        "Content-Type: application/json charset=utf-8",
        //'Content-Length: ' . strlen($fields),
        //'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36',
    );

    $opt = array(
        CURLOPT_URL => $url,
        //CURLOPT_URL => 'http://www.apple.com/',
        //CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_CUSTOMREQUEST => strtoupper('GET'),
        //CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        //CURLOPT_PROXY => '47.56.188.100',
        //CURLOPT_PROXY => '127.0.0.1',
        //CURLOPT_PROXYPORT => '30999',
        //CURLOPT_PROXYPORT => '1080',
        //CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
        //CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
        //CURLOPT_PROXY => 'socks5://127.0.0.1:1080',
        //CURLOPT_PROXY => 'localhost:1080',
        //CURLOPT_HTTPPROXYTUNNEL => true,
        //CURLOPT_FOLLOWLOCATION => 1,
    );

    if ($ssl) {
        //$opt[CURLOPT_SSL_VERIFYHOST] = 2;
        $opt[CURLOPT_SSL_VERIFYHOST] = false;
        $opt[CURLOPT_SSL_VERIFYPEER] = FALSE;
    }
    curl_setopt_array($ch, $opt);
    $result = curl_exec($ch);
    //var_dump($result);
    //var_dump(curl_error($ch));
    //var_dump(curl_errno($ch));
    curl_close($ch);
    return $result;
}
