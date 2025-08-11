<?php
// +----------------------------------------------------------------------
// | admin / Phar.php    [ 2023/7/7 下午3:54 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util;

use zhanshop\App;

class Phar
{
    protected $imports = [];

    /**
     * 导入
     * @param string $phar
     * @return void
     */
    public function import(string $phar){
        $phar .= '.phar';
        if(isset($this->imports[$phar])){
            return;
        }
        include 'phar://'.App::rootPath().DIRECTORY_SEPARATOR.$phar;
        $this->imports[$phar] = $phar;
    }

    /**
     * 打包
     * @param string $savePath
     * @param string $sourceDir
     * @return void
     * @throws \Exception
     */
    public function pack(string $savePath, string $sourceDir){
        $savePath = rtrim($savePath, DIRECTORY_SEPARATOR);
        if(ini_get('phar.readonly')){
            App::error()->setError("使用Phar需要先在php.ini设置 phar.readonly = Off");
        }
        $composer = $sourceDir.DIRECTORY_SEPARATOR.'composer.json';
        if(file_exists($sourceDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')){
            $indexFile = App::runtimePath().DIRECTORY_SEPARATOR.md5($sourceDir).'.php';
            $autoloadFile = '<?php'.PHP_EOL.PHP_EOL;
            $autoloadFile .= "if ( file_exists(dirname(__FILE__).'/vendor/autoload.php') ) {".PHP_EOL;
            $autoloadFile .= "    require_once dirname(__FILE__).'/vendor/autoload.php';".PHP_EOL;
            $autoloadFile .= "}";
            file_put_contents($indexFile, $autoloadFile);
        }else if(file_exists($composer)){
            $composer = json_decode(file_get_contents($composer), true);

            $autoloadFile = '<?php'.PHP_EOL.PHP_EOL;

            if($composer['autoload']['psr-4'] ?? []){
                $autoloadFile .= '$psr4 = '.var_export($composer['autoload']['psr-4'] ?? [], true).';'.PHP_EOL.PHP_EOL;
                $autoloadFile .= "spl_autoload_register(function (\$class) use(&\$psr4){".PHP_EOL;
                $autoloadFile .= '  foreach($psr4 as $k => $v){'.PHP_EOL;
                $autoloadFile .= '      if(strpos($class, $k) === 0){'.PHP_EOL;
                $autoloadFile .= '          $class = str_replace($k, \'\', $class);'.PHP_EOL;
                $autoloadFile .= '          $classFile = $v .DIRECTORY_SEPARATOR. str_replace("\\\", DIRECTORY_SEPARATOR, $class).'."'.php';";
                $autoloadFile .= '          if (file_exists($classFile)) {'.PHP_EOL;
                $autoloadFile .= '              require_once ($classFile);'.PHP_EOL;
                $autoloadFile .= '              return true;'.PHP_EOL;
                $autoloadFile .= '          }'.PHP_EOL;
                $autoloadFile .= '      }'.PHP_EOL;
                $autoloadFile .= '  }'.PHP_EOL;

                $autoloadFile .= '});'.PHP_EOL.PHP_EOL;
            }

            foreach($composer['autoload']['files'] ?? [] as $k => $v){
                $autoloadFile .= 'include \''.$v.'\';'.PHP_EOL;
            }
            $autoloadFile .= PHP_EOL;

            $indexFile = App::runtimePath().DIRECTORY_SEPARATOR.md5($sourceDir).'.php';
            file_put_contents($indexFile, $autoloadFile);
        }else{
            App::error()->setError("文件夹中未包含composer.json或/vendor/autoload.php不符合打包标准");
        }



        $phar = new \Phar($savePath);
        // /\.(?:css|less)$/
        $phar->buildFromDirectory($sourceDir, '/\.php$/');
        $phar->compressFiles(\Phar::GZ);

        $phar->addFile($indexFile, 'index.php');

        $phar->stopBuffering();

        unlink($indexFile);
    }
}