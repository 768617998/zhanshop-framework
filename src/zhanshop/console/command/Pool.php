<?php
// +----------------------------------------------------------------------
// | model-bridge / Pool.php    [ 2026/9/27 19:25 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\command;

use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;

class Pool extends Command
{
    public static $socketFile = '';
    public static $socketLog = '';
    public static $pidFile = '';
    public function configure()
    {
        $this->setTitle('进程池')->setDescription('启动一个进程池主要用于阻塞任务的执行');
        self::$socketFile = App::runtimePath() . '/pool.sock';
        self::$socketLog = App::runtimePath() . '/server/pool.log';
        self::$pidFile = App::runtimePath() . '/server/pool.pid';
    }

    public function getSocket()
    {
        if(self::$socketFile == false){
            self::$socketFile = App::runtimePath() . '/pool.sock';
        }
        return self::$socketFile;
    }

    public function execute(Input $input, Output $output)
    {
        // 主服务 startPool 用 System::exec 捕获输出：管道写端被 sh→php 继承进 pool 进程树(合法 stdout/stderr，
        // daemonize 还会 dup(1)/dup(2) 保存原 stdio)，导致主进程 exec 等不到 EOF 挂满 60 秒才进事件循环。
        // 启动时先经 bash 把 fd0/1/2 重定向、清掉其余继承的 pipe/socket，再重新 exec 本命令，彻底切断写端持有者。
        // 必须最早执行(此时进程除解释器与扩展外无自有 pipe/socket 状态)；重执行后 env 置位不再进入本分支
        if (getenv('ZHANSHOP_POOL_REEXEC') !== '1') {
            $closeCmd = 'export ZHANSHOP_POOL_REEXEC=1; exec </dev/null >/dev/null 2>/dev/null';
            $dirty = false;
            foreach (glob('/proc/self/fd/*') ?: [] as $fdPath) {
                $link = @readlink($fdPath);
                if ($link === false || (!str_starts_with($link, 'pipe:') && !str_starts_with($link, 'socket:'))) continue;
                $dirty = true; // fd1/2 命中即 60s 元凶(捕获管道写端)，由 redirect 处理；fd>2 才需显式关闭
                $fd = (int)basename($fdPath);
                if ($fd > 2) $closeCmd .= '; [ -e /proc/self/fd/'.$fd.' ] && exec '.$fd.'>&- || :'; // bash 关不存在的fd会终止shell: 存在才关、失败不断链
            }
            if ($dirty) {
                $args = array_merge([PHP_BINARY], $_SERVER['argv']);
                $cmd = $closeCmd.'; exec '.implode(' ', array_map('escapeshellarg', $args));
                pcntl_exec('/bin/bash', ['-c', $cmd]); // 失败则返回false, 按原逻辑继续启动(退化为旧的慢路径)
            }
        }
        if (file_exists(self::$pidFile)) {
            // 已有活 pool 时拒绝启动: 否则会 unlink 掉在用 sock 让旧 pool 成孤儿(旧 pid 死亡后的残留文件走下面 unlink 清理)
            // 进程校验走跨平台 helper: 原先直接读 /proc/{pid}/cmdline, 在 macOS(无 /proc)上恒为 false,
            // 判断失效导致每次 server start 都重复拉起一套 pool
            $oldPid = (int)@file_get_contents(self::$pidFile);
            if (self::isPoolProcess($oldPid)) {
                $output->output('进程池已在运行(pid '.$oldPid.')，拒绝重复启动', 'error');
                return;
            }
        }
        if (file_exists(self::$socketFile)) {
            @unlink(self::$socketFile); // 残留的旧socket可安全清理
        }
        $config = App::config()->get("server.process_pool");
        // 进程池按设计用于执行阻塞任务(1 worker = 1 槽位)，关闭协程：
        // 阻塞调用不会把 worker 卡在协程里，看门狗的同步文件检查也能随 WorkerStop 干净退出
        $config['enable_coroutine'] = false;
        $config['max_wait_time'] = 1;
        $config['daemonize'] = true;
        $config['pid_file'] = self::$pidFile;
        $config['log_file'] = self::$socketLog;

        // 看门狗目标必须在 start() 前捕获: pool 由主服务 master 拉起，daemonize fork 后父进程变成 systemd，该信息随之丢失。
        // System::exec 会先 fork 一个 "sh -c" 包装进程，所以沿父链向上最多3层找 "php cmd.php server ..."；
        // 找不到(如手动单独启动 pool)则不启用看门狗。父链读取走跨平台 helper(macOS 无 /proc)
        $ownerPid = 0;
        $ppid = posix_getppid();
        for ($d = 0; $d < 3 && $ppid > 1; $d++) {
            if (self::isServerProcess($ppid)) {
                $ownerPid = $ppid;
                break;
            }
            $ppid = self::processPpid($ppid);
        }

        $http = new \Swoole\Http\Server(self::$socketFile, 0, SWOOLE_PROCESS, SWOOLE_SOCK_UNIX_STREAM);
        $http->set($config);
        $http->on('Request', function ($request, $response) {
            $data = json_decode($request->getContent(), true);
            $class = $data[0][0] ?? '';
            $func = $data[0][1] ?? '';
            if($class && $func){
                try {
                    $result = App::make($class)->$func($data[1]);
                    $response->end(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
                }catch (\Throwable $throwable){
                    $response->status(500);
                    $response->end($throwable->getMessage());
                }
            }
        });

        // 看门狗: 主服务进程退出(stop/Ctrl+C/崩溃)后pool自行关闭，不依赖主进程回调。
        // 不依赖 server.pid 新鲜度——直接探测 owner 存活(启动窗口 Ctrl+C 时 server.pid 可能还是旧的);
        // 守护模式交接(前台启动进程退出、master pid 变化)后，再由 pool 从 server.pid 接管新 pid
        $serverPidFile = App::runtimePath() . '/server.pid';
        $http->on('WorkerStart', function ($server, $workerId) use ($ownerPid, $serverPidFile) {
            if ($workerId !== 0 || $ownerPid <= 0) return;
            // 判活: kill(pid,0) 遇 EPERM 会误报存活；僵尸、或 pid 被复用给其它进程都算已死。
            // 状态与 cmdline 校验都走跨平台 helper(macOS 无 /proc)
            $alive = function (int $pid) : bool {
                return self::isServerProcess($pid);
            };
            $owner = $ownerPid;
            $deadStreak = 0;
            $tid = 0;
            $tid = \Swoole\Timer::tick(1000, function () use ($server, $serverPidFile, $alive, &$owner, &$deadStreak, &$tid) {
                if ($alive($owner)) { $deadStreak = 0; return; }
                // 守护交接窗口: 前台启动进程已退出，新 master pid 已写入 server.pid，接管后继续守护
                $pid = (int)@file_get_contents($serverPidFile);
                if ($alive($pid)) {
                    $owner = $pid;
                    $deadStreak = 0;
                    return;
                }
                // 连续3拍确认已死才自杀; 先清掉自身tick，事件循环才能排空、worker干净退出
                if (++$deadStreak >= 3) {
                    \Swoole\Timer::clear($tid);
                    $server->shutdown();
                }
            });
        });
        $http->on('WorkerStop', function () {
            \Swoole\Timer::clearAll(); // 清掉看门狗tick，否则worker退出要等强制超时
        });
        $http->on('Shutdown', function () {
            // 只清理属于自己的文件: restart 时新 pool 会用同一路径重建 sock/pid，
            // 旧 pool 迟到的 Shutdown 若无条件 unlink 会把新 pool 删到不可达。
            // 关闭阶段不能 shell 出去探测子进程(实测 ps 拿不到结果)，因此只比对 pid 文件归属。
            $owner = file_exists(self::$pidFile) ? (int)@file_get_contents(self::$pidFile) : 0;
            if ($owner > 0 && $owner !== getmypid()) return;
            @unlink(self::$socketFile);
            @unlink(self::$pidFile);
        });

        $http->start();
    }

    /**
     * 读取进程命令行(跨平台): 优先 /proc, 无 /proc 的系统(macOS/Windows)用 ps 兜底
     */
    protected static function processCmdline(int $pid): string
    {
        if ($pid <= 0) return '';
        $raw = @file_get_contents('/proc/' . $pid . '/cmdline');
        if (is_string($raw) && $raw !== '') return str_replace("\0", ' ', $raw);
        if (!is_dir('/proc')) {
            $out = @shell_exec('ps -o command= -p ' . (int)$pid . ' 2>/dev/null');
            if (is_string($out)) return trim($out);
        }
        return '';
    }

    /**
     * 读取进程状态位(跨平台), 用于僵尸判定: Linux 取 /proc/{pid}/stat 的 state, 其它系统用 ps
     * 返回空串表示进程不存在
     */
    protected static function processState(int $pid): string
    {
        if ($pid <= 0) return '';
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        if (is_string($stat)) {
            $tail = substr($stat, (int)strrpos($stat, ')') + 2); // comm 可含空格, state/ppid 在最后的 ) 之后
            return explode(' ', $tail)[0] ?? '';
        }
        if (!is_dir('/proc')) {
            $out = @shell_exec('ps -o state= -p ' . (int)$pid . ' 2>/dev/null');
            if (is_string($out)) {
                $state = trim($out);
                if ($state === '') return '';
                if (stripos($state, 'Z') === 0) return 'Z'; // ps 可能返回 "Z+"
                return $state[0]; // "S+"/"R" 之类, 取首字符
            }
        }
        return '';
    }

    /**
     * 进程是否存活(僵尸与不存在都算已死)
     */
    protected static function processAlive(int $pid): bool
    {
        if ($pid <= 0) return false;
        $state = self::processState($pid);
        return $state !== '' && $state !== 'Z';
    }

    /**
     * 读取父进程 pid(跨平台)
     */
    protected static function processPpid(int $pid): int
    {
        if ($pid <= 0) return 0;
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        if (is_string($stat)) {
            $tail = substr($stat, (int)strrpos($stat, ')') + 2);
            return (int)(explode(' ', $tail)[1] ?? 0);
        }
        if (!is_dir('/proc')) {
            $out = @shell_exec('ps -o ppid= -p ' . (int)$pid . ' 2>/dev/null');
            if (is_string($out)) return (int)trim($out);
        }
        return 0;
    }

    /**
     * 判断 pid 是否仍是主服务进程(cmdline 校验防 pid 复用)
     */
    protected static function isServerProcess(int $pid): bool
    {
        if (!self::processAlive($pid)) return false;
        $cmdline = self::processCmdline($pid);
        return $cmdline !== '' && str_contains($cmdline, 'cmd.php') && str_contains($cmdline, 'server');
    }

    /**
     * 判断 pid 是否仍是 pool 进程(cmdline 校验防 pid 复用)
     */
    protected static function isPoolProcess(int $pid): bool
    {
        if (!self::processAlive($pid)) return false;
        $cmdline = self::processCmdline($pid);
        return $cmdline !== '' && str_contains($cmdline, 'cmd.php') && str_contains($cmdline, 'pool');
    }

    /**
     * 停止进程池(由 Server::stop / restart 调用, 使 pool 不依赖看门狗也能随之退出)
     * 必须等旧 pool 真正退出后才返回: 否则紧接着 start() 拉起的 pool 会与正在关闭的旧 pool
     * 抢同一个 sock 路径，旧 pool 迟到的 Shutdown 会删掉新 pool 的 sock/pid 导致其不可达。
     */
    public static function stop()
    {
        $pidFile = App::runtimePath() . '/server/pool.pid';
        if (!file_exists($pidFile)) return;
        $pid = intval(@file_get_contents($pidFile));
        @unlink($pidFile); // 先删: 让紧接着启动的新 pool 不会把旧 pool 误判为"已在运行"而拒绝启动
        // 校验后才杀: pid 文件可能陈旧或 pid 已被复用, 避免误杀无关进程
        if (!self::isPoolProcess($pid)) return;
        @\Swoole\Process::kill($pid, SIGTERM);
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline && self::processAlive($pid)) {
            usleep(100000);
        }
        if (self::processAlive($pid)) {
            // SIGKILL 不触发 Shutdown 回调, 至少不会再删掉后来者的文件
            @\Swoole\Process::kill($pid, SIGKILL);
            usleep(300000);
        }
    }
}
