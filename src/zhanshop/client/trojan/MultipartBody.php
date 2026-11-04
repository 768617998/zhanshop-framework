<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/MultipartBody.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | multipart/form-data 请求体拼装器 —— 对应 Httpclient 的 setUploadFile / uploadData。
// |
// | swoole 那边由 Swoole\Coroutine\Http\Client::addFile/addData 代劳, 这里只能自己拼。
// | 两条与内存有关的设计:
// |   · length() 用 filesize 算总长, **不读文件内容** —— 为的是能先给出 Content-Length,
// |     不必改用 chunked 传输编码(那会多一层复杂度, 而且很多网关不收)。
// |   · writeTo() 按片段边读边写, 上传大文件时内存里只留一个分片, 不整个塞进去。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

class MultipartBody
{
    /** 读文件的分片大小 */
    protected const CHUNK_SIZE = 65536;

    protected string $boundary;

    /** 每段: [0]=头部(到空行为止), [1]=内容(string) 或 [2]=文件描述 */
    protected array $parts = [];

    public function __construct(string $boundary = '')
    {
        $this->boundary = $boundary !== '' ? $boundary : '----zhanshop' . bin2hex(random_bytes(16));
    }

    /**
     * 普通表单字段
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function addField(string $name, string $value)
    {
        $this->parts[] = ['header' => $this->fieldHeader($name), 'body' => $value, 'file' => null];
        return $this;
    }

    /**
     * 直接给出内容的上传项(对应 uploadData)
     * @param string $name
     * @param string $data
     * @param string $mimeType
     * @param string $filename
     * @return $this
     */
    public function addData(string $name, string $data, string $mimeType = '', string $filename = '')
    {
        $this->parts[] = [
            'header' => $this->fileHeader($name, $filename !== '' ? $filename : $name, $mimeType !== '' ? $mimeType : 'application/octet-stream'),
            'body' => $data,
            'file' => null,
        ];
        return $this;
    }

    /**
     * 磁盘文件上传项(对应 setUploadFile)
     * @param string $name 表单名称
     * @param string $path 文件路径
     * @param string $type MIME, 空则按扩展名推断
     * @param string $filename 传给服务端的文件名, 空则用路径里的文件名
     * @param int $offset 起始偏移(断点续传)
     * @param int $length 发送长度, 0 = 到文件末尾
     * @return $this
     * @throws \RuntimeException 文件不可读时
     */
    public function addFile(string $name, string $path, string $type = '', string $filename = '', int $offset = 0, int $length = 0)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('上传文件不存在或不可读: ' . $path);
        }
        $size = filesize($path);
        if ($filename === '') $filename = basename($path);
        if ($type === '') $type = self::mimeOf($filename);
        if ($offset < 0) $offset = 0;
        if ($offset > $size) $offset = $size;
        $avail = $size - $offset;
        if ($length <= 0 || $length > $avail) $length = $avail;
        $this->parts[] = [
            'header' => $this->fileHeader($name, $filename, $type),
            'body' => null,
            'file' => ['path' => $path, 'offset' => $offset, 'length' => $length],
        ];
        return $this;
    }

    /**
     * 是否有内容
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->parts === [];
    }

    /**
     * 请求头里的 Content-Type
     * @return string
     */
    public function contentType(): string
    {
        return 'multipart/form-data; boundary=' . $this->boundary;
    }

    /**
     * 请求体总长度
     * @return int
     */
    public function length(): int
    {
        $len = 0;
        $dash = '--' . $this->boundary . "\r\n";
        foreach ($this->parts as $part) {
            // 每个段是: 分隔行(--boundary CRLF) + 段头 + CRLF(收尾最后一个头行) + CRLF(空行)
            $len += strlen($dash) + strlen($part['header']) + 4;
            $len += $part['file'] !== null ? $part['file']['length'] : strlen($part['body']);
            $len += 2; // 内容后的 CRLF
        }
        return $len + strlen('--' . $this->boundary . "--\r\n");
    }

    /**
     * 按片段把请求体写出去
     * @param callable $writer function(string $chunk): bool —— 返回 false 表示写失败, 立即中止
     * @return bool
     */
    public function writeTo(callable $writer): bool
    {
        $dash = '--' . $this->boundary . "\r\n";
        foreach ($this->parts as $part) {
            // 段头之后必须再补一个空行(RFC 7578: 段头以空行结束)。少这一个 CRLF,
            // 服务端会把紧跟着的**内容**当成头继续读 —— 整个体就废了, 且本地看不出任何异常。
            if (!$writer($dash . $part['header'] . "\r\n\r\n")) return false;
            if ($part['file'] !== null) {
                if (!self::writeFile($writer, $part['file'])) return false;
            } elseif (!$writer($part['body'])) {
                return false;
            }
            if (!$writer("\r\n")) return false;
        }
        return (bool)$writer('--' . $this->boundary . "--\r\n");
    }

    /**
     * 分片写文件
     * @param callable $writer
     * @param array $file
     * @return bool
     */
    protected static function writeFile(callable $writer, array $file): bool
    {
        $fp = fopen($file['path'], 'rb');
        if ($fp === false) return false;
        try {
            if ($file['offset'] > 0) fseek($fp, $file['offset']);
            $left = $file['length'];
            while ($left > 0) {
                $n = $left < self::CHUNK_SIZE ? $left : self::CHUNK_SIZE;
                $buf = fread($fp, $n);
                if ($buf === false || $buf === '') break;
                if (!$writer($buf)) return false;
                $left -= strlen($buf);
            }
            return $left <= 0;
        } finally {
            fclose($fp);
        }
    }

    /**
     * 普通字段的段头
     * @param string $name
     * @return string
     */
    protected function fieldHeader(string $name): string
    {
        return 'Content-Disposition: form-data; name="' . self::quote($name) . '"';
    }

    /**
     * 文件字段的段头
     * @param string $name
     * @param string $filename
     * @param string $type
     * @return string
     */
    protected function fileHeader(string $name, string $filename, string $type): string
    {
        return 'Content-Disposition: form-data; name="' . self::quote($name) . '"; filename="' . self::quote($filename) . '"' . "\r\n"
            . 'Content-Type: ' . $type;
    }

    /**
     * 属性值里的引号与反斜杠必须转义, 否则能注入出畸形的段头
     * @param string $v
     * @return string
     */
    protected static function quote(string $v): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
    }

    /**
     * 按扩展名猜 MIME(只需覆盖常见几类; 与 swoole 一样, 猜不出就用二进制流)
     * @param string $filename
     * @return string
     */
    protected static function mimeOf(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        static $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
            'txt' => 'text/plain', 'html' => 'text/html', 'htm' => 'text/html',
            'css' => 'text/css', 'csv' => 'text/csv', 'xml' => 'text/xml',
            'js' => 'application/javascript', 'json' => 'application/json',
            'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'gz' => 'application/gzip', 'tar' => 'application/x-tar',
            'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
