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

use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;
use zhanshop\console\ServerStatus;
use zhanshop\Log;
use zhanshop\server\Event;
use zhanshop\Timer;

class Server extends Command
{
    public const TCP       = 1;
    public const UDP       = 2;
    public const HTTP      = 3;
    public const WEBSOCKET = 4;
    public const HTTP_MCP  = 5;

    protected $config = [
        'servers' => [
            [
                'name'      => 'zhanshop',
                'mode'      => SWOOLE_BASE,
                'host'      => '0.0.0.0',
                'port'      => 9501,
                'sock_type' => SWOOLE_SOCK_TCP,
                'serv_type' => self::HTTP,
                'cross' => 'TOKEN',
                'settings' => [],
                'callbacks' => [],
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
        'crontab' => [],
        // 自定义进程
        'process' => [],
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
    protected $lockFp = null;

    /**
     * runtime运行文件初始化
     * @return void
     */
    protected function runtimeInit(){
        $runtimePath = App::runtimePath();
        foreach([$runtimePath, $runtimePath.DIRECTORY_SEPARATOR.'log', $runtimePath.DIRECTORY_SEPARATOR.'server'] as $path){
            if(!file_exists($path)) @mkdir($path, 0777, true);
        }
    }

    protected function getServerPid(){
        $pidFile = $this->config['settings']['pid_file'] ?? '';
        return $pidFile && file_exists($pidFile) ? file_get_contents($pidFile) : -1;
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
            if(!isset($callbacks[$k])) $callbacks[$k] = $v;
        }
        $this->config['servers'][0]['callbacks'] = $callbacks;
        $this->config['settings']['pid_file'] = App::runtimePath().DIRECTORY_SEPARATOR.'server.pid';
        $this->config['settings']['log_file'] = App::runtimePath().DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'server.log';
        $this->config['settings']['worker_num'] = $this->config['settings']['worker_num'] ?? swoole_cpu_num();
        $this->config['settings']['reactor_num'] = $this->config['settings']['reactor_num'] ?? swoole_cpu_num();
        // 注意：swoole http静态文件访问 HTTP2有bug，开启 open_http2_protocol 时应禁用 enable_static_handler
        if($this->config['settings']['enable_static_handler'] && ($this->config['settings']['document_root'] == false || file_exists($this->config['settings']['document_root']) == false)){
            App::error()->setError("静态文件目录不存在或未设置");
        }
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
        // 文件锁防止并发执行 start/stop/restart/reload（至多重试3次，每次等0.5秒）
        $needLock = in_array($command, ['start', 'stop', 'restart', 'reload']);
        if($needLock){
            $locked = false;
            for($retry = 0; $retry < 3; $retry++){
                if($this->serverLock()){
                    $locked = true;
                    break;
                }
                if($retry < 2) usleep(500000);
            }
            if(!$locked){
                $this->output->output("另一个 server 命令正在执行，请稍后重试", 'error');
                exit(1);
            }
        }
        $this->$command();
        $this->serverUnlock();
    }

    /**
     * 文件锁防止并发 start/stop/restart/reload
     */
    protected function serverLock(): bool {
        $lockFile = App::runtimePath().DIRECTORY_SEPARATOR.'server.lock';
        // 清理其他用户残留的锁文件（unlink 检查目录权限而非文件权限）
        if(file_exists($lockFile) && !is_writable($lockFile)){
            @unlink($lockFile);
        }
        $this->lockFp = @fopen($lockFile, 'w');
        if(!$this->lockFp) return false;
        return flock($this->lockFp, LOCK_EX | LOCK_NB);
    }

    protected function serverUnlock(): void {
        if($this->lockFp){
            flock($this->lockFp, LOCK_UN);
            fclose($this->lockFp);
            $this->lockFp = null;
        }
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
        $pid = intval(@file_get_contents($this->config['settings']['pid_file']));
        return $pid > 0 && $this->checkPidRunning($pid) ? $pid : false;
    }

    /**
     * 检查服务端口是否被占用
     */
    protected function isUsePort(){
        foreach($this->config['servers'] as $conf){
            if($this->checkPort($conf['host'], $conf['port'], $conf['serv_type'])) return true;
        }
        return false;
    }

    /**
     * 检查单个端口是否被占用
     */
    protected function checkPort(string $host, int $port, int $servType): bool {
        if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $host = '['.$host.']';
        $protocol = $servType == self::UDP ? 'udp' : 'tcp';
        try {
            $socket = @stream_socket_server("{$protocol}://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
        }catch (\Throwable $e){
            return true;
        }
        if($socket === false) return true;
        fclose($socket);
        return false;
    }

    /**
     * 等待端口释放（start/stop 共用）
     * @return bool 端口是否已全部释放
     */
    protected function waitPortsFree(float $seconds): bool {
        $deadline = microtime(true) + $seconds;
        while(true){
            if(!$this->isUsePort()) return true;
            if(microtime(true) >= $deadline) return false;
            usleep(100000);
        }
    }

    /**
     * 服务事件绑定
     * @param \Swoole\Server $server
     * @param $callbacks
     * @return void
     */
    protected function serverEventBind(mixed $server, array $callbacks){
        foreach($callbacks as $k => $v){
            $server->on($k, [App::make($v[0], [$server, self::$servNames]), $v[1]]);
        }
    }

    /**
     * 创建Server对象
     * @param array $conf
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
            case self::HTTP:
                $conf['settings']['open_http_protocol'] = true;
                $server = new \Swoole\Http\Server($host, $port, $mode, $sockType);
                break;
            case self::WEBSOCKET:
                $conf['settings']['open_websocket_protocol'] = true;
                $server = new \Swoole\WebSocket\Server($host, $port, $mode, $sockType);
                break;
            default:
                $server = new \Swoole\Server($host, $port, $mode, $sockType);
                break;
        }
        return $server;
    }

    /**
     * 添加监听服务
     * @param mixed $server
     * @param array $conf
     * @return mixed
     */
    public function addListenServer(\Swoole\Server $server, array &$conf){
        $conf['settings'] = $conf['settings'] ?? [];
        $servType = $conf['serv_type'];
        $host = $conf['host'];
        $port = $conf['port'];
        $sockType = $conf['sock_type'];
        switch ($servType){
            case self::HTTP:
                $conf['settings']['open_http_protocol'] = true;
                break;
            case self::WEBSOCKET:
                $conf['settings']['open_http_protocol'] = true;
                $conf['settings']['open_websocket_protocol'] = true;
                break;
        }
        return $server->listen($host, $port, $sockType);
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
            case self::UDP:
                $protocol = 'udp';
                break;
            case self::HTTP:
                $protocol = 'http';
                break;
            case self::WEBSOCKET:
                $protocol = 'ws';
                break;
        }
        if($conf['sock_type'] > 2){
            $conf['host'] = '['.$conf['host'].']';
        }
        if($conf['sock_type'] >= 512) $protocol .= 's';
        if(!$this->config['settings']['daemonize'] && $echoListenMsg && ($this->config['servers'][0]['mode'] ?? -1) != 3){
            echo '['.date('Y-m-d H:i:s').'] ###[listen]###'." ".$protocol.'://'.$conf['host'].':'.$conf['port'].'/'.PHP_EOL;
        }
        return $protocol;
    }

    /**
     * 启动服务器
     * @return void|null
     */
    public function start(){
        if($this->getServerPid() != 1 && $this->isRuning()){
            return $this->output->output("程序已在运行...", 'info');
        }
        // 进程不在但端口被占用（可能是 stop 后内核未释放），等待至多5秒
        if($this->isUsePort()){
            // 确认占用者是外部进程（另一个项目/其他程序）时立即失败：这类占用不会随时间释放
            $foreign = $this->findForeignPortOwner();
            if($foreign){
                return $this->output->output("端口 {$foreign[0]} 已被其他进程占用(PID {$foreign[1]})，启动失败", 'error');
            }
            // 无法确认归属，或确认是本项目残留进程时，保持原有的5秒等待
            if(!$this->waitPortsFree(5)){
                return $this->output->output("端口被占用，启动失败，请稍后重试", 'error');
            }
        }
        $server = null;
        foreach($this->config['servers'] as $k => $v){
            if($k == 0){
                $server = $this->createServer($v);
                $settings = array_merge($this->config['settings'], $v['settings']);
                if($v['serv_type'] != self::HTTP){
                    $settings['enable_static_handler'] = false; // 如果主服务不是http关闭http静态访问配置不然其他类型服务存在异常
                }
                $server->set($settings);
                $this->serverEventBind($server, $v['callbacks']);
            }elseif($server){
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
        self::$server = $server;
        // 释放文件锁再进入阻塞的事件循环（否则锁会被永久持有）
        $this->serverUnlock();
        $this->startPool();
        $server->start();
    }

    /**
     * 重启服务器
     * @return void
     */
    public function restart(){
        // systemd 管理的实例必须交给 systemd 重启：直接 stop 会被 Restart=always 拉起，
        // 而手动 start 出来的进程又不在 systemd 的 cgroup 里，后续崩溃不会自动恢复
        $pid = $this->runningPid();
        // 崩溃后的 RestartSec 窗口内没有 PID/端口，但单元仍是 activating/auto-restart，
        // 此时也必须交给 systemd，否则手动 start 会和 Restart=always 抢同一批端口
        $unit = $pid ? $this->systemdUnit($pid) : $this->activeSystemdUnit();
        if($unit !== null && ($pid === 0 || $this->canSignal($pid)) && getenv('ZHANSHOP_SYSTEMD_STOP') !== '1'){
            // 释放命令锁，否则 ExecStop 里的 server stop 无法进入，systemd 会一直等到超时
            $this->serverUnlock();
            $out = [];
            $rc = 0;
            @exec('systemctl restart '.escapeshellarg($unit).' 2>&1', $out, $rc);
            if($rc !== 0){
                $this->output->output("systemd 重启 {$unit} 失败: ".trim(implode("\n", $out)), 'error');
                exit(1);
            }
            $this->output->output("已通过 systemd 重启 {$unit}", 'success');
            return;
        }
        $this->stop();
        $this->start();
    }

    /**
     * 当前服务 PID：PID 文件优先，缺失时按配置端口反查；0 表示未运行
     */
    protected function runningPid(): int {
        $pid = $this->isRuning();
        if($pid) return $pid;
        if(!$this->isUsePort()) return 0;
        foreach(array_unique(array_column($this->config['servers'], 'port')) as $port){
            if(($pid = $this->findPidByPort((int)$port)) > 0) return $pid;
        }
        return 0;
    }

    /**
     * 被其他项目占用的配置端口 [端口, PID]
     * 仅当能确定占用者不是本项目时返回，否则返回 null，由调用方保持原有等待逻辑
     */
    protected function findForeignPortOwner(): ?array {
        foreach($this->config['servers'] as $conf){
            $port = (int)$conf['port'];
            // 只查 ss/fuser：此处用于快速失败，查不到归属就退回等待，不扫描 /proc
            $pid = $this->findPidByPort($port, false);
            if($pid > 0 && $this->isOwnProcess($pid) === false) return [$port, $pid];
        }
        return null;
    }

    /**
     * 判断进程是否属于本项目：/proc/PID/fd 中是否有指向项目目录的描述符
     * @return bool|null true=本项目 false=确定不是本项目 null=无法判断
     */
    protected function isOwnProcess(int $pid): ?bool {
        $root = realpath(App::rootPath());
        if($root === false) return null;
        $fdDir = "/proc/{$pid}/fd";
        $fds = @scandir($fdDir);
        if($fds === false) return null; // 进程已退出或无权读取
        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $checked = 0;
        foreach($fds as $fd){
            if($fd === '.' || $fd === '..') continue;
            $target = @readlink($fdDir.DIRECTORY_SEPARATOR.$fd);
            if($target === false || $target === '') continue;
            $checked++;
            if(strpos($target, $prefix) === 0) return true;
        }
        return $checked > 0 ? false : null;
    }

    /**
     * 是否允许向该 PID 发信号：必须能证明它是本项目的 server 进程
     * 防止 PID 文件失效/端口反查误判时，kill 到其他项目或容器 PID 1（宿主 init）
     */
    protected function canSignal(int $pid): bool {
        if($pid <= 0) return false;
        return $this->isOwnProcess($pid) === true || $this->isOurServerProcess($pid);
    }

    /**
     * 依据 cmdline 与入口脚本身份判定是否为本项目 server（isOwnProcess 无法判断时兜底）
     * 只按目标进程自己的 cwd/root 解析脚本，避免用本项目绝对路径在宿主根目录下误匹配
     */
    protected function isOurServerProcess(int $pid): bool {
        $raw = @file_get_contents("/proc/{$pid}/cmdline");
        if($raw === false || $raw === '') return false;
        $args = explode("\0", rtrim($raw, "\0"));
        if(!in_array('server', $args, true)) return false;
        $script = '';
        foreach($args as $arg){
            if(basename($arg) === 'cmd.php'){
                $script = $arg;
                break;
            }
        }
        if($script === '') return false;
        if($script[0] !== DIRECTORY_SEPARATOR){
            $cwd = @readlink("/proc/{$pid}/cwd");
            if($cwd === false) return false;
            $script = rtrim($cwd, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$script;
        }
        $self = realpath(rtrim(App::rootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'cmd.php');
        if($self === false) return false;
        $selfStat = @stat($self);
        $procStat = @stat("/proc/{$pid}/root".$script);
        return $selfStat !== false && $procStat !== false
            && $selfStat['dev'] === $procStat['dev']
            && $selfStat['ino'] === $procStat['ino'];
    }

    /**
     * 返回管理该 PID 且属于本项目的 systemd unit；非 systemd 管理时返回 null
     * 只认 poweron 生成的 <app_name>.service，避免误停其他服务
     */
    protected function systemdUnit(int $pid): ?string {
        if(PHP_OS_FAMILY !== 'Linux') return null;
        $cgroup = @file_get_contents("/proc/{$pid}/cgroup");
        if($cgroup === false || $cgroup === '') return null;
        if(preg_match('~/[^/\s]*\.service(?:\s|$)~', $cgroup, $m) !== 1) return null;
        $unit = basename(trim($m[0]));
        $expected = App::config()->get('app.app_name', 'zhanshop-server').'.service';
        return $unit === $expected ? $unit : null;
    }

    /**
     * 当前项目的 unit 仍处于 active/activating 等状态时返回 unit
     * 崩溃后的 RestartSec 窗口内没有 PID 和端口，但 systemd 仍会拉起
     */
    protected function activeSystemdUnit(): ?string {
        if(PHP_OS_FAMILY !== 'Linux') return null;
        $unit = App::config()->get('app.app_name', 'zhanshop-server').'.service';
        if(!file_exists('/etc/systemd/system/'.$unit)) return null;
        $out = [];
        $rc = 0;
        @exec('systemctl is-active '.escapeshellarg($unit).' 2>/dev/null', $out, $rc);
        $state = trim(implode('', $out));
        return in_array($state, ['active', 'activating', 'reloading', 'deactivating'], true) ? $unit : null;
    }

    /**
     * 端口 → 占用进程 PID（存活进程中最小者，通常是 master）
     * @param bool $procScan ss/fuser 均查不到时，是否再用 /proc 全量扫描兜底
     */
    protected function findPidByPort(int $port, bool $procScan = true): int {
        $output = @shell_exec("ss -tlnp 2>/dev/null | grep ':{$port} ' | grep -oP 'pid=\K[0-9]+'");
        if($output){
            $pids = array_unique(array_map('intval', explode("\n", trim($output))));
            sort($pids, SORT_NUMERIC);
            foreach($pids as $pid){
                if($pid > 1 && file_exists("/proc/{$pid}")) return $pid;
            }
        }
        $output = @shell_exec("fuser {$port}/tcp 2>/dev/null");
        if($output && ($pid = intval(trim($output))) > 0) return $pid;
        if(!$procScan) return 0;
        // 兜底：先在 /proc/net 中找到监听 socket 的 inode，再扫 /proc/*/fd 定位持有它的进程
        $hexPort = str_pad(dechex($port), 4, '0', STR_PAD_LEFT);
        foreach(['tcp', 'tcp6'] as $proto){
            $lines = @file("/proc/net/{$proto}", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if(!$lines) continue;
            foreach(array_slice($lines, 1) as $line){
                $parts = preg_split('/\s+/', trim($line));
                if(count($parts) < 10) continue;
                $local = explode(':', $parts[1]);
                if(count($local) < 2 || strtolower($local[1]) !== $hexPort) continue;
                $inode = intval($parts[9]);
                if($inode <= 0) continue;
                foreach(@glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $dir){
                    foreach(@glob($dir.'/fd/*') ?: [] as $fd){
                        $link = @readlink($fd);
                        if($link && strpos($link, "socket:[{$inode}]") !== false) return intval(basename($dir));
                    }
                }
            }
        }
        return 0;
    }

    /**
     * 停止服务器
     * @return void
     */
    public function stop(){
        // pool 不随 master 退出: 原先只靠 pool 自身看门狗(Windows/macOS 无 /proc 时压根不注册)，
        // 显式停一次才能保证各平台 stop/restart 后不留孤儿 pool，restart 随后会重新拉起
        Pool::stop();
        $pid = $this->runningPid();
        $unit = null;
        if(!$pid){
            // 端口仍被占用却定位不到进程时不能静默成功
            if($this->isUsePort()){
                $this->output->output("配置端口仍被占用，但无法定位占用进程，未执行停止", 'error');
                exit(1);
            }
            // 崩溃后的 RestartSec 窗口内没有 PID/端口，但 systemd 仍会在 RestartSec 后拉起
            $unit = $this->activeSystemdUnit();
        }else{
            if(!$this->canSignal($pid)){
                $this->output->output("PID {$pid} 不是本项目的 server 进程，已拒绝停止（可能属于其他项目，或容器 PID 1 被宿主解释为 init）", 'error');
                exit(1);
            }
            $unit = $this->systemdUnit($pid);
        }
        // 直接 CLI 停止 systemd 管理的实例时交给 systemd：如果在这里直接杀主进程，
        // Restart=always 会在 RestartSec 后把它重新拉起。ExecStop 里的二次调用带
        // ZHANSHOP_SYSTEMD_STOP=1（旧 unit 至少带 INVOCATION_ID），仍走下方的直接终止
        // 逻辑，不会递归。
        if($unit !== null && getenv('ZHANSHOP_SYSTEMD_STOP') !== '1' && getenv('INVOCATION_ID') === false){
            // 释放命令锁，否则 ExecStop 里的 server stop 无法进入，systemd 会一直等到超时
            $this->serverUnlock();
            $out = [];
            $rc = 0;
            @exec('systemctl stop '.escapeshellarg($unit).' 2>&1', $out, $rc);
            if($rc !== 0){
                $this->output->output("systemd 停止 {$unit} 失败: ".trim(implode("\n", $out)), 'error');
                exit(1);
            }
            if(file_exists($this->config['settings']['pid_file'])) @unlink($this->config['settings']['pid_file']);
            if(!$this->waitPortsFree(5)){
                $this->output->output("systemd 已停止 {$unit}，但端口仍未释放", 'error');
                exit(1);
            }
            $this->output->output("已通过 systemd 停止 {$unit}", 'success');
            return;
        }
        if(!$pid) return;
        // PID 文件缺失（或内容失效）时，PID 只可能来自端口反查
        $portFallback = !$this->isRuning();
        if($portFallback){
            $this->output->output("PID文件缺失，通过端口反查到进程: {$pid}", 'info');
        }
        try {
            $killRet = @\Swoole\Process::kill($pid, SIGTERM);
            if($killRet === false && swoole_errno() === 1){
                $this->output->output("无权限终止进程 {$pid}，请使用 sudo 执行", 'error');
                exit(1);
            }
            $startTime = microtime(true);
            // 只留 max_wait_time + 1 秒退出窗口；Swoole 6.2 的心跳线程 join 可能远超此窗口，超时后强制终止
            $waitTimeout = (int)$this->config['settings']['max_wait_time'] + 1;
            // 端口反查模式用 isUsePort 判断存活，避免单个 PID 不准
            $aliveCheck = $portFallback ? fn() => $this->isUsePort() : fn() => $this->checkPidRunning($pid);
            while ($aliveCheck()){
                $elapsed = microtime(true) - $startTime;
                if($elapsed > $waitTimeout){
                    // 等待期间 PID 可能已退出并被复用，强杀前再次确认归属
                    if(!$this->canSignal($pid)){
                        $this->output->output("进程 {$pid} 已退出或 PID 已被复用，跳过强制终止", 'info');
                        break;
                    }
                    // 仅杀 master 进程，避免进程组杀波及父脚本
                    @\Swoole\Process::kill($pid, SIGKILL);
                    usleep(500000);
                    if($aliveCheck()){
                        $this->output->output("终止程序失败, 请手动执行: kill -9 {$pid}", 'error');
                        exit(1);
                    }
                    break;
                }
                usleep(200000);
            }
            // 等待 workers 释放端口（Swoole onShutdown 可能先于 workers 退出触发）
            $free = $this->waitPortsFree(5);
            if(!$free) $this->output->output("等待端口释放超时，请稍后重试", 'error');
            // 进程已确认终止，PID 文件为僵尸数据，统一清理
            if(file_exists($this->config['settings']['pid_file'])) @unlink($this->config['settings']['pid_file']);
            if(!$free) exit(1);
        }catch (\Throwable $e){
            $this->output->output($e->getMessage(), 'error');
            exit(1);
        }
    }

    /**
     * 检查指定PID进程是否存活
     */
    protected function checkPidRunning(int $pid): bool {
        try {
            $ret = @\Swoole\Process::kill($pid, 0);
            if($ret === false && swoole_errno() === 1){
                // EPERM: 无权限发信号但进程存在(root进程)，通过 /proc 确认
                return file_exists("/proc/{$pid}");
            }
            return $ret;
        }catch (\Throwable $e){
        }
        return file_exists("/proc/{$pid}");
    }

    /**
     * 重载热更新工作进程
     * @return void
     */
    public function reload(){
        $pid = $this->runningPid();
        if(!$pid){
            $this->output->output("程序尚未启动", 'info');
            return;
        }
        if(!$this->canSignal($pid)){
            $this->output->output("PID {$pid} 不是本项目的 server 进程，已拒绝重载（可能属于其他项目，或容器 PID 1 被宿主解释为 init）", 'error');
            exit(1);
        }
        try {
            $killRet = @\Swoole\Process::kill($pid, SIGUSR1);
            if($killRet === false){
                $msg = swoole_errno() === 1 ? "无权限重载进程 {$pid}，请使用 sudo 执行" : "重载进程 {$pid} 失败";
                $this->output->output($msg, 'error');
                exit(1);
            }
            $this->output->output("重载完成", 'success');
        }catch (\Throwable $e){
            $this->output->output($e->getMessage(), 'error');
            exit(1);
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
            echo PHP_EOL."--------------------------SERVER STATUS------------------------------------------------------".PHP_EOL.PHP_EOL;

            exit();
        }
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
        }elseif(isset($_SERVER['SUDO_COMMAND'])){
            $phpCommand = explode(' ', $_SERVER['SUDO_COMMAND'])[0];
        }

        // 入口脚本固定用项目根目录 + 文件名，避免 PHP_SELF 为绝对路径时拼出重复路径
        $entry = rtrim($pwd, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($_SERVER['PHP_SELF'] ?? 'cmd.php');
        $pidFile = rtrim(App::runtimePath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'server.pid';

        // ExecStart 用 env -u 剥掉两个标记：主进程/worker 及其子进程不应继承 systemd Exec 上下文
        $serviceCode = '[Unit]
Description='.$pwd.' - project
[Service]
Type=forking
PIDFile='.$pidFile.'
ExecStartPre=/bin/rm -f '.$pidFile.'
Environment=ZHANSHOP_SYSTEMD_STOP=1
ExecStart=/usr/bin/env -u ZHANSHOP_SYSTEMD_STOP -u INVOCATION_ID '.$phpCommand.' '.$entry.' server start
ExecReload='.$phpCommand.' '.$entry.' server reload
ExecStop='.$phpCommand.' '.$entry.' server stop
Restart=always
RestartSec=3
LimitNOFILE=1024000
SuccessExitStatus=SIGKILL
[Install]
WantedBy=multi-user.target
';
        \Swoole\Coroutine\run(function() use ($serviceFile, $serviceName, $serviceCode){

            file_put_contents($serviceFile, $serviceCode);
            \Swoole\Coroutine\System::exec('systemctl daemon-reload');
            \Swoole\Coroutine\System::exec('systemctl enable '.$serviceName);

        });

        $limits = file_get_contents("/etc/security/limits.conf");
        if(strpos($limits, "root soft nofile 1024000") === false){
            $limitsStr = '';
            foreach(explode("\n", $limits) as $v){
                $v0 = $v[0] ?? '';
                $v1 = $v[1] ?? '';
                if($v0 == '*' && $v1 == ' ') break;
                $limitsStr .= $v."\n";
            }
            $limitsStr .= "* soft nofile 1024000\n";
            $limitsStr .= "* hard nofile 1024000\n";
            $limitsStr .= "root soft nofile 1024000\n";
            $limitsStr .= "root hard nofile 1024000\n";
            file_put_contents("/etc/security/limits.conf", $limitsStr);

            error_log("DefaultLimitNPROC=1024000\n", 3, "/etc/systemd/system.conf");

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
    public static function sendWorkerMessage(int $workerId, array $handler, ...$param){
        $selfCall = false;
        if($workerId != -1){
            if($workerId == self::$server->getWorkerId()){
                $selfCall = true;
            }else{
                self::$server->sendMessage([$handler, $param], $workerId);
            }
        }else{
            $selfCall = true;
            for($i = 0; $i < self::$server->setting['worker_num']; $i++){
                if($i != self::$server->getWorkerId()) self::$server->sendMessage([$handler, $param], $i);
            }
        }
        if($selfCall){
            $method = $handler[1];
            App::make($handler[0])->$method(...$param);
        }
    }

    /**
     * 启动进程池
     * @return void
     */
    public function startPool(){
        if(isset($_SERVER["_"]) && strpos($_SERVER["_"], "php") !== false && boolval(App::env()->get("OPEN_POOL"))){
            \Swoole\Coroutine\run(function(){
                // 必须把子进程 stdio 重定向掉: System::exec 靠管道 EOF 判定结束, 而 pool 会 daemonize 并
                // 一直持有该管道写端, exec 等不到 EOF 只能耗满 60 秒超时, 主进程因此卡满 60 秒才走到
                // $server->start() fork worker。Pool.php 里基于 /proc/self/fd 的兜底在 macOS 上不生效(无 /proc),
                // 所以在调用侧直接切断, 与平台无关。pool 自身日志走 log_file(runtime/server/pool.log), 不受影响。
                $command = $_SERVER["_"].' '.$_SERVER['argv'][0].' pool >/dev/null 2>&1 </dev/null';
                \Swoole\Coroutine\System::exec($command);
            });
        }
    }

}