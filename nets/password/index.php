<?php

define('ROOT_PATH', __DIR__);
define('DATA_PATH', __DIR__.DIRECTORY_SEPARATOR.'data');
define('AUTH_KEY', '0123456789^_^~');

/**
 * 通信加密2
 */
function net_encrypt_data2($plaintext, $key, $cipher = 'RC4'){
    $key = substr(strtoupper(md5($key).sha1($key)),0,64);
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_NO_PADDING);
    $ciphertext = safe_base64_encode($ciphertext);
    return $ciphertext;
}

/**
 * 通信解密2
 */
function net_decrypt_data2($ciphertext, $key, $cipher = 'RC4'){
    $key = substr(strtoupper(md5($key).sha1($key)),0,64);
    $plaintext = openssl_decrypt(safe_base64_decode($ciphertext), $cipher, $key, OPENSSL_NO_PADDING);
    return $plaintext;
}

/**
 * 加密数据(不混淆)
 * @param $plaintext
 * @param $key
 */
function encrypt_data($plaintext, $key, $iv = null){
    $cipher = 'AES-256-CFB';
    $ivlen = openssl_cipher_iv_length($cipher);

    if($iv == null){
        $iv = openssl_random_pseudo_bytes($ivlen);
    }else{
        $iv = substr(md5($iv), $ivlen);
    }
    $ciphertext = @openssl_encrypt($plaintext, $cipher, $key, OPENSSL_NO_PADDING, $iv);
    $ciphertext =  safe_base64_encode($ciphertext);

    $encrypted = $ciphertext . ':' . safe_base64_encode($iv);
    return $encrypted;
}

/**
 * 解密数据(不混淆)
 * @param $ciphertext
 * @param $key
 */

function decrypt_data($ciphertext, $key){
    list($ciphertext, $iv) = explode(':', $ciphertext);
    $cipher = 'AES-256-CFB';
//	$ivlen = openssl_cipher_iv_length($cipher);
//	$iv = openssl_random_pseudo_bytes($ivlen);
    $iv = safe_base64_decode($iv);
    return @openssl_decrypt(safe_base64_decode($ciphertext), $cipher, $key, OPENSSL_NO_PADDING, $iv);
}

/**
 * safe base64 encode
 */
function safe_base64_encode($text){
    return str_replace(['+','/','='], ['-','_',''], base64_encode($text));
}

/**
 * safe base64 decode
 */
function safe_base64_decode($text){
    $data = str_replace(['-','_'], ['+','/'], $text);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    $data = base64_decode($data);
    return $data;
}

function rand_string($length = 10){
    $str="QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
    while(strlen($str) < $length){
        $str .= $str;
    }
    str_shuffle($str);
    return substr(str_shuffle($str),26,$length);
}

/**
 * 获取文件列表
 */
function files($dir, $ext = 'pmap'){
    $ret = scandir($dir);
    $file = [];
    foreach($ret as $item){
        if($item == '.' || $item == '..')
            continue;
        if(preg_match('/\.'.$ext.'$/is', $item)){
            $file[] = $item;
        }
    }
    return $file;
}

function characet($data){
    if( !empty($data) ){
        $fileType = mb_detect_encoding($data , array('UTF-8','GBK','LATIN1','BIG5')) ;
        if( $fileType != 'UTF-8'){
            $data = mb_convert_encoding($data ,'utf-8' , $fileType);
        }
    }
    return $data;
}
function response($message, $code, $data = []){
    $format = [
        'response' => [
            'code' => $code,
            'msg'  => $message,
            'padding' => rand_string(255)
        ],
        'data' => $data
    ];
    //return json_encode($format);
    return net_encrypt_data2(json_encode($format), AUTH_KEY);
}

/**
 * 存储数据
 */
function storage(){
    $file = isset($_GET['file'])? $_GET['file']: null;
    $dir  = isset($_GET['dir'])? $_GET['dir']: null;
    $data  = isset($_GET['data'])? $_GET['data']: null;
    if(!is_dir(DATA_PATH.DIRECTORY_SEPARATOR.$dir)){
        @mkdir(DATA_PATH.DIRECTORY_SEPARATOR.$dir);
        @chmod(DATA_PATH.DIRECTORY_SEPARATOR.$dir,775);
    }

    if(is_dir(DATA_PATH.DIRECTORY_SEPARATOR.$dir)){
        $data = encrypt_data($data, AUTH_KEY);
        file_put_contents(DATA_PATH.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$file, $data);
        echo response('ok',200);
    }
}

/**
 * 读取数据
 */
function fetch(){
    $file = isset($_GET['file'])? $_GET['file']: null;
    $dir  = isset($_GET['dir'])? $_GET['dir']: null;
    if(is_file(DATA_PATH.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$file)){
        $content = file_get_contents(DATA_PATH.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$file);
        $content = decrypt_data($content, AUTH_KEY);
        echo response('ok',200, $content);
    }
}

/**
 * 列出文件
 */
function lists(){
    $dir = isset($_GET['dir'])? $_GET['dir']: null;
    $ret = [];
    if(is_dir(DATA_PATH.DIRECTORY_SEPARATOR.$dir)){
        $ret = files(DATA_PATH.DIRECTORY_SEPARATOR.$dir);
    }
    echo response('ok',200, $ret);
}

/**
 * 清空文件
 */
function remove_all(){
    $dir = isset($_GET['dir'])? $_GET['dir']: null;
    if(is_dir(DATA_PATH.DIRECTORY_SEPARATOR.$dir)){
        $ret = files(DATA_PATH.DIRECTORY_SEPARATOR.$dir);
        foreach($ret as $item){
            $file = DATA_PATH.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$item;
            unlink($file);
        }
        echo response('ok',200, $ret);
    }
}

if($_REQUEST['s']){
    $post = net_decrypt_data2($_REQUEST['s'], AUTH_KEY);
    parse_str($post, $data);
    $_GET = $data;
    function_exists($_GET['action']) && in_array($_GET['action'], ['storage', 'fetch', 'lists','remove_all'])  && $_GET['action']();
}
