<?php
/**
 * tcping and icmp ping
 *
 * tcping for windows:
 * download: https://elifulkerson.com/projects/tcping.php
 *
 * tcping for linux:
 * download page: http://www.linuxco.de/tcping/tcping.html
 * make install:
 *        wget https://sources.voidlinux.eu/tcping-1.3.5/tcping-1.3.5.tar.gz
 *        tar zxvf tcping-1.3.5.tar.gz
 *        cd tcping-1.3.5/
 *        yum install gcc
 *        gcc -o tcping tcping.c
 *        cp tcping /usr/bin
 *
 */

require '../../vendor/autoload.php';

if(is_file('config.env')){
    // 自定義常量配置請在此文件指定
    include 'config.env';
}
defined('NODE_ID') or define('NODE_ID', 1);
defined('NODE_SECRET') or define('NODE_SECRET', '');
defined('API_FETCH') or define('API_FETCH', '');
defined('API_PUSH') or define('API_PUSH', '');
defined('FILE_TASK') or define('FILE_TASK', 'task.temp');
defined('FILE_ANSWER') or define('FILE_ANSWER', 'answer.temp');


/**
 * 獲取當前系統OS
 * @return string
 */
function current_os()
{
    if (strcasecmp(PHP_OS, 'WINNT') === 0) {
        //Windows NT
        return 'windows';
    } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
        //Linux
        return 'linux';
    }
    return PHP_OS;
}

/**
 * TCP檢測端口通信狀態
 * @param $ip
 * @param string $port
 * @return bool
 */
function tcping_check($ip, $port = '80')
{
    if (current_os() == 'windows') {
        $lastout = exec('tcping.exe -n 1 -i 2 -p ' . $port . ' ' . $ip, $output);

        if (preg_match('/Was unable to connect/', $lastout)) {
            return false;
        }
        if (preg_match('/Could not/', $lastout)) {
            //DNS: Could not find host - 22, aborting
            return false;
        }
        if (preg_match('/Average/', $lastout)) {
            return true;
        }
    } elseif (current_os() == 'linux') {
        $lastout = exec('tcping -t 2 ' . $ip . ' ' . $port, $output);
        if (preg_match('/timeout/', $lastout)) {
            return false;
        }
        if (preg_match('/closed/', $lastout)) {
            return false;
        }
        if (preg_match('/open/', $lastout)) {
            return true;
        }
    }
    return false;
}


/**
 * ICMP檢測端口通信狀態
 * @param $ip
 * @return bool
 */
function icmp_ping($ip)
{
    if (current_os() == 'windows') {
        $lastout = exec('ping -n 1 ' . $ip, $output);
        if (preg_match('/Average\s+=[^\d]+\d+ms/is', $lastout)) {
            return true;
        }
    } elseif (current_os() == 'linux') {
        $lastout = exec("ping -c 1 {$ip}", $outcome, $status);
        if (preg_match('/min\/avg\/max\/mdev\s+=\s+[\d\.]+\/([\d\.]+)\/[\d\.]+\/[\d\.]+ ms/is', $lastout, $result)) {
            return true;
        }
    }
    return false;
}


/**
 * 輸出JSON
 * @param $msg
 * @param int $status
 * @param array $datas
 */
function output_json($msg, $status = 0, $datas = [])
{
    $json = [
        'status' => $status,
        'msg' => $msg,
        'datas' => $datas
    ];
    echo json_encode($json);
    exit;
}


/**
 * 解析command參數
 * @param array $args
 * @return array
 */
function parse_command(array $args)
{
    array_shift($args);
    return $args;
}

/**
 * 獲取當前COMMAND所執行的腳本文件
 * @return mixed
 */
function current_script()
{
    global $argv;
    $copy = $argv;
    return array_shift($copy);
}

/**
 * 是否是CLI環境
 * @return bool
 */
function is_cli()
{
    return preg_match("/cli/i", php_sapi_name()) ? true : false;
}

/**
 * 自定義通信驗證
 * @param $secret
 * @param $time
 * @param $data
 * @return string
 */
function hash_token($secret, $time, $data)
{
    return md5($data . $time . $secret);
}

/**
 * 通信加密2
 * @param $plaintext
 * @param $key
 * @param string $cipher
 * @return mixed|string
 */
function net_encrypt_data2($plaintext, $key, $cipher = 'RC4')
{
    $key = substr(strtoupper(md5($key) . sha1($key)), 0, 64);
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_NO_PADDING);
    $ciphertext = safe_base64_encode($ciphertext);
    return $ciphertext;
}

/**
 * 通信解密2
 * @param $ciphertext
 * @param $key
 * @param string $cipher
 * @return string
 */
function net_decrypt_data2($ciphertext, $key, $cipher = 'RC4')
{
    $key = substr(strtoupper(md5($key) . sha1($key)), 0, 64);
    $plaintext = openssl_decrypt(safe_base64_decode($ciphertext), $cipher, $key, OPENSSL_NO_PADDING);
    return $plaintext;
}

/**
 * API拉取遠程任務數據
 * @param $task
 */
function fetch_remote($task)
{
    if (!empty(API_FETCH)) {
        $token = hash_token(NODE_SECRET, date('YmdHi'), '');
        $url = sprintf(API_FETCH . '?id=%s&token=%s', NODE_ID, $token);
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        $old = json_decode(file_get_contents($task), true);
        foreach ($result['datas'] as $ip => $portList) {
            $old[$ip] = $portList;
        }
        file_put_contents($task, json_encode($old, JSON_PRETTY_PRINT));
    }
}

/**
 * API回應遠程任務結果
 * 如果需要單文件運行，請替換其中的CURL方法
 * @param $answer
 * @throws \GuzzleHttp\Exception\GuzzleException
 */
function push_remote($answer)
{
    if (!empty(API_PUSH)) {
        @$raw = file_get_contents($answer);
        @$result = json_decode($raw, true);

        if (!empty($result) && $result != null) {
            $data = json_encode($result, JSON_UNESCAPED_UNICODE);
            $data = net_encrypt_data2($data, NODE_SECRET);

            $token = hash_token(NODE_SECRET, date('YmdHi'), $data);
            $url = sprintf(API_FETCH . '?id=%s&token=%s', NODE_ID, $token);

            $client = new \GuzzleHttp\Client();
            $headers = ['content-type' => 'application/x-www-form-urlencoded;charset=UTF-8'];
            $response = $client->request('POST', $url, [
                'form_params' => ['data' => $data],
                'headers' => $headers,
            ]);

            $body = (string)$response->getBody();
            $result = json_decode($body, true);

            if (isset($result['code']) && $result['code'] != '200') {
                echo 'PUSH_REMOTE, ERROR: ' . $result['message'] . PHP_EOL;
            }
        }
    }
}


// ======== main ========

// 判斷當前是CLI訪問
if (is_cli()) {
    $task = __DIR__ . DIRECTORY_SEPARATOR . FILE_TASK;
    $answer = __DIR__ . DIRECTORY_SEPARATOR . FILE_ANSWER;

    $command = parse_command($argv);
    if (!isset($command[0])) {
        echo sprintf('請給予參數:%sphp %s [command]' . PHP_EOL . '  -- command = [listen, scan]' . PHP_EOL, PHP_EOL, current_script());
        die;
    }


    // 手動指定監聽內容
    if ($command[0] == 'listen') {
        if (!isset($command[1]) || !isset($command[2])) {
            echo sprintf('參數不全，請指定完整參數, 如:%sphp %s listen 8.8.8.8 22-3389' . PHP_EOL, PHP_EOL, current_script());
            die;
        }
        $ip = $command[1];
        $portStr = $command[2];
        if (preg_match('/^(\d{2,})\-(\d{2,})$/is', $portStr, $matches)) {
            $portList = range($matches[1], $matches[2]);
        } elseif (strpos($portStr, ',') !== false) {
            $portList = explode(',', $portStr);
        } else {
            $portList = intval($portStr);
        }

        try {
            @$raw = file_get_contents($task);
            $taskList = json_decode($raw, true);
        } catch (Exception $e) {
            $taskList = [];
        }
        $taskList[$ip] = $portList;

        $result = file_put_contents($task, json_encode($taskList, JSON_PRETTY_PRINT));
        echo $result ? '監聽成功' : '監聽失敗';
        echo PHP_EOL;
        die;

    }

    // 常駐後台掃描任務&提交任務
    elseif ($command[0] == 'scan') {

        if (!is_file($task)) {
            echo '任務不存在!';
            die;
        }
        echo '執行任務~' . PHP_EOL;
        while (1) {
            fetch_remote($task);

            if (!is_file($task)) {
                sleep(10); // 延时等待文件
                continue;
            }

            $raw = file_get_contents($task);
            try {
                $taskList = json_decode($raw, true);
            } catch (Exception $e) {
                echo 'ERROR:' . $e->getMessage();
                die;
            }

            $assignment = [];
            foreach ($taskList as $ip => $portList) {
                foreach ($portList as $port) {
                    $connected = tcping_check($ip, $port);
                    $assignment[$ip][$port] = $connected;
                }
                sleep(1);
            }

            $format = json_encode($assignment, JSON_PRETTY_PRINT);
            file_put_contents($answer, $format);

            //PUSH REMOTE
            push_remote($answer);

        }
    }

    // 生成臨時訪問TOKEN
    else if ($command[0] == 'token') {
        echo hash_token(NODE_SECRET, date('YmdHi'), '') . PHP_EOL;
    }


} else {
    // 判斷當前是HTTP訪問
    if (isset($_POST['ip'])) {
        $ip = trim($_POST['ip']);
        if (!preg_match('/^\d+\.\d+\.\d+\.\d+$/is', $ip)) {
            output_json('ip address valid!', 1, ['ip' => $ip]);
        }

        $result['service'] = '電信';
        $result['tcp'] = 200;
        $result['http'] = 200;
        $result['icmp'] = 200;

        if (!tcping_check($ip, 22)) {
            $result['tcp'] = 500;
        }
        if (!tcping_check($ip, 80)) {
            $result['http'] = 500;
        }
        if (!icmp_ping($ip)) {
            $result['icmp'] = 500;
        }

        $result['ip'] = $ip;
        output_json('ok', 0, $result);
    }
    output_json('valid request!', 2);
}

