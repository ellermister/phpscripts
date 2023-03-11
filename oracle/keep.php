<?php
declare(ticks=1);
define("WHILE_MINUTE", 5);

pcntl_signal(SIGINT, function(){
   exit("Get signal SIGINT and exit\n");
});


function testNetwork()
{
    echo "[*] test network\n";
    $bigFileUrl = "http://lg.hkg.hosthatch.com/100MB.test";

    $startTime = time();
    while (1) {
        if (time() - $startTime > 60 * WHILE_MINUTE) {
            echo "[+] test network\n";
            break;
        }

        try {
            if ($stream = fopen($bigFileUrl, "r")) {
                $offset = 0;
                $fh = fopen("test.tmp", "w+");
                $chunkSize = 1024 * 1024 * 2;
                while (1) {
                    $data = stream_get_contents($stream, $chunkSize);

                    if ($data == false) {
                        break;
                    }
                    fwrite($fh, $data);
                    echo "[*] write 2M for network\r";
                    //sleep(2);

                    $offset += $chunkSize;
                }
                echo "\n";

                fclose($fh);
                fclose($stream);
                unlink("test.tmp");
            } else {
                echo "[-] test network\n";
            }
        } catch (Throwable $t) {
            fclose($fh);
            fclose($stream);
            unlink("test.tmp");
            echo "[-] test network\n";
        }
    }
}

function testCPU()
{
    echo "[*] test CPU\n";
    $startTime = time();
    while (1) {
        if (time() - $startTime > 60 * WHILE_MINUTE) {
            echo "[+] test CPU\n";
            break;
        }

        acos(rand());
    }
}

function getSystemMemInfo()
{
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = [];
    foreach ($data as $line) {
        @list($key, $val) = explode(":", $line);
        if (!$key || empty($key)) {
            continue;
        }
        if (is_string($val) && preg_match("(\d+)", $val, $match)) {
            $meminfo[$key] = intval($match[0]);
        } else {
            $meminfo[$key] = trim($val);
        }
    }
    return $meminfo;
}

function testMemory()
{
    echo "[*] test Memory\n";
    $bigString = "";
    $startTime = time();
    while (1) {
        if (time() - $startTime > 60 * WHILE_MINUTE) {
            echo "\n[+] test memory finish!\n";
            break;
        }
        $systemMemory = getSystemMemInfo();

        $maxMemory = $systemMemory["MemTotal"];
        $MemFree = $systemMemory["MemFree"];
        $memUsed = $maxMemory - $MemFree;
        $usedPercentage = round(($memUsed / $maxMemory) * 100, 2);
        $used = memory_get_usage();

        echo "[*] memory_get_usage $used; max memory: $maxMemory; system used: $memUsed; percentage: $usedPercentage%\r";
        if ($usedPercentage < 40) {
            $bigString += openssl_random_pseudo_bytes(2048);
        }
    }

    unset($bigString);
}

while (1) {
    $startTime = time();
    testNetwork();
    testCPU();
    testMemory();

    $endTime = time();

    echo sprintf("[+] take times: %d\n", $endTime - $startTime);
    sleep(WHILE_MINUTE * 60);
}
