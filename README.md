# zhanshop-php

## 简介
zhanshop-php 是一个基于swoole高性能轻量级框架、全程非阻塞、凭借swoole底层将php阻塞函数重写进行协程非阻塞化和php自身低开销特点爆发强劲的性能，性能较传统基于 PHP-FPM 的框架有质的提升，提供超高性能的同时，也保持着极其灵活的可扩展性， 一键快速生成业务逻辑层代码骨架，只需要编写业务逻辑代码即可，根据注释配置即可生成api文档，对PDO 和 redis连接池进行了封装，同一连接内事务支持，不同连接和不同数据库服务器之前的分布式事务等功能，全程APP容器化管理，数据库操作类和缓存操作类和thinkphp类似，简单易用。

## 完整项目
https://gitee.com/zhangqiquan_admin/zhanshop-php

## server组成
1. 业务层(包含控制器、service、model)
2. crontab (定时任务进程，可将任务丢给task执行)
3. task (task任务)


## 关于附件
目前附件是只传七牛云  修改 config/sns.php 相应配置

## 环境要求
php8.4 以上版本，需要redis扩展的支持， pdo mysql 扩展的支持，swoole6 以上的扩展支持


## 性能表现 （供参考和对比）
本框架http服务器，在我的（本机12核，32G ubuntu22 设备上）使用ab压力测试工具 吞吐量可达38万多/s

压测表现碾压如go语言的Gin框架、python语言的Tornado框架、java语言的Spring Boot等一众框架

## 目录结构
~~~

├─app           应用目录
│  ├─api
│  │  ├─admin       后台管理系统
│  │  ├─index       api/前端页面
│  │  └─.....       其他
│  ├─console        应用层控制台程序
│  ├─constant       常量类的定义
│  ├─exception      协议异常自定义接收处理
│  ├─helper         助手函数类的目录
│  ├─schema         数据表字段配置(常用于后台智能表单智能列表的生成配置)
│  ├─process        自定义进程
│  ├─script         独立运行的php脚本文件
│  ├─crontab        定时任务目录
│  ├─model          模型目录
│  └─task           task任务目录
│
├─config                配置目录  
│  ├─app.php            应用配置
│  ├─cache.php          缓存配置
│  ├─console.php        控制台配置
│  ├─database.php       数据库配置
│  ├─elasticsearch.php  elasticsearch配置
│  ├─middleware.php     中间件的配置 仅http协议下生效
│  ├─share.php          多进程共享表字段配置
│  ├─server.php         server服务器配置
│  ├─sns.php            对接第三方系统配置参数
│  ├─ini.php            ini配置
│  └─log.php            日志配置
├─route                 路由定义目录
│  ├─index              路由定义文件
│  │  └─v1.php          注册的目录版本配置文件
│  ├─admin              路由定义文件
│  │  └─v1.php          注册的目录版本配置文件
│  └─ ...   
│
├─public                WEB前端访问目录
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                Composer类库目录
│  ├─Container.php      单例容器类
│  ├─App.php            APP管理器
│  ├─Config.php         配置参数管理
│  ├─Console.php        控制台程序入口
│  ├─Cache.php          redis缓存管理类     
│  ├─Database.php       数据库操作管理入口类
│  ├─Helper.php         帮助函数
│  ├─Log.php            日志类
│  ├─Middleware.php     控制器中间件管理
│  ├─RequestCache.php   请求缓存【相当于nginx的程序内部缓存】
│  ├─Route.php          路由管理类
│  ├─Service.php        service单例管理
│  ├─Task.php           task任务管理
│  ├─Validate.php       请求参数验证
│  ├─console            控制台程序目录(包含http服务器,后台管理系统等程序)
│  └─ ...  
├─.env.dev              本地开发环境变量示例文件
├─.env.test             测试环境变量示例文件
├─.env.production       生产环境变量示例文件
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─cmd.php               命令行入口文件

~~~

## 框架安装

~~~
composer require zhanshop/framework

~~~


## 控制台使用方法
~~~
php cmd.php

欢迎使用zhanshop控制台系统

用法:   cmd 指令 --参数 参数信息

可用命令：

help                                帮助 - 显示命令的帮助
server                              启动服务器 - 使用该命令可以创建服务器应用
script                              执行脚本 - 单独启动进程方式启动指定程序
phar                                phar打包 - 将vendor项目目录打包成phar并存放在项目中
api:route                           api注解生成路由 - 一键生成基于控制器配置的注解路由

~~~


## 配置开机启动脚本【运行在非容器环境下】

~~~
php cmd.php server poweron

~~~

## 容器环境下启动

~~~
cd docker
./start.sh

~~~

## 演习地址 【IPV6网络环境】
~~~
后台管理系统：
http://jia-old.shuangcloud.com:7201/admin/
账号: admin
密码: admin123

接口服务：
http://jia-old.shuangcloud.com:6201/apiDoc

~~~


## 使用方法


~~~
1.数据模型

<?php
namespace app\model;
use zhanshop\database\Model;

class Abc extends Model
{
    // 设置当前模型的对应数据表
    protected $table = '对应的表名';

    // 设置当前模型的数据库连接
    protected $connection = '连接配置名称';

}
~~~

~~~
2.数据库模型使用案例

App::database()->model('表名')->where(['id' => 123])->find();  // 查询单个数据
App::database()->model('表名')->where(['a' => 123])->finder(); // 分页查询
App::database()->model('表名')->save([...]);  // 新增数据
App::database()->model('表名')->where(['id' => 123])->delete(); // 删除


事务 当闭包中的代码发生异常会自动回滚
App::database()->transaction(function($pdo){
    Test::insert([
        'a' => '1',
        'b' => '2',
        'c' => '3'
    ], $pdo);
    Test::insert([
        'a1' => '1',
        'b' => '2',
        'c' => '3'
    ], $pdo);
});

分布式事务【在数据库开启分布式事务后才可以正常使用】
App::database()->transactionXa(function($pdos){
    Test::insert([
        'a' => '1',
        'b' => '2',
        'c' => '3'
    ], $pdo[0]);
    Test1::insert([
        'a' => '1',
        'b' => '2',
        'c' => '3'
    ], $pdo[1]);
});

3.缓存使用
App::cache()->get(...);
App::cache()->set(...);

更多方法详见 https://blog.csdn.net/demored/article/details/123842206 以及 php官方文档

等等....
~~~

## 交流群
由于微信群无法直接加入，故可先加下方二维码好友，并声明目的，再拉您入群。

微信号： eee7798
![](http://test-cdn.zhanshop.cn/2023313/167870337640814238.jpg)
