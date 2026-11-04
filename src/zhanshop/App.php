<?php
// +----------------------------------------------------------------------
// | flow-course / App类    [ 2021/10/22 4:45 下午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;
use zhanshop\console\Output;
use zhanshop\server\Route;
use zhanshop\util\Phar;

/**
 * @method static Console console()
 * @method static Config config()
 * @method static Cache cache()
 * @method static Log log()
 * @method static Error error()
 * @method static Validate validate()
 * @method static Env env()
 * @method static Database database()
 * @method static Phar phar()
 * @method static Route route()
 */
class App extends Container
{
    /**
     * 应用调试模式
     * @var bool
     */
    protected static $appDebug = false;

    /**
     * 应用开始时间
     * @var float
     */
    protected static $beginTime;

    /**
     * 应用内存初始占用
     * @var integer
     */
    protected static $beginMem;
    /**
     * 应用根目录
     * @var string
     */
    protected static $rootPath = '';

    /**
     * 框架目录
     * @var string
     */
    protected static $kernelPath = '';

    /**
     * 应用目录
     * @var string
     */
    protected static $appPath = '';

    /**
     * Runtime目录
     * @var string
     */
    protected static $runtimePath = '';

    /**
     * 路由定义目录
     * @var string
     */
    protected static $routePath = '';

    /**
     * 类别名的绑定
     * @var string[]
     */
    protected static $bind = [
        'error'           => Error::class,
        'env'             => Env::class,
        'config'          => Config::class,
        'cache'           => Cache::class,
        'log'             => Log::class,
        'validate'        => Validate::class,
        'database'        => Database::class,
        'console'         => Console::class,
        'phar'            => Phar::class,
        'route'           => Route::class
    ];

    /**
     * 构造方法
     * @param string $rootPath
     */
    public function __construct(string $rootPath){
        set_error_handler([Error::class, 'errorHandler'], E_ALL);// 系统错误
        set_exception_handler([Error::class, 'exceptionHandler']); //用户自定义的异常处理函数
        register_shutdown_function([Error::class, 'shutdown']); // Server运行期致命错误
        self::$kernelPath   = __DIR__;
        self::$rootPath     = $rootPath;
        self::$appPath      = self::$rootPath .DIRECTORY_SEPARATOR. 'app';
        self::$runtimePath  = self::$rootPath .DIRECTORY_SEPARATOR. 'runtime';
        self::$routePath    = self::$rootPath .DIRECTORY_SEPARATOR. 'route';
    }

    /**
     * 获取框架目录地址
     * @return string
     */
    public static function kernelPath(){
        return self::$kernelPath;
    }

    /**
     * 获取项目跟目录地址
     * @return string
     */
    public static function rootPath(){
        return self::$rootPath;
    }

    /**
     * 获取app跟目录地址
     * @return string
     */
    public static function appPath(){
        return self::$appPath;
    }

    /**
     * 获取运行目录地址
     * @return string
     */
    public static function runtimePath(){
        return self::$runtimePath;
    }

    /**
     * 获取路由目录地址
     * @return string
     */
    public static function routePath(){
        return self::$routePath;
    }

    /**
     * 获取cpu个数
     * @return int
     */
    public static function cpuNum(){
        $cpuNum = 1;
        // 检查扩展是否存在
        if(extension_loaded('swoole')){
            $cpuNum = swoole_cpu_num();
        }
        return $cpuNum;
    }
}