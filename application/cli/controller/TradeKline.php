<?php
namespace app\cli\controller;
use app\common\model\TradeConfig;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

//use think\console\Input;
//use think\console\Output;
//use think\console\Command;

/**
 *交易对K线图数据生成
 */
class TradeKline
{
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

    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'TradeKline';
        $this->worker->onWorkerStart = function ($worker) {
            while (true){
                $this->doRun();
            }
        };
        Worker::runAll();
    }

    /**
     * 交易对K线图数据生成，每分钟执行一次
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function doRun($worker_id=0)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $this->symbol_list = explode(',', TradeConfig::get_value('trade_kline_symbols', 'btcusdt,ethusdt,eosusdt,ltcusdt,etcusdt'));

        Log::write("交易对K线图:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $runNum = 1;
        while ($runNum < 2000) {
            $runNum++;

            foreach ($this->symbol_list as $key => $value) {
                foreach ($this->time_list as $key1 => $value1) {
                    try {
                        Db::startTrans();

                        $t1 = microtime(true);
                        //throw new Exception('暂无任务1'); //暂无任务
                        $r = \app\common\model\TradeKline::create_kline($value, $key1);
                        $t2 = microtime(true);
                        $cost = round($t2-$t1,3);
                        if ($r['code'] == SUCCESS) {

                        }
                        else {
                            throw new Exception($r['message']);
                        }

                        Db::commit();
                    }
                    catch(Exception $e) {
                        @Db::rollback();

                        $msg = $e->getMessage();
                        Log::write("交易对K线图:定时任务:".$msg, 'INFO');
                    }
                }
            }
            sleep(1);
        }
        Log::write("交易对K线图:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
        sleep(1);
    }
}