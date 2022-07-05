<?php
/**
 * Created by PhpStorm.
 * User: ellermister
 * Date: 2022/7/5
 * Time: 2:19
 */

if(!getenv('QBIT_HOST')){
    die('QBIT_HOST not specified');
}

if(!getenv('QBIT_COOKIE')){
    die('QBIT_COOKIE not specified');
}

define('HTTP_HOST', getenv('QBIT_HOST'));
define('USER_COOKIE', getenv('QBIT_COOKIE'));

function qbit_bt_list($hash, $maxM = 500)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, HTTP_HOST.'/api/v2/torrents/files?hash='.$hash.'&l572clhc');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . USER_COOKIE
    ]);

    $server_output = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($server_output, true);
    $selected = [];
    $unselected = [];
    if ($data !== null) {
        foreach ($data as $value) {
            if ($value['size'] <= 1024 * 1024 * $maxM) {
                $selected[] = $value['index'];
            } else {
                $unselected[] = $value['index'];
            }
        }
    }
    return [$selected, $unselected];
}

function qbit_select_file($hash, $files)
{
    list($selected, $unselected) = $files;
    $selected_str = implode('|', $selected);
    $unselected_str = implode('|', $unselected);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, HTTP_HOST . '/api/v2/torrents/filePrio');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "hash={$hash}&id={$selected_str}&priority=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . USER_COOKIE
    ]);

    $server_output = curl_exec($ch);
    curl_close($ch);
    var_dump($server_output);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, HTTP_HOST . '/api/v2/torrents/filePrio');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "hash={$hash}&id={$unselected_str}&priority=0");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . USER_COOKIE
    ]);

    $server_output = curl_exec($ch);
    curl_close($ch);
    var_dump($server_output);
}

if(!isset($argv[1]) || !isset($argv[2])){
    die("Incorrect input, format like php qbit-min.php {torrent hash} {max size file} \neg: \nphp qbit-min.php 2ffdd218d1d716a64c4cb384af5a33c174729307 500");
}

$hash  = strval($argv[1]);
$maxM  = intval($argv[2]);
$files = qbit_bt_list($hash, $maxM);
if($maxM > -1){
    qbit_select_file($hash, $files);
}else{
    $file_count = count($files[1]);
    echo "get files count {$file_count} of torrent.\n";
}
echo "done\n";