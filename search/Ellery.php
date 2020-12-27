<?php
/**
 * Created by PhpStorm.
 * User: ellermister
 * Date: 2020/12/15
 * Time: 12:23
 */
include 'vendor/autoload.php';
use Swoole\Process;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Elasticsearch\Common\Exceptions\Forbidden403Exception;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\ClientBuilder;

class Ellery
{
    /**
     * 文件长度
     * @var int
     */
    protected $length = 0;

    /**
     * 单个进程处理长度
     * @var float|int
     */
    protected $singleProcessLength = 0;

    /**
     * 最大进程数
     * @var int
     */
    protected $maxProcess = 1;

    /**
     * 文件名
     * @var
     */
    protected $filename;

    /**
     * ES索引名
     * @var
     */
    protected $esIndex;

    /**
     * 单次发送
     * @var int
     */
    protected $singleSend = 3000;

    /**
     * 开始时间
     * @var int
     */
    protected $beginTime = 0;

    /**
     * 格式化数据时回调
     * @var
     */
    protected $formatCall;

    /**
     * 错误重试次数
     * @var int
     */
    protected $errorNum = 10;

    /**
     * 总数计数器
     * @var __anonymous@1642
     */
    protected $atomic;

    /**
     * Es HOST
     * @var
     */
    protected $esHost;

    /**
     * 任务类型
     * 1:检查
     * 2:发送数据
     */
    const QUEUE_TYPE_CHECK = 1;
    const QUEUE_TYPE_SEND = 2;


    protected function __construct($filename, $esIndex, $formatCall,$esHost, $maxProcess = 1, $singleSend = 3000)
    {
        $this->length = $this->length($filename);
        $this->singleProcessLength = ceil($this->length / $maxProcess);
        $this->maxProcess = $maxProcess;
        $this->filename = $filename;
        $this->singleSend = $singleSend;
        $this->beginTime = time();
        $this->formatCall = $formatCall;
        $this->esIndex = $esIndex;
        // 初始化计数器
        $this->atomic = new class($maxProcess){
            /**
             * @var Swoole\Atomic[]
             */
            protected $atomic;
            protected $sum;
            public function __construct($maxProcess)
            {
                foreach (range(0,$maxProcess -1)  as $tid){
                    $this->atomic[] = new Swoole\Atomic();
                }
                $this->sum = new Swoole\Atomic();
            }

            /**
             * 添加处理总数到进程
             *
             * @param $tid
             * @param $num
             */
            public function addCount($tid,$num){
                $this->atomic[$tid]->add($num);
                $this->sum->add($num);
            }

            /**
             * 获取进程处理总数
             *
             * @param $tid
             * @return mixed
             */
            public function getCount($tid){
                return $this->atomic[$tid]->get();
            }

            /**
             * 获取所有进程处理总数
             * @return mixed
             */
            public function sum()
            {
                return $this->sum->get();
            }

        };

        //  es host
        if(is_string($esHost)){
            $this->esHost = [$esHost];
        }else if(is_array($esHost)){
            $this->esHost = $esHost;
        }
    }

    /**
     * 产生一个入库任务
     *
     * @param $filename
     * @param $esIndex
     * @param $formatCall
     * @param $esHost
     * @param int $maxProcess
     * @param int $singleSend
     * @return Ellery
     */
    public static function make($filename, $esIndex, $formatCall, $esHost, $maxProcess = 1, $singleSend = 3000)
    {
        return new self($filename, $esIndex, $formatCall, $esHost, $maxProcess, $singleSend);
    }


    /**
     * 请求ES建立文档
     *
     * @param array $_data
     * @param $tid
     * @param Client $client
     */
    protected function requestDoc(array &$_data, $tid,Client &$client)
    {
        $params['index'] = $this->esIndex;
        foreach ($_data as $raw) {
            if($this->formatCall instanceof Closure){
                $body = [];// 字段=>值 的键值对
                // 这里参数必须引用传递
                call_user_func_array($this->formatCall, [&$raw, &$body]);
                if(count($body) && !isset($body[0])){
                    $body = [$body];
                }
                // 有数据则入
                foreach ($body as $_body){
                    $params['body'][] = array(
                        'index' => array(    #注意create也可换成index
                            '_index' => $this->esIndex,
                        ),
                    );
                    $params['body'][] = $_body;
                    unset($_body);
                }
                unset($body);
            }
            unset($raw);
        }

        // 准备写入ES
        $errorNum  = 0;
        $res = null;
        while ($errorNum  < $this->errorNum){
            try{
                $res = $client->bulk($params);
                if (!(isset($res['errors']) && $res['errors'] == false)) {
                    $errorNum++;
                    sleep($errorNum);
                }else{
                    break;
                }
            }catch (NoNodesAvailableException $exception) {
                $errorNum++;
                echo sprintf("tid:%s es client recreated! error num:%s \n", $tid, $errorNum);
                $client = $this->getEs();
                sleep($errorNum);
            } catch (Forbidden403Exception $exception) {
                $errorNum++;
                echo sprintf("tid %s,error: %s  ,error num:%s \n", $tid, $exception->getMessage(), $errorNum);
                sleep($errorNum);
            } catch (BadRequest400Exception $exception) {
                $errorNum++;
                echo sprintf("tid %s,error %s, error num:%s \n", $tid, $exception->getMessage(), $errorNum);
                sleep($errorNum);
            }
        }
        if (!(isset($res['errors']) && $res['errors'] == false)) {
            file_put_contents('error_' . $tid . '.txt', json_encode($_data) . PHP_EOL, FILE_APPEND);
            echo '插入失败，数据已存放到 error.txt';
        }
        unset($res);
        unset($params);
        unset($errorNum);
    }

    /**
     * 获得ES客户端链接
     *
     * @return Client
     */
    protected function getEs()
    {
        $ClientBuilder = new ClientBuilder();
        return $ClientBuilder->setHosts($this->esHost)->setRetries(3)->build();
    }

    /**
     * 获取文件长度
     *
     * @param $filename
     * @return bool|int
     */
    function length($filename)
    {
        $handle = fopen($filename, "r");
        $currentPos = ftell($handle);

        fseek($handle, 0, SEEK_END);
        $length = ftell($handle);
        fseek($handle, $currentPos);
        fclose($handle);
        // $length 文件总长度
        return $length;
    }

    /**
     * 线程负责读取的内容
     *
     * @param $filename
     * @param $index
     * @param $singleProcessLength
     * @return Generator
     */
    function processRead($filename, $index, $singleProcessLength)
    {
        $fh = fopen($filename, 'r');

        $beginPos = $index * $singleProcessLength;
        //结束位置=线程序列*线程处理数据长度+线程处理数据 - 1 (长度转指针，实际结束指针小于结束长度)
        $endPos = $index * $singleProcessLength + $singleProcessLength - 1;

        fseek($fh, $beginPos);
//        echo '线程:' . $index . '，起始位置:' . $beginPos . '，结束位置:' . $endPos . PHP_EOL;
        //移动到上个\n 以便首次顺利获取整行内容
        while ($beginPos !=0) {
            if (fread($fh, 1) == "\n" || ftell($fh) >= $endPos) {
                break;
            }
        }
//        echo '线程:' . $index . '，移动完毕!!!!! 当前位置:'.ftell($fh). PHP_EOL;

        //整行读取数据
        //结束时位置超过预计结束位置是正常状况，fgets 读取一整行内容
        //预计结束位置可能在行内，所以产生不同结果。
        while (ftell($fh) <= $endPos && !feof($fh)) {
            yield $raw = fgets($fh);
        }
        //echo '进程' . $index . '结束时 指针位置:' . ftell($fh) . ', 应该到:' . $endPos . PHP_EOL;
        fclose($fh);
    }

    /**
     * 检查数据
     * @param $index
     */
    protected function checkData($index)
    {
        $line = 0;
        foreach ($this->processRead($this->filename, $index, $this->singleProcessLength) as $value) {
            // $value为每一行的内容，处理后释放
            unset($value);
            $line++;
        }
        $this->atomic->addCount($index,$line);
    }

    /**
     * 存储数据
     *
     * @param $index
     */
    protected function storeData($index)
    {
        $begin = time();
        $chan = new Swoole\Coroutine\Channel(2);
        Swoole\Coroutine::create(function () use ($chan, $index) {
            $_data = [];
            foreach ($this->processRead($this->filename, $index, $this->singleProcessLength) as $value) {
                // $value为每一行的内容，处理后释放
                $_data[] = $value;
                if (count($_data) >= $this->singleSend) {
                    $chan->push($_data);
                    unset($_data);
                    $_data = [];
                }
                unset($value);
            }
            $chan->push($_data);
            unset($_data);
        });

        Swoole\Coroutine::create(function () use ($chan, $begin, $index) {
            $esClient = $this->getEs();
            while (1) {
                $_data = $chan->pop();
                if ($_data) {
                    $_b = time();
                    $this->requestDoc($_data, $index, $esClient);
                    $_e = time();
                    $this->atomic->addCount($index, count($_data));
                    echo sprintf("time:%s current count:%s time:%s s, sum:%s/%s \r",
                        gmdate("H:i:s", time() - $this->beginTime), count($_data), $_e - $_b, number_format($this->atomic->sum()), number_format($this->atomic->getCount($index)));
                    // free vars
                    unset($_e);
                    unset($_b);
                } else {
                    break;
                }
                unset($_data);
            }
            $end = time();
            echo sprintf("\n\n\ntime:%s", $end - $begin);
        });
    }


    /**
     *
     * 开始任务
     * @param int $queueType
     */
    public function startQueue($queueType = self::QUEUE_TYPE_CHECK)
    {
        for ($index = 0; $index < $this->maxProcess; $index++) {
            $process = new \Swoole\Process(function () use ($index,$queueType) {
                echo "< process $index created!\n";
                Swoole\Coroutine::create(function ()use($index,$queueType){
                    if($queueType == self::QUEUE_TYPE_CHECK){
                        $this->checkData($index);
                    }else if($queueType == self::QUEUE_TYPE_SEND){
                        $this->storeData($index);
                    }
                    sleep(1);
                });
                Swoole\Event::wait();
            });
            $process->start();
        }

        for ($n = $this->maxProcess; $n--;) {
            $status = Process::wait(true);
            echo "Recycled #{$status['pid']}, code={$status['code']}, signal={$status['signal']}" . PHP_EOL;
        }
        echo 'Parent #' . getmypid() . ' exit' . PHP_EOL;
        $endTime = time();
        echo sprintf("done! file line: %s time:%s\n", $this->atomic->sum(), $endTime - $this->beginTime);
    }
}