<?php

namespace zhanshop;

class MaxFile
{
    /**
     * 到这读取文件
     * @param string $path
     * @param mixed $callback
     * @return void
     */
    public function endRead(string $path, mixed $callback)
    {

        // 打开文件
        $fileHandle = fopen($path, 'r');
        // 移动文件指针到文件末尾
        fseek($fileHandle, 0, SEEK_END);

        // 从文件末尾开始逐行读取
        while (($line = fgets($fileHandle)) !== false) {
            // 处理读取到的数据
            if($callback($line) === false) break;
        }

        // 关闭文件句柄
        fclose($fileHandle);
    }

    public function read(string $path, mixed $callback)
    {
        // 打开文件，'r'模式为只读
        $file = fopen($path, 'r');

        // 检查文件是否成功打开
        if ($file) {
            // 逐行读取
            while (($line = fgets($file)) !== false) {
                // 处理$line中的文本
                if($callback($line) === false) break;
            }
            // 关闭文件
            fclose($file);
        } else {
            App::error()->setError("文件打开失败");
        }
    }
}