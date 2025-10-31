<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / Event.php    [ 2023/4/12 16:58 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server;

use Swoole\Timer;
use Swoole\Coroutine;
use zhanshop\App;
use zhanshop\cache\CacheManager;
use zhanshop\console\command\Server;
use zhanshop\database\DbManager;
use zhanshop\Log;
use zhanshop\server\Request;
use zhanshop\server\Response;
use zhanshop\util\Queue;
use zhanshop\util\WebHook;

class Event
{
    /**
     * 当前工作进程的server对象
     * @var \Swoole\Server
     */
    public mixed $server;

    public string $servName = 'index';

    /**
     * 当前所有的服务名称
     * @param array $servNames
     */
    public $servNames = [];

    /**
     * 路由组
     * @var string
     */
    public const ON_INITIAL = 'initial';
    public const ON_START = 'start';
    public const ON_WORKER_START = 'workerStart';
    public const ON_WORKER_STOP = 'workerStop';
    public const ON_WORKER_EXIT = 'workerExit';
    public const ON_WORKER_ERROR = 'workerError';
    public const ON_PIPE_MESSAGE = 'pipeMessage';
    public const ON_REQUEST = 'request';
    public const ON_RECEIVE = 'receive';
    public const ON_CONNECT = 'connect';
    public const ON_HAND_SHAKE = 'handshake';
    public const ON_OPEN = 'open';
    public const ON_MESSAGE = 'message';
    public const ON_CLOSE = 'close';
    public const ON_TASK = 'task';
    public const ON_FINISH = 'finish';
    public const ON_SHUTDOWN = 'shutdown';
    public const ON_PACKET = 'packet';
    public const ON_MANAGER_START = 'managerStart';
    public const ON_MANAGER_STOP = 'managerStop';
    public const ON_BEFORE_RELOAD = 'onBeforeReload';
    public const ON_BEFORE_SHUTDOWN = 'onBeforeShutdown';
    public const ON_AFTER_RELOAD = 'onAfterReload';

    /**
     * 构造函数
     * @param mixed $server
     * @param array $servNames
     */
    public function __construct(mixed &$server, array $servNames)
    {
        $this->server = $server;
        $this->servNames = $servNames;
    }

    /**
     * 设置server对象
     * @param \Swoole\Server $server
     * @return void
     */
    public function setServer(mixed $server) :void{
        $this->server = $server;
    }

    /**
     * 获取server对象
     * @return \Swoole\Server
     */
    public function server() :mixed{
        return $this->server ?? null;
    }
    /**
     * 主进程启动回调
     * @param \Swoole\Server $server
     * @return void
     */
    public function onStart($server) :void{
        App::config()->loadIni();
        $msg = "启动工作进程数".($server->setting['worker_num']).', 任务工作进程'.($server->setting['task_worker_num']).', 环境'.swoole_version().' '.($_SERVER['APP_ENV'] ?? 'dev').'['.getmypid().']';
        Log::errorLog(SWOOLE_LOG_NOTICE, $msg);
        //App::make(Robot::class)->send($msg);
    }

    /**
     * Worker进程启动回调
     * @param \Swoole\Server $server
     * @param int $workerId
     * @return void
     */
    public function onWorkerStart($server, $workerId) :void{
        App::config()->loadIni();

        if($server->setting){
            if($workerId < $server->setting['worker_num']) {
                $msg = "工作进程启动, 进程".getmypid();
            }else{
                $msg = "任务进程启动, 进程".getmypid();
            }
            Log::errorLog(SWOOLE_LOG_DEBUG, $msg);
        }
        
        Timer::clearAll();// 清理掉所有定时器
        App::cleanAll(); // 清空销毁APP容器的所有对象
        gc_collect_cycles();
        App::log();
        App::make(CacheManager::class); // 缓存管理初始化
        App::make(DbManager::class); // 数据库管理初始化
        App::make(WebHandle::class)->setServers($this->servNames)->router(); // webServer处理
        App::make(Queue::class);
    }

    /**
     * Worker(退出后)进程终止时回调
     * @param \Swoole\Server $server
     * @param int $workerId
     * @return void
     */
    public function onWorkerStop($server, $workerId) :void{
        if(($server->setting['worker_num'] - 1) == $workerId){
            Coroutine::create(function () {
                $msg = 'server触发了停止';
                App::make(WebHook::class)->bridge($msg);
            });
        }
    }

    /**
     * Worker进程退出前回调
     * @param \Swoole\Server $server
     * @param int $workerId
     * @return void
     */
    public function onWorkerExit($server, $workerId) :void{
        Timer::clearAll();
        App::log()->write();
    }

    /**
     * Worker进程发生错误时回调
     * @param \Swoole\Server $server
     * @param int $worker_id
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     * @return void
     */
    public function onWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal) :void{
        // 使用 gdb 来跟踪 swoole 前，需要在编译时添加 --enable-debug 参数以保留更多信息
        // ulimit -c unlimited 开启核心转储文件
        // gdb php core || gdb php /tmp/core.1234 键入命令进入 gdb 调试程序
        // bt 紧接着输入 bt 并回车，就可以看到出现问题的调用栈
        // f 1 || f 0 可以通过键入 f 数字 来查看指定的调用栈帧
        // 将以上信息都贴在 issue 中
        $msg = '工作进程'.$worker_id.',所属进程'.$worker_pid.'退出码'.$exit_code.'退出信号'.$signal;
        Log::errorLog(SWOOLE_LOG_ERROR, $msg);
        foreach ($server->connections as $fd) {
            $server->close($fd, true);
        }
    }

    /**
     * Worker进程收到由 $server->sendMessage() 发送的 unixSocket 消息时会触发 onPipeMessage 事件。worker/task 进程都可能会触发 onPipeMessage 事件
     * @param \Swoole\Server $server
     * @param int $src_worker_id
     * @param mixed $message
     * @return void
     */
    public function onPipeMessage($server, $src_worker_id, $message) :void{
        try {
            $handler = $message[0][0];
            $method = $message[0][1];
            $param = $message[1];
            App::make($handler)->$method(...$param);
        }catch (\Throwable $e){
            Log::errorLog(SWOOLE_LOG_ERROR,'worker出错 '.$e->getMessage().PHP_EOL.'#@ '.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString().PHP_EOL);
        }
    }

    /**
     * http请求
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @param string $group
     * @return void
     */
    public function onRequest($request, $response) :void{
        $response->header('Server', 'zhanshop');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Headers', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE');
        $response->header('Access-Control-Max-Age', '3600');
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico' || $request->server['request_method'] == 'OPTIONS') {
            $response->end();
            return;
        }else if($request->server['request_uri'] == '/_status' && ($request->get['app_key'] ?? '') == App::config()->get('app.app_key')){
            $response->end(json_encode($this->server->stats()));
            return;
        }
        App::make(WebHandle::class)->httpDispatch($this->servName, $request, $response);
    }

    /**
     * 收到tcp数据
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return void
     */
    public function onReceive($server, $fd, $reactorId, $data) :void{
        if($data == "ping") return;
        // 接收到的数据过长
        $data = json_decode($data, true);
        if($data){
            $request = \Swoole\Http\Request::create([]);
            $request->fd = $fd;
            $clientInfo = $server->getClientInfo($fd);
            $request->server['remote_addr'] = $clientInfo['remote_ip'] ?? '-1';
            $request->server['request_uri'] = $data['uri'] ?? '/v1/index.index';
            $request->server['request_time'] = time();
            $request->server['request_method'] = 'TCP';
            foreach($data['header'] ?? [] as $k => $v){
                $request->header[$k] = $v;
            }
            $request->post = $data['body'] ?? [];
            $protocol = Server::TCP;
            $servRequest = new Request($protocol, $request);
            $servResponse = new Response($protocol, $server);
            $result = App::make(WebHandle::class)->dispatchtTcp('admin', $servRequest, $servResponse);
            if($result !== true){
                $server->send($fd, '{"uri":"error","header":[],"body":"'.$request->server['request_uri'].PHP_EOL.addslashes($result).'"}'."\r\n");
            }
        }
    }

    /**
     * 有新的连接进入时，在 worker 进程中回调
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @return void
     */
    public function onConnect($server, $fd, $reactorId) :void{
    }

    /**
     * WebSocket 客户端与服务器建立连接并完成握手后
     * @param \Swoole\Server $server
     * @param \Swoole\Http\Request $request
     * @return void
     */
    public function onOpen($server, $request) :void{
        App::make(WebHandle::class)->httpDispatch($this->servName, $request, null);
    }

    /**
     * WebSocket收到来自客户端的数据帧时会回调此函数
     * @param \Swoole\Server $server
     * @param $frame
     * @return void
     */
    public function onMessage($server, $frame) :void{
    }

    /**
     * TCP 客户端连接关闭后，在 Worker 进程中回调此函数
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @return void
     */
    public function onClose($server, $fd, $reactorId) :void{
    }

    /**
     * 接收到新的task任务
     * @param \Swoole\Server $server
     * @param $task
     * @return void
     */
    public function onTask($server, $task) :void{
        try{
            if($task->data == false || !is_array($task->data)){
                Log::errorLog(SWOOLE_LOG_ERROR,'投递的task必须是一个数组');
            }else{
                $obj = App::make($task->data[0][0]);

                $enableLog = $obj->enableLog ?? false;
                $taskId = 0;
                if($enableLog){
                    $taskId = App::database()->model("system_tasks")->insertGetId([
                        'task' => $task->data[0][0].'::'.$task->data[0][1],
                        'param' => json_encode($task->data[1], JSON_UNESCAPED_UNICODE),
                        'status' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                }

                if(isset($obj->taskId)){
                    $obj->taskId = $taskId;
                }

                $method = $task->data[0][1];
                $result = $obj->$method(...$task->data[1]);

                if($taskId) App::database()->model("system_tasks")->where(['id' => $taskId])->update(['status' => 2, 'result' => $result, 'update_time' => time()]);

            }
        }catch (\Throwable $e){
            Log::errorLog(SWOOLE_LOG_ERROR,'task出错 '.$e->getMessage().PHP_EOL.'#@ '.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString().PHP_EOL);
        }
    }

    /**
     * 处理异步任务的结果(此回调函数在worker进程中执行)
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param mixed $data
     * @return void
     */
    public function onFinish($server, int $task_id, $data) :void{

    }

    /**
     * 触发了正常停止
     * @param \Swoole\Server $server
     * @return void
     */
    public function onShutdown($server) :void{
        $pidFile = $server->setting['pid_file'] ?? '';
        if(file_exists($pidFile)) @unlink($pidFile);
    }

    /**
     * 接收到 UDP 数据包时回调此函数
     * @param \Swoole\Server $server
     * @param string $data
     * @param array $clientInfo
     * @return void
     */
    public function onPacket($server, $data, $clientInfo) :void{
        $server->sendto($clientInfo['address'], $clientInfo['port'], "1");
    }

    /**
     * 当管理进程启动时触发此事件
     * @param \Swoole\Server $server
     * @return void
     */
    public function onManagerStart($server) :void{
        //echo PHP_EOL.'['.date('Y-m-d H:i:s').'] ###[info]###'." 管理进程启动, 进程".getmypid();
    }

    /**
     * 当管理进程结束时触发
     * @param \Swoole\Server $server
     * @return void
     */
    public function onManagerStop($server) :void{
        $pidFile = $server->setting['pid_file'] ?? '';
        if(file_exists($pidFile)) @unlink($pidFile);
    }

    /**
     * Worker 进程 Reload 之前触发此事件，在 Manager 进程中回调
     * @param \Swoole\Server $server
     * @return void
     */
    public function onBeforeReload($server) :void{
    }

    /**
     * Worker 进程 Reload 之后触发此事件，在 Manager 进程中回调
     * @param \Swoole\Server $server
     * @return void
     */
    public function onAfterReload($server) :void{
    }

    /**
     * Server 正常结束前发生
     * @param \Swoole\Server $server
     * @return void
     */
    public function onBeforeShutdown($server) :void{
    }
}