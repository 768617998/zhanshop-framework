<?php

namespace zhanshop\servevent;

use Swoole\ExitException;
use zhanshop\App;
use zhanshop\console\command\Server;
use zhanshop\helper\Grpc;
use zhanshop\route\Dispatch;
use zhanshop\ServEvent;

/**
 * protoc 命令需要安装
 * sudo apt  install protobuf-compiler
 * apt install prototool
 * brew install prototool
 * protoc --version 验证 protoc 是否安装成功
 * 使用 protoc 自动生成代码
 * protoc --php_out=grpc/ grpc.proto
 *
 * php扩展：grpc,protobuf
 * prototool lint
 * prototool generate
 */
class GrpcServEvent extends ServEvent
{

    public function __construct()
    {
        if(!App::env()->checkExtensions(['grpc', 'protobuf'])){
            exit();
        }
    }

    /**
     * 主进程启动回调
     * @param \Swoole\Server $server
     * @return void
     */
    public function onStart($server) :void{
        // 检查grpc是否安装
        App::env()->checkExtensions(['grpc', 'protobuf']);
    }
    /**
     * http请求
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @param string $group
     * @return void
     */
    public function onRequest($request, $response, $protocol = Server::HTTP, $appName = 'grpc') :void{

        if (!in_array($request->header['content-type'] ?? '', [
            'application/grpc',
            'application/grpc+proto',
        ])) {
            $response->status(400);
            $response->end('<center><h1>400 Bad Request</h1></center><hr><center>zhanshop</center>');
            return ;
        }
        if(($request->server['request_method'] ?? 'GET') != 'POST'){
            $response->status(405);
            $response->end('<center><h1>405 Method Not Allowed</h1></center><hr><center>zhanshop</center>');
            return ;
        }
        $data = '';
        try {
            $dispatch = App::make(Dispatch::class);
            $route = $dispatch->getGrpc($request->server['request_uri']);

            list($class, $method) = $route['handler'];

            list($grpcReq, $grpcResp) = $route['param'];
            $grpcReq = new $grpcReq();
            $grpcResp = new $grpcResp();

            $method = lcfirst($method);
            Grpc::deserialize($grpcReq, $request->getContent());
            App::make($class)->$method($grpcReq, $grpcResp);
            $data = Grpc::serialize($grpcResp);

        }catch (\Throwable $e){
            $response->status(Grpc::getStatus($e->getCode()));
            $data = $e->getMessage().PHP_EOL.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString();
        }

        $response->end($data);
    }
}
/*
protoc可用命令:
--version                   显示版本信息并退出.

-h, --help                  显示帮助信息并退出.

--encode=MESSAGE_TYPE       阅读给定类型的文本格式消息从标准输入并以二进制形式写入到标准输出。消息类型必须在PROTO_FILES或其导入中定义。

--decode=MESSAGE_TYPE       从中读取给定类型的二进制消息标准输入并以文本格式书写到标准输出。消息类型必须在PROTO_FILES或其导入中定义。

--decode_raw                从中读取任意协议消息，标准输入并写入原始标记/值，以文本格式与标准输出配对。不使用时应提供PROTO_FILES旗帜--descriptor_set_in=FILES指定FILES的分隔列表每个都包含一个FileDescriptorSet（描述符.proto中定义的协议缓冲区）。每个PROTO_FILES的FileDescriptor提供的将从这些加载文件描述符集。如果文件描述符出现多次，第一次出现将被使用。

-oFILE,                     将FileDescriptorSet（协议缓冲区，--descriptor_set_out=在descriptor.proto中定义的FILE），包含所有FILE的输入文件

--include_imports           使用--descriptor_set_out时，还包括中输入文件的所有依赖项集合，使得该集合是自包含的。

--include_source_info        使用--descriptor_set_out时，不要从FileDescriptorProto中删除SourceCodeInfo。这导致了更大的描述符包括有关原件的信息源文件中每个decl的位置以及周围的评论。

--dependency_out=FILE       以下格式编写依赖项输出文件按品牌预期。这写的是传递file的一组输入文件路径

--error_format=FORMAT       设置打印错误的格式。FORMAT可以是“gcc”（默认值）或“msvs”（Microsoft Visual Studio格式）。--print_free_field_numbers打印消息的空闲字段号在给定的proto文件中定义。群组共享与父项相同的字段编号空间消息扩展范围计算为占用字段编号。

--plugin=EXECUTABLE         指定要使用的插件可执行文件。通常，protoc在PATH中搜索插件，但您可以指定其他插件不在使用此标志的路径中的可执行文件。此外，EXECUTABLE可以采用以下形式NAME=PATH，在这种情况下，给定的插件名称映射到给定的可执行文件，即使可执行文件自身的名称不同

  --cpp_out=OUT_DIR           生成C++标头和源代码。
  --csharp_out=OUT_DIR        生成C#源文件。
  --java_out=OUT_DIR          生成Java源文件。
  --js_out=OUT_DIR            生成JavaScript源代码。
  --objc_out=OUT_DIR          生成OBJECT-C标题和来源。
  --php_out=OUT_DIR           生成PHP源文件
  --python_out=OUT_DIR        生成Python源文件。
  --ruby_out=OUT_DIR          生成Ruby源文件。
  @<filename>                 从文件中读取选项和文件名。如果指定了相对文件路径，则文件将在工作目录中搜索。--proto_path选项不会影响将搜索此参数文件。的内容文件将在的位置展开@参数列表中的＜filename＞。笔记外壳膨胀不适用于文件的内容（即，您不能使用引号、通配符、转义符、命令等）。每一行对应于单个自变量，即使它包含空格。
*/

