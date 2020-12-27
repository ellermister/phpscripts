<?php
include "Ellery.php";
$queue = Ellery::make('weibo2019.txt', 'weibo', function (&$raw, &$body) {
    $raw = trim(str_replace('----', ' ', trim($raw, '"')));
    if (preg_match('/^phone/is', $raw)) {
        echo "\n\n\n首行跳过:$" . $raw . "$\n\n\n\n\n\n";
        $body = [];
        return;//跳过首行
    }
    $buf = explode("\t", $raw);
    if (!isset($buf[1]) || strlen($raw) > 50) {
        echo "\n\n\n残废数据:$" . $raw . "$\n\n\n\n\n\n";
        unset($buf);
        $body = [];
        return;//跳过本次残废数据
    }
    $body = array(
        'phone' => $buf[0],
        'uid'   => $buf[1],
    );
    unset($buf);
}, 'http://192.168.1.112:9200', 16, 3000);
$queue->startQueue(Ellery::QUEUE_TYPE_SEND);

// host--> https://elastic:password@domain:9200