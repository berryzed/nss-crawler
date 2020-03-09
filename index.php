<?php
header('Content-Type: application/json');

require __DIR__."/vendor/autoload.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Telegram\Bot\Api;

//-- 설정 불러오기
$file = file_get_contents('setting.json');
if ($file === FALSE) {
    echo json_encode(array('error' => true, 'msg' => 'load failed'));
    exit;
}

$settings = json_decode($file);
if ($settings === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(array('error' => true, 'msg' => 'json data incorrect'));
    exit;
}

if (count($settings->markets) <= 0) {
    echo json_encode(array('error' => true, 'msg' => 'market data is none'));
    exit;
}

//-- 초기화
$timeout = 30;
if (isset($settings->goutte->timeout) && is_numeric($settings->goutte->timeout)) {
    $timeout = $settings->goutte->timeout;
}

$userAgent = "Mozilla/5.0 (Linux; Android 8.0.0; Pixel 2 XL Build/OPD1.170816.004) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.146 Mobile Safari/537.36";
if (isset($settings->goutte->userAgent)) {
    $userAgent = $settings->goutte->userAgent;
}

$client = new Client(HttpClient::create([
    'timeout' => $timeout,
    'headers' => [
        'User-Agent' => $userAgent
        ]
    ]));
$client->setServerParameter('HTTP_USER_AGENT', $userAgent);

//-- 모바일 스마트스토어 기준
$results = array();
$success = 0;

foreach ($settings->markets as $market) {
    $crawler = $client->request('GET', $market->url);
    $cnt = $crawler->filter('button._3qTIMZDKm3')->count();

    if ($cnt > 0) {
        $success++;
    }
    else {
        $market->url = "soldout";
    }

    array_push($results, $market);

    usleep(100 * 1000); // 100000us(msec) = 0.1s
}

//--텔레그램 봇
if ($success > 0 && isset($settings->telegram->token) && isset($settings->telegram->chatId)) {
    $text = '';
    foreach ($results as $rst) {
        $text .= $rst->name.': '.$rst->url.PHP_EOL;
    }

    $telegram = new Api($settings->telegram->token, true);

    $telegram->sendMessage([
        'chat_id' => $settings->telegram->chatId, 
        'text' => $text,
        'disable_web_page_preview' => true
    ]);
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>