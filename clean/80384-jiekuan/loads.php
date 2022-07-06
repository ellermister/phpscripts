<?php
/**
 * Created by PhpStorm.
 * User: ellermister
 * Date: 2022/7/6
 * Time: 2:33
 */


$filepath = getenv('JIEKUAN80384_PATH');
if(!is_file($filepath)){
    die('JIEKUAN80384_PATH not specified');
}

$es_hosts = getenv('ES_HOSTS');
if(!$es_hosts){
    die('ES_HOSTS not specified');
}
define('ES_HOSTS', $es_hosts);

include "../../es.php";
$es = getEs();


$fh = fopen($filepath, "r");

$params = [
    'body' => []
];

$index = 'jiekuan80384';

while (!feof($fh)) {
    $row = fgets($fh);
    $json = json_decode($row, true);
    if ($json !== null) {
        $head = [
            'index' => [
                '_index' => $index,
                '_id'    => $json['_id'] ?? null
            ]
        ];
        $params['body'][] = $head;
        $params['body'][] = $json;
    }

    if (count($params['body']) >= 1000) {
        $res = $es->bulk($params);
        unset($params);
        $params = [
            'body' => []
        ];
    }
    unset($row);
}

if (count($params['body']) > 0) {
    $res = $es->bulk($params);
    unset($params);
}
fclose($fh);
echo "done!\n";