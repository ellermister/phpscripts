<?php
include "Ellery.php";
$queue = Ellery::make('qq.txt', 'qq', function (&$raw, &$body) {
    $raw = trim(str_replace('----', ' ', trim($raw, '"')));
    $buf = explode(' ', $raw);
    if (!isset($buf[1]) || strlen($raw) > 50) {
        echo "\n\n\n残废数据:$" . $raw . "$\n\n\n\n\n\n";
        unset($buf);
        return;//跳过本次残废数据
    }
    if (count($buf) > 2) {
        $qq = array_shift($buf);
        foreach ($buf as $mobile) {
            $body[] = array(
                'qq'    => trim($qq),
                'phone' => trim($mobile),
            );
            unset($mobile);
        }
    } else {
        $body = array(
            'qq'    => trim($buf[0]),
            'phone' => trim($buf[1]),
        );
    }
    unset($buf);
}, 'http://192.168.1.112:9200', 16, 3000);
$queue->startQueue(Ellery::QUEUE_TYPE_SEND);