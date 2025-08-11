<?php

namespace zhanshop\util;

use zhanshop\App;
use zhanshop\Helper;

class Upload
{
    /**
     * 保存上传文件
     * @param array $file
     * @param $saveDir
     * @return string|void
     * @throws \Exception
     */
    public static function putFile(array $file, $saveDir = ''){
        if($file['error'] != 0) App::error()->setError("文件上传错误:".$file['error']);
        if(!$saveDir) $saveDir = App::rootPath().DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.date('Ym');
        Helper::mkdirs($saveDir);
        $infos = pathinfo($file["name"]);
        $newFileName = $saveDir.DIRECTORY_SEPARATOR.md5(microtime(true).$file['size'].$file["name"].rand(1,999)).'.'.$infos['extension'];
        if (move_uploaded_file($file["tmp_name"], $newFileName)) {
            return $newFileName;
        } else {
            App::error()->setError("文件保存失败");
        }
    }
}