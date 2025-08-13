<?php
// +----------------------------------------------------------------------
// | zhanshop-message  / Mail.php    [ 2025/4/25 09:06 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util;
use Swoole\Coroutine;
use zhanshop\App;

class Mail
{
    /**
     * @var string 邮件传输代理用户名
     * @access private
     */
    private $_userName;
    /**
     * @var string 邮件传输代理密码
     * @access private
     */
    private $_password;
    /**
     * @var string 邮件传输代理服务器地址
     * @access protected
     */
    protected $_sendServer;
    /**
     * @var int 邮件传输代理服务器端口
     * @access protected
     */
    protected $_port=25;
    /**
     * @var string 发件人
     * @access protected
     */
    protected $_from;
    /**
     * @var string 收件人
     * @access protected
     */
    protected $_to;
    /**
     * @var string 抄送
     * @access protected
     */
    protected $_cc;
    /**
     * @var string 秘密抄送
     * @access protected
     */
    protected $_bcc;
    /**
     * @var string 主题
     * @access protected
     */
    protected $_subject;
    /**
     * @var string 邮件正文
     * @access protected
     */
    protected $_body;
    /**
     * @var string 附件
     * @access protected
     */
    protected $_attachment;
    /**
     * @var reource socket资源
     * @access protected
     */
    protected $socket;
    /**
     * @var string 错误信息
     * @access protected
     */
    protected $_errorMessage;
    /**
     * 设置邮件传输代理，如果是可以匿名发送有邮件的服务器，只需传递代理服务器地址就行
     * @access public
     * @param string $server 代理服务器的ip或者域名
     * @param string $username 认证账号
     * @param string $password 认证密码
     * @param int $port 代理服务器的端口，smtp默认25号端口
     * @return boolean
     */

    /**
     * 构造函数
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     */
    public function __construct(string $host, int $port, string $username, string $password)
    {
        $this->_sendServer = $host;

        $this->_port = $port;
        if(!empty($username)) {
            $this->_userName = base64_encode($username);
        }
        if(!empty($password)) {
            $this->_password = base64_encode($password);
        }
    }

    /**
     * 设置发件人
     * @param string $fromMail
     * @return $this
     */
    public function setFromMail(string $fromMail)
    {
        $this->_from = $fromMail;
        return $this;
    }

    /**
     * 设置收件人
     * @param string $toMail
     * @return $this
     */
    public function setToMail(string $toMail)
    {
        if(isset($this->_to)) {
            if(is_string($this->_to)) {
                $this->_to = array($this->_to);
                $this->_to[] = $toMail;
                return true;
            }
            elseif(is_array($this->_to)) {
                $this->_to[] = $toMail;
                return true;
            }
            else {
                return false;
            }
        }
        else {
            $this->_to = $toMail;
            return true;
        }
        return $this;
    }
    /**
     * 设置抄送人
     * @param string $ccMail
     * @return $this
     */
    public function setCcMail(string $ccMail)
    {
        if(isset($this->_cc)) {
            if(is_string($this->_cc)) {
                $this->_cc = array($this->_cc);
                $this->_cc[] = $ccMail;
            }
            elseif(is_array($this->_cc)) {
                $this->_cc[] = $ccMail;
            }
        }
        else {
            $this->_cc = $ccMail;
        }
        return $this;
    }

    /**
     * 设置秘密抄送
     * @param string $bccMail
     * @return $this
     */
    public function setBccMail(string $bccMail)
    {
        if(isset($this->_bcc)) {
            if(is_string($this->_bcc)) {
                $this->_bcc = array($this->_bcc);
                $this->_bcc[] = $bccMail;
            }
            elseif(is_array($this->_bcc)) {
                $this->_bcc[] = $bccMail;
            }
        }
        else {
            $this->_bcc = $bccMail;
        }
        return $this;
    }

    /**
     * 设置发信内容
     * @param string $subject
     * @param string $body
     * @param string $attachment
     * @return $this
     */
    public function setContent(string $subject, string $body, ?string $attachment = null)
    {
        $this->_subject = $subject;
        $this->_body = base64_encode($body);
        if(!empty($attachment)) {
            $this->_attachment = $attachment;
        }
        return $this;
    }

    /**
     * 获取附件MIME类型
     * @access protected
     * @return mixed
     */
    protected function getMIMEType() {
        if(isset($this->_attachment) && file_exists($this->_attachment)) {
            $mime = mime_content_type($this->_attachment);
            if(! preg_match("/gif|jpg|png|jpeg/", $mime)){
                $mime = "application/octet-stream";
            }
            return $mime;
        }
        else {
            return false;
        }
    }

    /**
     * 读取附件文件内容，返回base64编码后的文件内容
     * @access protected
     * @return mixed
     */
    protected function readFile() {
        if(isset($this->_attachment) && file_exists($this->_attachment)) {
            $file = file_get_contents($this->_attachment);
            return base64_encode($file);
        }
        else {
            return false;
        }
    }
    
    /**
     * 构建mail命令
     * @access protected
     * @return array
     */
    protected function makeCommand() {
        $command = array(
            array("HELO sendmail\r\n", 250)
        );
        if(!empty($this->_userName)){
            $command[] = array("AUTH LOGIN\r\n", 334);
            $command[] = array($this->_userName . "\r\n", 334);
            $command[] = array($this->_password . "\r\n", 235);
        }
        $command[] = array("MAIL FROM:<" . $this->_from . ">\r\n", 250);
        $separator = "----=_Part_" . md5($this->_from . time()) . uniqid(); //分隔符
        //设置发件人
        $header = "FROM: 服务<" . $this->_from . ">\r\n";
        //设置收件人
        if(is_array($this->_to)) {
            $count = count($this->_to);
            for($i=0; $i<$count; $i++){
                $command[] = array("RCPT TO: <" . $this->_to[$i] . ">\r\n", 250);
                if($i == 0){
                    $header .= "TO: <" . $this->_to[$i] .">";
                }
                elseif($i + 1 == $count){
                    $header .= ",<" . $this->_to[$i] .">\r\n";
                }
                else{
                    $header .= ",<" . $this->_to[$i] .">";
                }
            }
        }
        else{
            $command[] = array("RCPT TO: <" . $this->_to . ">\r\n", 250);
            $header .= "TO: <" . $this->_to . ">\r\n";
        }
        //设置抄送
        if(isset($this->_cc)) {
            if(is_array($this->_cc)) {
                $count = count($this->_cc);
                for($i=0; $i<$count; $i++){
                    $command[] = array("RCPT TO: <" . $this->_cc[$i] . ">\r\n", 250);
                    if($i == 0){
                        $header .= "CC: <" . $this->_cc[$i] .">";
                    }
                    elseif($i + 1 == $count){
                        $header .= ",<" . $this->_cc[$i] .">\r\n";
                    }
                    else{
                        $header .= ",<" . $this->_cc[$i] .">";
                    }
                }
            }
            else{
                $command[] = array("RCPT TO: <" . $this->_cc . ">\r\n", 250);
                $header .= "CC: <" . $this->_cc . ">\r\n";
            }
        }
        //设置秘密抄送
        if(isset($this->_bcc)) {
            if(is_array($this->_bcc)) {
                $count = count($this->_bcc);
                for($i=0; $i<$count; $i++){
                    $command[] = array("RCPT TO: <" . $this->_bcc[$i] . ">\r\n", 250);
                    if($i == 0){
                        $header .= "BCC: <" . $this->_bcc[$i] .">";
                    }
                    elseif($i + 1 == $count){
                        $header .= ",<" . $this->_bcc[$i] .">\r\n";
                    }
                    else{
                        $header .= ",<" . $this->_bcc[$i] .">";
                    }
                }
            }
            else{
                $command[] = array("RCPT TO: <" . $this->_bcc . ">\r\n", 250);
                $header .= "BCC: <" . $this->_bcc . ">\r\n";
            }
        }
        $header .= "Subject: " . $this->_subject ."\r\n";
        if(isset($this->_attachment)) {
            //含有附件的邮件头需要声明成这个
            $header .= "Content-Type: multipart/mixed;\r\n";
        }
        elseif(false){
            //邮件体含有图片资源的需要声明成这个
            $header .= "Content-Type: multipart/related;\r\n";
        }
        else{
            //html或者纯文本的邮件声明成这个
            $header .= "Content-Type: multipart/alternative;\r\n";
        }
        //邮件头分隔符
        $header .= "\t" . 'boundary="' . $separator . '"';
        $header .= "\r\nMIME-Version: 1.0\r\n";
        $header .= "\r\n--" . $separator . "\r\n";
        $header .= "Content-Type:text/html; charset=utf-8\r\n";
        $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $header .= $this->_body . "\r\n";
        $header .= "--" . $separator . "\r\n";
        //加入附件
        if(isset($this->_attachment)){
            $header .= "\r\n--" . $separator . "\r\n";
            $header .= "Content-Type: " . $this->getMIMEType() . '; name="' . basename($this->_attachment) . '"' . "\r\n";
            $header .= "Content-Transfer-Encoding: base64\r\n";
            $header .= 'Content-Disposition: attachment; filename="' . basename($this->_attachment) . '"' . "\r\n";
            $header .= "\r\n";
            $header .= $this->readFile();
            $header .= "\r\n--" . $separator . "\r\n";
        }
        $header .= "\r\n.\r\n";
        $command[] = array("DATA\r\n", 354);
        $command[] = array($header, 250);
        $command[] = array("QUIT\r\n", 221);
        return $command;
    }

    /**
     * 发送命令
     * @access protected
     * @param string $command 发送到服务器的smtp命令
     * @param int $code 期望服务器返回的响应吗
     * @return boolean
     */
    protected function sendCommand(string $command, int $code) {
        if($this->socket->send($command)){
            if(empty($code))  {
                return true;
            }
            $data = trim($this->socket->recv(10240));
            if($data){
                $pattern = "/^".$code."/";
                if(preg_match($pattern, $data)) {
                    return true;
                }else{
                    $this->errorMsg .= "服务端返回错误:" . $data . "|**| command:";
                    return false;
                }
            }else{
                $this->errorMsg .= "Error: 消息读取失败";
                return false;
            }
        }else{
            $this->errorMsg .= "Error: 消息发送失败";
            return false;
        }
    }

    /**
     * 发出邮件
     * @return array
     */
    public function send()
    {
        $this->socket = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
        if($this->socket->connect($this->_sendServer, $this->_port) == false){
            App::error()->setError($this->_sendServer.':'.$this->_port.'[连接失败]', 500);
        }
        $this->socket->recv(10240);

        $commands = $this->makeCommand();
        foreach ($commands as $value) {
            $this->sendCommand($value[0], $value[1]);
        }
        $this->socket->close();
        return true;
    }

    public function __destruct()
    {

    }
}