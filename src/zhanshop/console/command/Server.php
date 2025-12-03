<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / HttpServer.php    [ 2023/4/12 15:43 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\command;

use Swoole\Coroutine;
use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\command\software\ScanPorts;
use zhanshop\console\Input;
use zhanshop\console\Output;
use zhanshop\console\ServerStatus;
use zhanshop\Log;
use zhanshop\Mutex;
use zhanshop\ProcessManager;
use zhanshop\server\Event;
use zhanshop\ShareData;
use zhanshop\Timer;

class Server extends Command
{
    public const TCP       = 1;
    public const UDP       = 2;
    public const HTTP      = 3;
    public const WEBSOCKET = 4;
    public const HTTP_MCP = 5;

    protected $config = [
        'servers' => [
            [
                'name'      => 'zhanshop',
                'mode'      => SWOOLE_BASE,
                'host'      => '0.0.0.0',
                'port'      => 9501,
                'sock_type' => SWOOLE_SOCK_TCP,
                'serv_type' => Server::HTTP,
                'cross' => 'TOKEN',
                'settings' => [],
                'callbacks' => [
                ],
            ]
        ],
        // 关于静态化访问 正式环境中不提供该功能
        'settings' => [
            'daemonize' => true,
            'enable_coroutine' => true, // 这个只是将OnRequest 方法变成非阻塞而已而没有把mysql的操作变成非阻塞
            'hook_flags' => SWOOLE_HOOK_ALL,
            'log_level' => SWOOLE_LOG_INFO, // 仅记录错误日志以上的日志
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY, // 每日日志
            'log_date_format' => '%Y-%m-%d %H:%M:%S',
            'task_worker_num' => 1,
            'task_enable_coroutine' => true,
            'max_request' => 0,
            'max_wait_time' => 2,
            'enable_static_handler' => false,
            'document_root' => '',
            'http_autoindex' => true,
            'http_index_files' => ['index.html'],
            // 一个TCP连接的最少需要占用 224 字节
            'max_connection' => 200000,
            // 底层默认分配2M(C)虚拟内存+8K(PHP)内存
            'max_coroutine' => 1000, // 一个协程初始化的时候默认约占用8K物理内存
            //'ssl_cert_file' => '/ssl/swagger.crt',
            //'ssl_key_file' => '/ssl/swagger.key',
        ],
        // 定时任务是在子进程启动之后就开始执行【定时任务不受reload影响，需要restart后生效】
        'crontab' => [
        ],
        // 自定义进程
        'process' => [

        ]
    ];

    protected $callbacks = [
        Event::ON_START => [Event::class, 'onStart'],
        Event::ON_WORKER_START => [Event::class, 'onWorkerStart'],
        Event::ON_WORKER_STOP => [Event::class, 'onWorkerStop'],
        Event::ON_WORKER_EXIT => [Event::class, 'onWorkerExit'],
        Event::ON_WORKER_ERROR => [Event::class, 'onWorkerError'],
        Event::ON_PIPE_MESSAGE => [Event::class, 'onPipeMessage'],
        Event::ON_REQUEST => [Event::class, 'onRequest'],
        Event::ON_RECEIVE => [Event::class, 'onReceive'],
        Event::ON_CONNECT => [Event::class, 'onConnect'],
        //Event::ON_HAND_SHAKE => [Event::class, 'onHandshake'],
        Event::ON_OPEN => [Event::class, 'onOpen'],
        Event::ON_MESSAGE => [Event::class, 'onMessage'],
        Event::ON_CLOSE => [Event::class, 'onClose'],
        Event::ON_TASK => [Event::class, 'onTask'],
        Event::ON_FINISH => [Event::class, 'onFinish'],
        Event::ON_SHUTDOWN => [Event::class, 'onShutdown'],
        Event::ON_PACKET => [Event::class, 'onPacket'],
        Event::ON_MANAGER_START => [Event::class, 'onManagerStart'],
        Event::ON_MANAGER_STOP => [Event::class, 'onManagerStop'],
        // 由于swoole5开始默认走SWOOLE_BASE模式就不存在管理进程下面两个方法主在SWOOLE_PROCESS下有效
        //Event::ON_BEFORE_RELOAD => [Event::class, 'onBeforeReload'],
        //Event::ON_BEFORE_SHUTDOWN => [Event::class, 'onBeforeShutdown'],
    ];
    /**
     * @var \Swoole\Server
     */
    public static $server = null;
    public function configure(){
        $this->setTitle('启动服务器')->setDescription('使用该命令可以创建服务器应用');
    }

    protected $command;
    protected static $servNames = [];

    /**
     * runtime运行文件初始化
     * @return void
     */
    protected function runtimeInit(){
        if(!file_exists(App::runtimePath())){
            mkdir(App::runtimePath());
        }

        if(!file_exists(App::runtimePath().DIRECTORY_SEPARATOR.'log')){
            mkdir(App::runtimePath().DIRECTORY_SEPARATOR.'log');
        }

        if(!file_exists(App::runtimePath().DIRECTORY_SEPARATOR.'server')){
            mkdir(App::runtimePath().DIRECTORY_SEPARATOR.'server');
        }
    }
    protected function getServerPid()
    {
        $pid = -1;
        if(isset($this->config['settings']['pid_file']) && file_exists($this->config['settings']['pid_file'])){
            $pid = file_get_contents($this->config['settings']['pid_file']);
        }
        return $pid;
    }
    /**
     * 初始化
     * @param array $argv
     * @return void
     * @throws \Exception
     */
    protected function init(array &$argv){
        $this->usage($argv);
        $this->command = $argv[0];
        if(($argv[1] ?? 'true') == 'false') $this->config['settings']['daemonize'] = false;

        $config = App::config()->get('server');
        $servers = $config['servers'] ?? $this->config['servers'];
        unset($config['servers']);
        foreach($config as $k => $v){
            if(isset($this->config[$k]) && is_array($this->config[$k])){
                $this->config[$k] = array_merge($this->config[$k], $v);
            }
        }
        $this->config['servers'] = $servers;
        self::$servNames = array_column($servers, 'name');

        $callbacks = $this->config['servers'][0]['callbacks'] ?? [];
        $initial = $callbacks[Event::ON_INITIAL] ?? null;
        if($initial) $initial();
        unset($callbacks[Event::ON_INITIAL]);
        foreach($this->callbacks as $k => $v){
            if(isset($callbacks[$k]) == false){
                $callbacks[$k] = $v;
            }
        }
        $this->config['servers'][0]['callbacks'] = $callbacks;
        $this->config['settings']['pid_file'] = App::runtimePath().DIRECTORY_SEPARATOR.'server'.'.pid';
        $logDir = App::runtimePath().DIRECTORY_SEPARATOR.'server';
        // 检查日志目录是否存在
        if(!file_exists($logDir)) mkdir($logDir);
        $this->config['settings']['log_file'] = $logDir.DIRECTORY_SEPARATOR.'server.log';
        $this->config['settings']['worker_num'] = $this->config['settings']['worker_num'] ?? swoole_cpu_num();
        $this->config['settings']['reactor_num'] = $this->config['settings']['reactor_num'] ?? swoole_cpu_num();
        // 由于swoole暂不支持多个http静态文件访问(当server个数大于1禁用enable_static_handler)
        // 由于swoole http静态文件访问 HTTP2有bug 当开启open_http2_protocol(禁用enable_static_handler)
        // 同一组进程同端口同时支持监听IPV4和IPV6目前只支持BASE模式下，SWOOLE_PROCESS模式下暂不支持同一个端口同时监听IPV4和IPV6
        $http2 = $this->config['settings']['open_http2_protocol'] ?? false;
        if($http2){
            //$this->config['settings']['enable_static_handler'] = false;
        }
        //$this->config['settings']['stats_file'] = App::runtimePath().DIRECTORY_SEPARATOR.'status'.DIRECTORY_SEPARATOR.$this->loadName.'.json';
    }

    protected Input $input;
    protected Output $output;

    /**
     * 入口
     * @param Input $input
     * @param Output $output
     * @return mixed|void
     * @throws \Exception
     */
    public function execute(Input $input, Output $output){
        $this->runtimeInit();
        $this->input = $input;
        $this->output = $output;
        // 第一个参数 server 第二个参数启动 关闭 重载 重启 第三个参数 是否后台启动
        $argv = $input->getArgv();
        $this->init($argv);
        $command = $this->command;
        $this->$command();
    }

    /**
     * 检查服务是否运行
     * @return false|int
     */
    protected function isRuning(){
        if($this->config['servers'][0]['mode'] == 3){
            if(count($this->config['servers']) > 1){
                App::error()->setError("多线程模式下暂仅支持监听一个服务");
            }
            return false;
        }
        try {
            $pidFile = $this->config['settings']['pid_file'];
            if(file_exists($pidFile)){
                $pid = intval(file_get_contents($this->config['settings']['pid_file']));
                if($pid && \Swoole\Process::kill($pid, 0)){
                    //$this->output->output("进程ID{$pid}运行中...", 'error');
                    return $pid;
                }
            }
        }catch (\Throwable $e){
        }
        return false;
    }

    /**
     * 检查服务端口是否被占用
     * @return bool
     */
    protected function isUsePort(){
        foreach($this->config['servers'] as $server){
            $host = $server['host'];
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = '['.$host.']';
            }
            $port = $server['port'];
            $servType = $server['serv_type'];
            if($servType != 2){
                try {
                    $socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
                    if($socket == false) return true; // 被占用
                }catch (\Throwable $e){
                    $this->output->output($port."tcp端口被占用...", 'error');
                    return true; // 被占用
                }
            }else{
                try {
                    $socket = @stream_socket_server("udp://{$host}:{$port}", $errno, $errstr);
                    if($socket == false) return true; // 被占用
                }catch (\Throwable $e){
                    $this->output->output($port."udp端口被占用...", 'error');
                    return true; // 被占用
                }
            }
            fclose($socket);
        }
        return false;
    }

    /**
     * 服务事件绑定
     * @param \Swoole\Server $server
     * @param $callbacks
     * @return void
     */
    protected function serverEventBind(mixed &$server, array &$callbacks){
        foreach($callbacks as $k => $v){
            $server->on($k, [App::make($v[0], [$server, self::$servNames]), $v[1]]);
        }
    }

    /**
     * 创建Server对象
     * @param $servType
     * @param $host
     * @param $port
     * @param $mode
     * @param $sockType
     * @return \Swoole\Http\Server|\Swoole\Server|\Swoole\WebSocket\Server
     */
    protected function createServer(array &$conf){
        $conf['settings'] = $conf['settings'] ?? [];
        $servType = $conf['serv_type'];
        $host = $conf['host'];
        $port = $conf['port'];
        $mode = $conf['mode'] ?? SWOOLE_BASE;
        $sockType = $conf['sock_type'];
        switch ($servType){
            case Server::HTTP:
                $conf['settings']['open_http_protocol'] = true;
                $server = new \Swoole\Http\Server($host, $port, $mode, $sockType);
                break;
            case Server::WEBSOCKET:
                $conf['settings']['open_websocket_protocol'] = true;
                $server = new \Swoole\WebSocket\Server($host, $port, $mode, $sockType);
                break;
            default:
                $server = new \Swoole\Server($host, $port, $mode, $sockType);
        }
        return $server;
    }

    /**
     * 添加监听服务
     * @param mixed $server
     * @param array $conf
     * @return mixed
     */
    public function addListenServer(\Swoole\Server &$server, array &$conf){
        $conf['settings'] = $conf['settings'] ?? [];
        $servType = $conf['serv_type'];
        $host = $conf['host'];
        $port = $conf['port'];
        $sockType = $conf['sock_type'];

//        $conf['settings']['open_http_protocol'] = false;
//        $conf['settings']['open_websocket_protocol'] = false;
//        $conf['settings']['open_mqtt_protocol'] = false;
//        $conf['settings']['open_redis_protocol'] = false;

        switch ($servType){
            case Server::HTTP:
                $conf['settings']['open_http_protocol'] = true;
                break;
            case Server::WEBSOCKET:
                $conf['settings']['open_http_protocol'] = true;
                $conf['settings']['open_websocket_protocol'] = true;
                break;
        }
        $subServer = $server->listen($host, $port, $sockType);
        return $subServer;
    }

    /**
     * 监听协议
     * @param array $conf
     * @param bool $echoListenMsg
     * @return string
     */
    protected function listenProtocol(array $conf, bool $echoListenMsg = true){
        $protocol = 'tcp';
        switch ($conf['serv_type']){
            case Server::UDP:
                $protocol = 'udp';
                break;
            case Server::HTTP:
                $protocol = 'http';
                break;
            case Server::WEBSOCKET:
                $protocol = 'ws';
                break;
        }
        if($conf['sock_type'] > 2){
            $conf['host'] = '['.$conf['host'].']';
        }
        if($conf['sock_type'] >= 512) $protocol .= 's';
        if($this->config['settings']['daemonize'] == false && $echoListenMsg && ($this->config["servers"][0]["mode"] ?? -1) != 3) echo '['.date('Y-m-d H:i:s').'] ###[listen]###'." ".$protocol.'://'.$conf['host'].':'.$conf['port'].'/'.PHP_EOL;
        return $protocol;
    }

    /**
     * 启动服务器
     * @return void|null
     */
    public function start(){
        // 并且检查端口是否被占用

        if($this->getServerPid() != 1 && $this->isRuning() && $this->isUsePort()){
            return $this->output->output("程序已在运行...", 'info');
        }
        $server = null;
        foreach($this->config['servers'] as $k => $v){
            if($k == 0){
                //if(!in_array($v['serv_type'], [Server::HTTP, Server::WEBSOCKET])) App::error()->setError('主server的serv_type必须为HTTP | ');
                $server = $this->createServer($v);
                $settings = array_merge($this->config['settings'], $v['settings']);
                if($v['serv_type'] != Server::HTTP){
                    $settings['enable_static_handler'] = false; // 如果主服务不是http关闭http静态访问配置不然其他类型服务存在异常
                }
                $server->set($settings);
                $this->serverEventBind($server, $v['callbacks']);
            }else if($server){
                if($v['serv_type'] > $this->config['servers'][0]['serv_type']) App::error()->setError("第".($k+1).'个server的serv_type值不得高于首个serv_type的值');
                $subServer = $this->addListenServer($server, $v);
                if($subServer == false){
                    $errorMsg = $v['host'].'的'.$v['port'].'端口监听失败';
                    if($v['port'] < 1000 && ($_SERVER['USER'] ?? "") != 'root') $errorMsg .= ',请使用root权限执行';
                    App::error()->setError($errorMsg);
                }
                $subServer->set($v['settings']);
                $callbacks = $v['callbacks'] ?? [];
                if($callbacks) $this->serverEventBind($subServer, $callbacks);
            }
            $this->listenProtocol($v);
        }

        $customMsg = "";

        // 用户进程的生存周期与 Master 和 Manager 是相同的，不会受到 reload 影响 修改定时任务啥的需要重启
        if($this->config['crontab']){
            // 当存在定时任务的时候才会启动定时任务进程
            $process = new \Swoole\Process(function ($process) use ($server) {
                $process->set(['enable_coroutine' => true, 'hook_flags' => SWOOLE_HOOK_ALL]);
                foreach($this->config['crontab'] as $v){
                    try {
                        App::make(Timer::class)->register(new $v($server)); // 根据配置执行定时任务
                    }catch (\Throwable $err){
                        Log::errorLog(SWOOLE_LOG_NOTICE, $err->getMessage());
                    }
                }
                Log::errorLog(SWOOLE_LOG_DEBUG, "定时进程启动, 进程".getmypid());
            }, false, 2, true);
            $server->addProcess($process);
        }

        // 用户自定义进程
        if($this->config['process']){
            foreach($this->config['process'] as $v){
                $process = new \Swoole\Process(function ($process) use ($server, $v) {
                    $process->set(['enable_coroutine' => true]);
                    try {
                        App::make($v)->execute($server);
                    }catch (\Throwable $err){
                        Log::errorLog(5, $err->getMessage().PHP_EOL.$err->getFile().':'.$err->getLine());
                    }
                    sleep(60);
                }, false, 2, true);
                $server->addProcess($process);
            }
        }
        Server::$server = $server;
        $server->start();
    }

    /**
     * 重启服务器
     * @return void
     */
    public function restart(){
        $this->stop();
        $this->start();
    }

    /**
     * 停止服务器
     * @return void
     */
    public function stop(){
        $pid = $this->isRuning();
        if($pid){
            try {
                \Swoole\Process::kill($pid, SIGTERM);
                $startTime = microtime(true);
                while ($this->isRuning()){
                    usleep(100);
                    if((microtime(true) - $startTime) > 10){
                        $this->output->output('终止程序失败', 'error');
                        exit();
                    }
                }
                if(file_exists($this->config['settings']['pid_file'])) @unlink($this->config['settings']['pid_file']);
            }catch (\Throwable $e){
                $this->output->output($e->getMessage(), 'error');
                exit();
            }
        }
    }

    /**
     * 重载热更新工作进程
     * @return void
     */
    public function reload(){
        $pid = $this->isRuning();
        if($pid){
            try {
                \Swoole\Process::kill($pid, SIGUSR1);
                $this->output->output("重载完成", 'success');
            }catch (\Throwable $e){
                $this->output->output($e->getMessage(), 'error');
                exit();
            }
        }else{
            $this->output->output("程序尚未启动", 'info');
        }
    }

    /**
     * 输出服务器状态信息
     * @return void
     */
    public function status(){
        $pid = $this->isRuning();
        if($pid){
            echo PHP_EOL."--------------------------SERVER STATUS------------------------------------------------------".PHP_EOL;
            echo PHP_EOL.'swoole版本: '.swoole_version().'   php版本: '.PHP_VERSION.', 环境：'.($_SERVER['APP_ENV'] ?? 'dev').PHP_EOL.PHP_EOL;
            \Swoole\Coroutine\run(function() use ($pid){
                $protocol = $this->listenProtocol($this->config['servers'][0], false);
                $statusInfo = ServerStatus::info($protocol.'://'.$this->config['servers'][0]['host'].':'.$this->config['servers'][0]['port']);
                if($statusInfo){
                    $statusNum = 0;
                    foreach ($statusInfo as $k => $v){
                        echo $k.'：';
                        App::make(Output::class)->echo((string)$v, 'left', 16);
                        if($statusNum % 4 == 0) echo PHP_EOL;
                        $statusNum++;
                    }
                }else{
                    $pRunTimes = \Swoole\Coroutine\System::exec('ps -p '.$pid.' -o etimes,etime');
                    $pRunTimes = explode('       ', $pRunTimes['output']);
                    $pRunTime = array_values(array_filter(explode('   ', explode("\n", $pRunTimes[0])[1])))[0];
                    $pRunTime = intval($pRunTime);
                    echo '['.date('Y-m-d H:i:s', time() - $pRunTime).'] 开始运行, 现已运行: '.ServerStatus::getTimeStr($pRunTime).PHP_EOL.PHP_EOL;
                }

            });

            usleep(100);
            echo PHP_EOL."--------------------------SERVER STATUS------------------------------------------------------".PHP_EOL.PHP_EOL;

            exit();
        }
        //$this->output->output("程序尚未启动", 'info');
    }

    public function poweron(){
        if(($_SERVER['USER'] ?? "") != 'root'){
            App::error()->setError('请使用root用户执行该命令');
        }

        /**
         * root soft nofile 1024000
         * root hard nofile 1024000
         */

        $pwd = App::rootPath();
        // 检查文件是否存在
        $serviceName = App::config()->get("app.app_name", 'zhanshop-server');
        $serviceFile = '/etc/systemd/system/'.$serviceName.'.service';
        if(file_exists($serviceFile)){
            \Swoole\Coroutine\run(function() use ($serviceName, $serviceFile){
                \Swoole\Coroutine\System::exec('systemctl disable '.$serviceName);
                unlink($serviceFile);
            });
        }

        $phpCommand = 'php';
        if(isset($_SERVER['_'])){
            $phpCommand = $_SERVER['_'];
        }else if(isset($_SERVER['SUDO_COMMAND'])){
            $phpCommand = explode(' ', $_SERVER['SUDO_COMMAND'])[0];
        }

        $serviceCode = '[Unit]
Description='.$pwd.' - project
[Service]
Type=forking
ExecStart='.$phpCommand.' '.$pwd.'/'.$_SERVER['PHP_SELF'].' server start
ExecReload='.$phpCommand.' '.$pwd.'/'.$_SERVER['PHP_SELF'].' server reload
ExecStop='.$phpCommand.' '.$pwd.'/'.$_SERVER['PHP_SELF'].' server stop
Execenable='.$phpCommand.' '.$pwd.'/'.$_SERVER['PHP_SELF'].' server start
[Install]
WantedBy=multi-user.target
';
        \Swoole\Coroutine\run(function() use ($serviceFile, $serviceName, $serviceCode){

            file_put_contents($serviceFile, $serviceCode);
            \Swoole\Coroutine\System::exec('systemctl enable '.$serviceName);

        });

        $limits = file_get_contents("/etc/security/limits.conf");
        //var_dump($limits);die;
        if(strpos($limits, "root soft nofile 1024000") === false){
            $limitsStr = '';
            $arr = explode("\n", $limits);
            foreach ($arr as $v){
                $v0 = $v[0] ?? '';
                $v1 = $v[1] ?? '';
                var_dump($v0.'=>'.$v1);
                if($v0 == '*' && $v1 == ' '){
                    break;
                }
                $limitsStr .= $v."\n";
            }
            $limitsStr .= "* soft nofile 1024000\n";
            $limitsStr .= "* hard nofile 1024000\n";
            $limitsStr .= "root soft nofile 1024000\n";
            $limitsStr .= "root hard nofile 1024000\n";
            file_put_contents("/etc/security/limits.conf", $limitsStr);

            $defaultLimit = "\n\n";
            $defaultLimit = "DefaultLimitNOFILE=1024000\n";
            $defaultLimit = "DefaultLimitNPROC=1024000\n";
            error_log($defaultLimit, 3, "/etc/systemd/system.conf");

            \Swoole\Coroutine\run(function(){\Swoole\Coroutine\System::exec('reboot');});

            return;
        }

        $this->start();
    }


    // 支持到多端口

    public function usage(&$argv){
        if(!$argv){
            $this->output->output("\n示例用法:\n", 'success');
            echo 'php cmd.php server {start | stop | reload | restart | status |  poweron } {true|false}'.'   // 启动Server服务'.PHP_EOL;
            $this->output->output("\n参数说明：server 后的一个参数{启动|关闭|重载|重启|状态|开机启动} {后台启动(不指定默认)|非后台启动}");
            $this->output->output("关于环境：请修改全局环境变量 APP_ENV=dev或者APP_ENV=production 没有指定将会载入dev环境文件");
            exit();
        }
    }

    /**
     * 像任意Worker 进程或者 Task 进程发送消息
     * @param int $workerId
     * @param array $task
     * @param ...$param
     * @return void
     */
    public static function sendWorkerMessage(int $workerId, array $handler, ...$param)
    {
        $selfCall = false;
        if($workerId != -1){
            if($workerId == Server::$server->getWorkerId()){
                $selfCall = true;
            }else{
                Server::$server->sendMessage([$handler, $param], $workerId);
            }
        }else{
            $selfCall = true;
            for($i = 0; $i < self::$server->setting['worker_num']; $i++){
                if($i != Server::$server->getWorkerId()) Server::$server->sendMessage([$handler, $param], $i);
            }
        }
        if($selfCall){
            $method = $handler[1];
            App::make($handler[0])->$method(...$param);
        }
    }

}