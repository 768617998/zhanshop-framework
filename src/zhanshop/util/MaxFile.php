<?php

namespace zhanshop\util;

class MaxFile
{
    /**
     * 读取文件从末尾开始
     * @param string $filepath
     * @param $blockSize
     * @return \Generator|void
     */
    public function endRead(string $filepath, $blockSize = 16000)
    {

        $fp = @fopen($filepath, 'rb');
        if (!$fp) return;

        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $buffer = '';
        $delimiters = ["\r\n", "\n", "\r"];

        // 从后向前循环读取块
        while ($pos > 0) {
            $readSize = $pos >= $blockSize ? $blockSize : $pos;
            $pos -= $readSize;
            fseek($fp, $pos);
            $chunk = fread($fp, $readSize);
            $buffer = $chunk . $buffer;

            // 动态切割完整行
            $lines = [];
            while (true) {
                $found = false;
                foreach ($delimiters as $delim) {
                    $delimPos = strrpos($buffer, $delim);
                    if ($delimPos !== false) {
                        $line = substr($buffer, $delimPos + strlen($delim));
                        $buffer = substr($buffer, 0, $delimPos);
                        array_unshift($lines, $line);
                        $found = true;
                        break;
                    }
                }
                if (!$found) break;
            }

            // 逆序生成当前块解析出的行
            foreach (array_reverse($lines) as $line) {
                yield $line;
            }
        }

        // 处理最后剩余内容
        if ($buffer !== '') {
            yield $buffer;
        }

        fclose($fp);
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

    /**
     * 迭代器逐行读取文件
     * @param $filepath
     * @return \Generator|void
     */
    function readLines($filepath) {
        $fp = fopen($filepath, 'rb');
        if (!$fp) return;

        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                yield $line;
            }
        }
        fclose($fp);
    }
}