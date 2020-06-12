<?php
namespace app\cli\controller;
use app\common\model\TradeConfig;
use think\worker\Server;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\Connection\AsyncTcpConnection;
use \Workerman\Autoloader;
use GatewayWorker\Gateway;
use think\Log;
use think\Db;
use think\Exception;

//use think\console\Input;
//use think\console\Output;
//use think\console\Command;

//心跳间隔5秒
define('HEARTBEAT_TIME', 5);

/**
 *交易对K线图数据生成-火币
 * 用于实时接收火币K线数据
 */
class TradeKlineHuobi
{
    protected $flag = true;//是否正式环境

    protected $huobi_host = '';

    protected $server_host = '';

    protected $host = '';

    protected $local_host = 'Websocket://0.0.0.0:9999';// 代理监听本地9999端口

    private $time_list = [
        '1min'=>60, //1分钟
        '5min'=>300,//5分钟
        '15min'=>900,//15分钟
        '30min'=>1800,//30分钟
        '60min'=>3600,//1小时
        '1day'=>86400,//1天
        '1week'=>604800,//1周
        '1mon'=>2592000, //1月
        //'1year'=>31536000, //1年
    ];

    private $symbol_list = [];

    private $all_cons = [];

    private $all_symbols = [];

    //private $all_dic = [];

    private $testip = array("192.168.10.234", "192.168.230.1");

    private $huobi_id = 0;//连接火币服务器的连接id，防止心跳把火币连接关闭

    private $reconnect_num = 0;//与火币服务器的重连次数，超过一定次数重启Worker，目前是10次，windows下无法重启

    private $async_message_time = 0;//与火币服务器的消息交互时间，超过一定时间没有消息往来重启Worker，目前是300s，windows下无法重启

    public function index()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $this->huobi_host = TradeConfig::get_value('trade_kline_huobi_url', 'ws://api.huobi.pro:443/ws');
        $this->server_host = TradeConfig::get_value('trade_kline_server_url', '47.57.127.13:80');

        $host= gethostname();
        $ip = gethostbyname($host);
        $this->host = $this->huobi_host;
        var_dump($ip);
        if (in_array($ip, $this->testip)) {//测试环境
            $this->flag = false;
            $this->host = $this->server_host;
        }
        if (empty($this->host)) {
            echo "服务器地址为空,无法启动\r\n";
            die();
        }
        if (!$this->flag) $this->host = 'ws://'.$this->host;
        //var_dump($this->flag);

        //启动worker之前先更新K线数据
        $info = "更新K线数据-start:".date('Y-m-d H:i:s');
        echo "\r\n ".$info;
        $this->saveLog("huobi", $info);

        $this->symbol_list = explode(',', TradeConfig::get_value('trade_kline_symbols', 'btcusdt,ethusdt,eosusdt,ltcusdt,etcusdt'));

        foreach ($this->symbol_list as $value) {
            $symbol = $value;
            foreach ($this->time_list as $k => $v) {
                $info = "create_kline:{$symbol}-{$k}";
                echo "\r\n ".$info;
                $this->saveLog("huobi", $info);
                $r = \app\common\model\TradeKline::create_kline($symbol, $k);
                if ($r['code'] == SUCCESS) {

                }
            }
        };
        $info = "更新K线数据-end:".date('Y-m-d H:i:s');
        echo "\r\n ".$info;
        $this->saveLog("huobi", $info);

        $info = "启动Worker-start:".date('Y-m-d H:i:s');
        echo "\r\n ".$info;
        $this->saveLog("huobi", $info);
        $this->worker = new Worker($this->local_host);
        $this->worker->count = 1;
        $this->worker->name = 'TradeKlineHuobi';
        $this->worker->onWorkerStart = function($worker)
        {
            $this->onWorkerStart($worker);
        };

        $this->worker->onMessage = function($connection, $data)
        {
            $this->onWorkerMessage($connection, $data);
        };

        $this->worker->onClose = function($connection)
        {
            //unset($this->all_cons[$connection->id]);
            //var_dump($this->all_cons);

            $c_id = $connection->id;
            /*foreach($this->all_cons as $k=>$v){
                if($v["sid"] == $c_id){
                    unset($this->all_cons[$k]);
                    break;
                }
            }*/

            if (array_key_exists($c_id, $this->all_cons)) {
                if (isset($this->all_cons[$c_id]["symbol"])) {
                    foreach ($this->all_cons[$c_id]["symbol"] as $key => $val) {
                        if (array_key_exists($val, $this->all_symbols)) {
                            if (array_key_exists($c_id, $this->all_symbols[$val])) unset($this->all_symbols[$val][$c_id]);
                        }
                    }
                }
                unset($this->all_cons[$c_id]);
            }
            $info = "\r\n connection close sid:".$c_id;
            echo $info;
            $this->saveLog("all", $info);
            echo var_export($this->all_cons, true);
        };

        // 当有链接事件时触发
        $this->worker->onConnect = function($connection)
        {
            //$this->saveLog("all", 'onConnect:'.print_r($connection, true));
            //$this->all_cons[$connection->id] = $connection;
            // 设置连接的onClose回调
            //$connection->onClose = function($connection)
            //{
            //    unset($this->all_cons[$connection->id]);
            //};

            $exists = 0;
            $c_id = $connection->id;
            $this->saveLog("all", 'onConnect,cid:'.$c_id);
            /*foreach($this->all_cons as $k=>$v){
                if($v["sid"]==$c_id){
                    $exists = 1;
                    break;
                }
            }
            if($exists == 0) $this->all_cons[] = array("sid"=>$c_id);*/

            if (!array_key_exists($c_id, $this->all_cons)) $this->all_cons[$c_id] = ["sid"=>$c_id];
            $connection->send(json_encode(array('Welcome to XRPCash, id:'.$c_id)));
        };

        Worker::runAll();
    }

    function onWorkerStart($worker)
    {
        $info = "启动Worker-start success:".date('Y-m-d H:i:s');
        echo "\r\n ".$info;
        $this->saveLog("huobi", $info);
        // 进程启动后设置一个每秒运行一次的定时器
        Timer::add(1, function()use($worker){
            $time_now = time();
            $this->saveLog("all", '心跳计时器,count:'.count($worker->connections));
            foreach($worker->connections as $connection) {
                if ($connection->id == $this->huobi_id) {
                    $this->saveLog("all", '心跳计时器,huobi_id:'.$this->huobi_id);
                    continue;
                }
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $time_now;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔*2，则认为客户端已经下线，关闭连接
                if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME * 2) {
                    $this->saveLog("all", '心跳计时器,心跳超时,cid:'.$connection->id.',now:'.date('Y-m-d H:i:s', $time_now).',lastMessageTime:'.date('Y-m-d H:i:s', $connection->lastMessageTime));
                    $connection->close();
                    //unset($this->all_cons[$connection->id]);
                }
            }
            if ($this->reconnect_num >= 10) {//与火币的连接断开重连超过10次，重启Worker
                $info = "与火币的连接断开重连超过10次,重启Worker:".date('Y-m-d H:i:s');
                echo "\r\n".$info;
                $this->saveLog("huobi", $info);
                Worker::stopAll();
            }
            if ($time_now - $this->async_message_time > 300) {
                $info = "与火币的连接超过300s没有消息交互,重启Worker:".date('Y-m-d H:i:s');
                echo "\r\n".$info;
                $this->saveLog("huobi", $info);
                Worker::stopAll();
            }
        });

        //var_dump(1);
        $info = "连接到服务器:{$this->host}";
        echo "\r\n".$info;
        $this->saveLog("huobi", $info);
        // 异步建立一个到火币服务器的连接
        $con = new AsyncTcpConnection($this->host);
        //var_dump(2);
        if ($this->flag) {//正式环境
            $con->transport = 'ssl';
        }

        //var_dump(3);
        $con->onConnect = function($con)
        {
            $this->onAsyncConnect($con);
        };

        // 当服务器连接发来数据时，转发给对应客户端的连接
        $con->onMessage = function($con, $message) use($worker)
        {
            $this->onAsyncMessage($con, $message, $worker);
        };

        $con->onError = function($con, $err_code, $err_msg)
        {
            var_dump(6);
            echo "$err_code, $err_msg";
            $info = "Async onError err_code:{$err_code},err_msg:{$err_msg}";
            echo "\r\n ".$info;
            $this->saveLog("huobi", $info);
        };

        $con->onClose = function($con)
        {
            $info = "Async onClose";
            echo "\r\n ".$info;
            $this->saveLog("huobi", $info);
            $this->reconnect_num++;//重连次数+1

            //重连之前先更新K线数据
            $info = "更新K线数据-重连之前-start:".date('Y-m-d H:i:s');
            echo "\r\n ".$info;
            $this->saveLog("huobi", $info);
            foreach ($this->trade_list as $value) {
                $symbol = $value;
                foreach ($this->time_list as $k => $v) {
                    $info = "create_kline:{$symbol}-{$k}";
                    echo "\r\n ".$info;
                    $this->saveLog("huobi", $info);
                    $r = \app\common\model\TradeKlineKline::create_kline($symbol, $k);
                    if ($r['code'] == SUCCESS) {

                    }
                }
            };
            $info = "更新K线数据-重连之前-end:".date('Y-m-d H:i:s');
            echo "\r\n ".$info;
            $this->saveLog("huobi", $info);

            // 如果连接断开，则在1秒后重连
            $con->reConnect(1);
        };

        //var_dump(4);
        // 执行异步连接
        $con->connect();
        //var_dump(5);
    }

    function onWorkerMessage($connection, $data)
    {
        // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
        $connection->lastMessageTime = time();
        $data = json_decode($data, true);
        var_dump($data);
        $c_id = $connection->id;
        $valid = 0;
        if(isset($data['pong'])) {//客户端返回心跳pong
            $valid = 1;
            $connection->lastMessageTime = time();
            //$connection->send(gzencode(json_encode(array('pong success'))));
            $info = "\r\n cid ".$connection->id." pong success";
            echo $info;
            $this->saveLog("all", $info);
            $connection->send(json_encode(array('pong success')));
        }
        else if (isset($data['sub'])) {
            //$sub = $data['sub'];
            //$this->all_cons[$connection->id] = $connection;
            //$this->all_dic[$sub][$connection->id] = $connection->id;
            //var_dump($this->all_cons);
            //$connection->send(gzencode(json_encode(array('sub success'))));
            //$connection->send(json_encode(array('sub success')));

            if(isset($data["id"])){
                $valid = 1;

                $symbol = $data["sub"];

                /*foreach($this->all_cons as $k=>$v){

                    if($v["sid"]==$c_id){
                        $exist_symbol = 0;
                        if(isset($this->all_cons[$k]["symbol"])){
                            foreach($this->all_cons[$k]["symbol"] as $k1=>$v1){
                                if($v1==$symbol){
                                    $exist_symbol = 1;
                                    break;
                                }
                            }
                        }
                        if($exist_symbol == 0){
                            $this->all_cons[$k]["symbol"][] = $symbol;
                        }
                        break;
                    }
                }*/

                if (!array_key_exists($c_id, $this->all_cons)) {
                    $this->all_cons[$c_id] = ["sid"=>$c_id,"symbol"=>[$symbol]];
                }
                else {
                    $this->all_cons[$c_id]["symbol"][] = $symbol;
                }
                if (!array_key_exists($symbol, $this->all_symbols)) {
                    $this->all_symbols[$symbol][$c_id] = $c_id;
                }
                else {
                    if (!array_key_exists($c_id, $this->all_symbols[$symbol])) $this->all_symbols[$symbol][$c_id] = $c_id;
                }
                $connection->send(json_encode(['Receive sid:'.$c_id." symbol:".$symbol. " sub:".$data["sub"]]) );
            }
        }
        else if (isset($data['unsub'])) {
            //$sub = $data['unsub'];
            //$this->all_cons[$connection->id] = $connection;
            //$this->all_dic[$sub][$connection->id] = $connection->id;
            //unset($this->all_dic[$sub][$connection->id]);
            //var_dump($this->all_cons);
            //$connection->send(gzencode(json_encode(array('unsub success'))));
            //$connection->send(json_encode(array('unsub success')));
            if(isset($data["id"])){
                $valid = 1;

                $symbol = $data["unsub"];

                /*foreach($this->all_cons as $k=>$v){

                    if($v["sid"]==$c_id){
                        $exist_symbol = 0;
                        $exist_key = -1;
                        if(isset($this->all_cons[$k]["symbol"])){
                            foreach($this->all_cons[$k]["symbol"] as $k1=>$v1){
                                if($v1==$symbol){
                                    $exist_symbol = 1;
                                    $exist_key = $k1;
                                    break;
                                }
                            }
                        }
                        if($exist_symbol == 1){
                            unset($this->all_cons[$k]["symbol"][$exist_key]);
                        }
                        break;
                    }
                }*/

                if (array_key_exists($c_id, $this->all_cons)) {
                    if (isset($this->all_cons[$c_id]["symbol"])) {
                        foreach ($this->all_cons[$c_id]["symbol"] as $key => $val) {
                            if ($symbol == $val) unset($this->all_cons[$c_id]["symbol"][$key]);
                        }
                    }
                }
                if (array_key_exists($symbol, $this->all_symbols)) {
                    if (array_key_exists($c_id, $this->all_symbols[$symbol])) unset($this->all_symbols[$symbol][$c_id]);
                }
                $connection->send(json_encode(['Receive sid:'.$c_id." symbol:".$symbol. " unsub:".$data["unsub"]]) );
            }
        }
        else {
            //$connection->send(gzencode(json_encode(array('undefind message'))));
            $connection->send(json_encode(array('undefind message')));
        }
        if($valid == 0) $connection->close();
        echo "\r\n  onMessage:";
        echo var_export($data, true);
        echo var_export($this->all_cons, true);
    }

    function onAsyncConnect($con) {

        $this->async_message_time = time();
        $info = "连接到服务器:{$this->host},成功";
        echo "\r\n".$info;
        $this->saveLog("huobi", $info);

        $info = "开始订阅K线数据";
        echo "\r\n".$info;
        $this->saveLog("huobi", $info);
        //$this->saveLog("huobi", 'onAsyncConnect:'.print_r($con, true));
        $this->saveLog("huobi", 'onAsyncConnect,cid:'.$con->id.',reconnect_num:'.$this->reconnect_num);
        $make = explode(',', TradeConfig::get_value('trade_kline_symbols', 'btcusdt,ethusdt,eosusdt,ltcusdt,etcusdt'));
        $this->huobi_id = $con->id;
        foreach ($make as $key => $value) {
            $symbol = $value;
            foreach ($this->time_list as $k => $v) {
                $info = "sub:{$symbol}-{$k}";
                echo "\r\n".$info;
                $this->saveLog("huobi", $info);
                $data = json_encode([                         //行情
                    'sub' => "market." . $symbol . ".kline." . $k,
                    'id' => "id" . time(),
                    'freq-ms' => 5000
                ]);
                $con->send($data);
            }
        }
        /*foreach ($this->trade_list as $key => $value) {
            $symbol = $key;
            foreach ($this->time_list as $k => $v) {
                echo "sub:{$symbol}-{$k}\r\n";
                $data = json_encode([                         //行情
                    'sub' => "market." . $symbol . ".kline." . $k,
                    'id' => "id" . time(),
                    'freq-ms' => 5000
                ]);
                $con->send($data);
            }
        };*/
    }

    function onAsyncMessage($con, $message, $worker)
    {
        //var_dump($data);
        $data = json_decode($message, true);
        if (!$data) {//说明采用了GZIP压缩
            $data = gzdecode($message);
            $this->saveLog("huobi", $data);
            $data = json_decode($data, true);
        }
        else {
            $this->saveLog("huobi", $message);
        }
        //$data = gzdecode($data);
        //$data = json_decode($data, true);
        if(isset($data['ping'])) {
            $this->async_message_time = time();
            var_dump($data);
            $con->send(json_encode([
                "pong" => $data['ping']
            ]));

            // 给客户端心跳
            /*foreach($worker->connections as $conn)  //如果是websock协议的话 这里就可以这样发给客户端了
            {
                $conn->send(json_encode($data));
            }*/
            foreach($this->all_cons as $kk=>$vv){
                if (array_key_exists($vv["sid"], $worker->connections)) {
                    $info = "\r\n sid ".$vv["sid"]." send ping";
                    echo $info;
                    $this->saveLog("all", $info);

                    //$conn->send(json_encode($data));
                    $worker->connections[$vv["sid"]]->send(json_encode($data));
                }
                else {
                    unset($this->all_cons[$kk]);
                }
            }
        } else if (isset($data['ch'])) {
            $this->async_message_time = time();
            $info = "接收到推送,ch:{$data['ch']}";
            echo "\r\n".$info;
            $this->saveLog("huobi", $info);
            //Log::write(print_r($data, true), 'INFO');
            //var_dump($data);

            $symbol = $data["ch"];
            $info = "\r\n  on mess size:".sizeof($this->all_cons)." conn-size: ".sizeof($worker->connections)." symbol:".$symbol;
            echo $info;
            $this->saveLog("all", $info);
            $pieces = explode(".", $data['ch']);
            switch ($pieces[2]) {
                case "kline":              //行情图
                    $market = $pieces[1];  //火币对
                    if (in_array($market, $this->symbol_list)) {
                        $period = $pieces[3];
                        $tick = $data['tick'];
                        //tick 说明
                        //"tick": {
                        //  "id": K线id,
                        //  "amount": 成交量,
                        //  "count": 成交笔数,
                        //  "open": 开盘价,
                        //  "close": 收盘价,当K线为最晚的一根时，是最新成交价
                        //  "low": 最低价,
                        //  "high": 最高价,
                        //  "vol": 成交额, 即 sum(每一笔成交价 * 该笔的成交量)
                        //}
                        $id = $tick['id'];
                        $where = [
                            'period'=>$period,
                            'symbol'=>$market,
                            'add_time'=>$id,
                        ];
                        $find1 = \app\common\model\TradeKline::where($where)->order('id', 'desc')->find();
                        if ($find1) {//记录已存在，更新已有记录
                            if ($find1['open_price'] != $tick['open'] ||
                                $find1['close_price'] != $tick['close'] ||
                                $find1['high_price'] != $tick['high'] ||
                                $find1['low_price'] != $tick['low'] ||
                                $find1['amount'] != $tick['amount'] ||
                                $find1['count'] != $tick['count'] ||
                                $find1['vol'] != $tick['vol']) {//没有数据变化不做更新
                                $update_list[] = [
                                    'id'=>$find1['id'],
                                    'open_price'=>number_format($tick['open'],6,".",""),
                                    'close_price'=>number_format($tick['close'],6,".",""),
                                    'high_price'=>number_format($tick['high'],6,".",""),
                                    'low_price'=>number_format($tick['low'],6,".",""),
                                    'amount'=>number_format($tick['amount'],6,".",""),
                                    'count'=>number_format($tick['count'],6,".",""),
                                    'vol'=>number_format($tick['vol'],6,".",""),
                                    'update_time'=>time(),
                                ];
                                $kline = new \app\common\model\TradeKline;
                                $res2 = $kline->isUpdate()->saveAll($update_list);
                                if (empty($res2)) {
                                    var_dump(lang('更新记录失败-2').'-in line:'.__LINE__);
                                    //throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                                }
                            }
                        }
                        else {
                            $add_list[] = [
                                'period'=>$period,
                                'symbol'=>$market,
                                'open_price'=>number_format($tick['open'],6,".",""),
                                'close_price'=>number_format($tick['close'],6,".",""),
                                'high_price'=>number_format($tick['high'],6,".",""),
                                'low_price'=>number_format($tick['low'],6,".",""),
                                'amount'=>number_format($tick['amount'],6,".",""),
                                'count'=>number_format($tick['count'],6,".",""),
                                'vol'=>number_format($tick['vol'],6,".",""),
                                'ch'=>$data['ch'],
                                'add_time'=>$id,
                                'update_time'=>time(),
                            ];
                            $kline = new \app\common\model\TradeKline;
                            $res1 = $kline->saveAll($add_list);
                            if (empty($res1)) {
                                var_dump(lang('插入记录失败').'-in line:'.__LINE__);
                                //throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                            }
                        }
                    }

                    break;
            }

            $time_1 = microtime(true);
            /*foreach($this->all_cons as $kk=>$vv){
                if (array_key_exists($vv["sid"], $worker->connections)) {
                    $send=0;
                    if(isset($vv["symbol"])){
                        foreach($vv["symbol"] as $kkk=>$vvv ){
                            if( $vvv==$symbol ){
                                $info = " symbol ".$vvv." | ch ".$data["ch"]." sid ".$vv["sid"]." send \r\n";
                                echo $info;
                                $this->saveLog("all", $info);

                                $send=1;
                                //$conn->send(json_encode($data));
                                $worker->connections[$vv['sid']]->send(json_encode($data));
                                break;
                            }
                        }
                    }
                    if($send==0) {
                        $info = " symbol empty | ch ".$data["ch"]." sid ".$vv["sid"]."  not send \r\n";
                        echo $info;
                        $this->saveLog("all", $info);
                    }
                    //if($send==1) break;
                }
                else {
                    unset($this->all_cons[$kk]);
                }
            }*/

            if (array_key_exists($symbol, $this->all_symbols)) {
                foreach ($this->all_symbols[$symbol] as $key => $val) {
                    $info = " symbol ".$symbol." | ch ".$data["ch"]." sid ".$val." send \r\n";
                    echo $info;
                    $this->saveLog("all", $info);

                    $worker->connections[$val]->send(json_encode($data));
                }
            }
            $time_2 = microtime(true);
            $cost = $time_2 - $time_1;
            if ($cost > 1) {
                $info = " symbol ".$symbol." | ch ".$data["ch"]." cost {$cost} \r\n";
                echo $info;
                $this->saveLog("all", $info);
            }

            /*if (array_key_exists($data['ch'], $this->all_dic)) {
                foreach ($this->all_dic[$data['ch']] as $value) {
                    if (array_key_exists($value, $this->all_cons)) {
                        $conn = $this->all_cons[$value];
                        $conn->send(gzencode(json_encode($data)));
                    }
                }
            }*/
        }
        else {
            echo "undefind message\r\n";
            var_dump($data);
        }
    }

    function saveLog($symbol, $msg){
        $dir =  __DIR__ ."/logs";
        if( !file_exists($dir) ) mkdir($dir, 0777);
        $today = date('Ymd');
        $file_path =$dir."/a-".$symbol."-".$today.".log";
        $handle = fopen($file_path, "a+");
        @fwrite($handle, date("H:i:s"). $msg . "\r\n");
        @fclose($handle);
    }
}