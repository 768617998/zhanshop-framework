<?php

namespace zhanshop;

use Swoole\Coroutine;

class ShellCommand
{
    public static function exec($command, $callBack)
    {
        $cmd = $command;
        $error = App::runtimePath().'/process/'.md5($cmd).'.log';
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", $error, "a")
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);
        if($process == false) App::error()->setError($cmd.'启动失败');
        $startTime = microtime(true);
        $isRead = false;
        while (true) {
            $msg = fread($pipes[1], 20480);
            if($msg){
                $startTime = microtime(true);
                $status = $callBack($msg);
                if($status === false) break;
                $isRead = true;
            }else if($msg === ""){
                if($isRead === false){
                    $msg = file_get_contents($error);
                    $callBack($msg);
                }
                break;
            }else{
                if((microtime(true) - $startTime) > 10){
                    App::error()->setError($cmd.'执行超时');
                }
                usleep(100);
            }
        }
        @unlink($error);
        fclose($pipes[0]);
        proc_close($process);
    }

    /**
     * 调用一个命令
     * @param $command
     * @param $callBack
     * @return void
     * @throws \Exception
     */
    public static function call($command, $callBack)
    {
        $cmd = $command;
        $error = App::runtimePath().'/process/'.md5($cmd).'.log';
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", $error, "a")
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);
        if($process == false) App::error()->setError($cmd.'启动失败');

        Coroutine::create(function () use (&$error, $callBack){
            self::exec('tail -f '.$error, function ($msg)use ($callBack){
                if(strpos($msg, "###ZHANSHOP-END###") !== false){
                    return false;
                }
                $callBack($msg);
            });
        });

        while (true){
            $msg = fread($pipes[1], 20480);
            if($msg === ""){
                sleep(1);
                error_log("###ZHANSHOP-END###", 3, $error);
                break;
            }
        }

        @unlink($error);
        fclose($pipes[0]);
        proc_close($process);
    }
}