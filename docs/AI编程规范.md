# zhanshop 系列 AI 编程规范（框架级基线）

> 适用对象：所有基于 `zhanshop/framework` 的 PHP 项目，以及参与这些项目的 AI 编码助手。
> 维护位置：`zhanshop-framework` 仓库 `docs/AI编程规范.md`。
> 事实基准：v7 分支源码与 README，2026-10-17 实地核验；本文中的 API、签名、执行顺序均以源码为准。
> 项目规范只写「与本文件的差异」，禁止复制本文形成第二份口径。

---

## 0. 使用方式与强制力

### 0.1 项目接入（每个项目只维护差异）

每个项目的 `CLAUDE.md` 顶部必须放下面这段指针，并补齐项目差异：

```markdown
## AI 编程约束（必读）

通用规范以 zhanshop-framework 仓库 `docs/AI编程规范.md` 为准。
冲突优先级：本项目下方「项目差异」> 框架规范 > 框架 README。

项目形态：<API 服务型 / 管理后台型>
本项目差异：
- 目录与命名：<只写不同之处>
- 数据库与表前缀：<只写不同之处>
- 菜单/权限模型：<只写不同之处>
- 验收命令与端口：<只写不同之处>

红线：不改生成物 `route/*`、框架 `vendor/`；菜单/列/按钮只改 `docs/*.sql` 且必须幂等；
用户可控资源键必须校验；禁止静默降级、空 catch、顺手重构；真实集群破坏性操作先确认；
未明确要求不 commit、不 push。
```

### 0.2 强制力

- 本文中的「必须」「禁止」是硬约束；「推荐」是默认做法，偏离时必须在任务书里写明原因。
- 与项目 `CLAUDE.md` 冲突时，取更严格者；项目显式声明的差异优先于本文件。
- 偏离本规范必须在任务书的「影响面与风险」中写明：为什么偏离、影响面、替代方案、谁兜底。
- 无法验证的结论不准写进任务书；不确定写「待核验」。

### 0.3 动手前

1. 先读目标项目 `CLAUDE.md` 与本文。
2. 复制项目任务书模板，写清现状核验（带 `文件:行号`）、改动清单、验收标准、回滚方案。
3. 再改代码。禁止先改代码后补任务书。

---

## 1. 技术栈与版本策略

| 项 | 约束 |
| --- | --- |
| 语言 | PHP。`composer.json` 当前约束 `>=8.1`；生产运行环境以项目部署为准（README 推荐 PHP 8.4+、Swoole 6+）。项目必须明确并锁定实际运行版本。 |
| 运行时 | Swoole 常驻进程；HTTP、WebSocket、TCP、JSON-RPC、gRPC、MCP 等协议共用同一组 worker。 |
| 新代码 | 必须使用当前运行版本支持的新特性：严格类型、返回类型、`match`、枚举、readonly、构造器提升等。 |
| 新增依赖 | 必须选最新稳定版、锁定版本、说明用途；优先使用框架已有能力。 |
| 存量依赖 | 禁止顺手升级。升级必须单独立项，附回归验证。 |
| 重复能力 | 禁止引入与框架 `Database`、`Cache`、`Validate`、`Log`、HTTP 客户端等重复的库。 |

硬约束：

- 所有业务 PHP 文件必须 `declare(strict_types=1);`。
- 禁止在常驻进程里保存跨请求可变的静态状态；需要共享时用 `RamCache` 或 `Swoole\Table`。
- 禁止在协程里执行阻塞 IO（`sleep`、同步文件下载、同步外部 HTTP）；使用框架异步客户端。

---

## 2. 项目形态与骨架选择

一个仓库只保留一套主链路；不得为「以后可能复用」预建另一套空层。

| 项目形态 | 入口 | 主链路 | 适用 |
| --- | --- | --- | --- |
| API 服务型 | HTTP 方法 + 注解路由 | controller → service → repository → model/DB | 对外接口、交易、回调、开放平台 |
| 管理后台型 | 菜单 ID | controller → Finder → provider → Client | 菜单驱动后台、资源运维台 |

补充规则：

- API 服务型出现菜单驱动页面时，局部引入 Finder；不要把管理后台 Finder 改造成 Controller。
- 管理后台型不得为了「像 API 项目」而给每个菜单配空 service/repository；有编排才建 service，有 SQL 复用才建 repository。
- 目录名、版本号、app 名必须与 `composer.json` 的 PSR-4 和 `config/` 保持一致，禁止自造第二套。

---

## 3. 路由

### 3.1 唯一来源：控制器方法注解

路由由 `php cmd.php api:route` 扫描 `app/api/{app}/{version}/controller/*.php` 生成，唯一来源是方法上的 `@api` 注解。

```php
/**
 * @api POST create 创建交易
 * @apiHeader string token 用户token
 * @apiParam array items 商品列表
 * @apiParam string pay_way 支付方式
 * @apiSuccess string order_id 订单号
 * @apiMiddleware \zhanshop\server\middleware\SignAuth::class,SessionAuth::class,UserAuth::class
 * @apiGroup 交易模块
 * @apiDescription 多商品合并下单；库存/名额不足时整单失败。
 */
public function postCreate(Request $request, Response $response)
```

| 注解 | 是否必需 | 作用 |
| --- | --- | --- |
| `@api METHOD uri title` | 必需 | `METHOD`、`uri`、`title` 三者缺一，该方法不会生成路由。 |
| `@apiMiddleware` | 可选 | 路由级中间件，逗号分隔且列表内禁止空格；每个值必须以 `::class` 结尾。不含 `\` 的短类名由框架拼成 `\app\middleware\X::class`，含 `\` 的原样写入生成的路由文件。 |
| `@apiHeader` / `@apiParam` / `@apiSuccess` / `@apiError` | 可选 | 只生成 OpenAPI 文档，**不做任何参数校验**。 |
| `@apiGroup` / `@apiDescription` | 可选 | 文档分组与描述。 |

硬约束：

- `@apiParam` 是文档，不是校验；真正的校验必须调用 `validateRule()` 或 `validateData()`。
- `@apiMiddleware` 的值会**原样写进生成的路由文件**，必须写成合法的 PHP 类名表达式（以 `::class` 结尾）；漏写 `::class` 会生成未定义常量，路由文件 include 时抛 `Error`，服务启动即失败。
- `@apiMiddleware` 的解析正则是取第一个空白前的片段；逗号后加空格会**静默丢弃**后续中间件，列表内禁止空格。
- 注解里禁止写密码、密钥、真实手机号等敏感示例值。

### 3.2 生成规则与 URL

源码事实（`src/zhanshop/console/command/ApiRoute.php`、`src/zhanshop/server/route/Group.php`）：

- 分组名 = 控制器类名的 `lcfirst`。`Trades` → `/trades`。
- 规则名 = `@api` 的 `uri` 第一段。`@api POST create` → `create`。
- 最终 URL = `/{version}/{分组}.{规则名}`。`Trades` + `@api POST create` → `/v1/trades.create`。
- 路径参数写在 `@api` 的 `uri` 中：`@api GET detail/{id}` → `/v1/trades.detail/{id}`，生成时自动进 `extra(['id'])`，方法内用 `$request->getData('id')` 读取。
- 同一路径的不同请求方法必须写成不同方法：`getCreate`、`postCreate`、`deleteCreate`；禁止在一个方法里 `switch` 请求方式。
- `@api` 的 `uri` 禁止再重复控制器名。写 `@api POST trades.create` 会生成 `/v1/trades.trades.create`。
- `route/` 是生成物，头部标注「系统生成」，**禁止手改**。改路径就改注解再运行 `php cmd.php api:route`。
- 生成文件与注解不一致时，以重新生成的结果为准；禁止手改掩盖。

### 3.3 版本与文件名

- 版本目录名 `v1`、`v2` 直接作为 URL 的第一段；`v1_1` 会转换为 `v1.1`。
- 生成物为 `route/{app}/{version}.php`，例如 `route/admin/v1.php`。
- 新增版本必须同时确认入口、配置和 Nginx/网关转发，不允许只加目录。

### 3.4 禁止

- 禁止在控制器外手写 `App::route()->rule()` 补充业务路由（框架级特殊入口除外，且必须在任务书写明）。
- 禁止为同一 URI 注册多个相同方法。
- 禁止把路由参数当普通 GET 参数；路径参数只从 `extra()` 注入的 `getData()` 读取。

---

## 4. 中间件

### 4.1 签名

框架实际调用是 **两参数**：

```php
namespace app\middleware;

use zhanshop\App;
use zhanshop\server\Request;

class UserAuth
{
    public function handle(Request $request, \Closure $next)
    {
        $token = $request->header('token');
        if ($token === false || strlen($token) <= 4) {
            App::error()->setError('用户token缺失', 10001);
        }

        $payload = self::parseToken($token);
        $request->setData('user.user_id', (int)$payload['user_id']);
        $request->setData('user.token_id', (string)$payload['token_id']);

        return $next($request);
    }
}
```

硬约束：

- 必须 `return $next($request)`；禁止只调用不返回。
- 禁止照抄 README 里的三参数示例（`Response $response`）；框架只传两个参数，以源码为准。
- 中间件抛错用 `App::error()->setError($msg, $code)`；禁止把堆栈、路径、密钥写进 message。
- 中间件只做横切：认证、限流、请求日志、响应头。禁止读写业务表、写业务状态、调业务 service。
- 后续中间件和控制器只读前面写入的 `setData`；键名必须稳定，推荐 `user.user_id`、`user.token_id`、`request.trace_id`。
- `getData($name)` 在值为 `null` 时会直接报 400；可选上下文必须给默认值，如 `getData('user.user_id', 0)`。

### 4.2 注册与执行顺序

- 路由级：`@apiMiddleware` 声明。
- 全局级：`config/middleware.php`，按 app 名分组：

```php
return [
    'admin' => [
        \app\middleware\RequestLog::class,
        \app\middleware\AdminAuth::class,
    ],
    'index' => [
        \app\middleware\RequestLog::class,
    ],
];
```

执行顺序（源码事实）：先按 `@apiMiddleware` 声明顺序执行路由中间件，再按 `config/middleware.php` 中该 app 的数组顺序执行全局中间件。框架内部会先 `array_reverse` 再 `array_reduce` 包裹，不要据此误判为「倒序执行」。

硬约束：

- 有身份依赖的中间件必须排在认证之后。
- 全局中间件必须对「未登录、无权限、畸形 token」也安全：不得因为缺失字段产生 PHP 警告或 500。
- 中间件顺序改变必须做全量回归（至少覆盖登录、未登录、过期 token 三条路径）。

---

## 5. 控制器

### 5.1 API 服务型控制器

控制器只做四件事：取身份 → 校验参数 → 调 service → 返回信封。

```php
declare(strict_types=1);

namespace app\api\index\v1\controller;

use app\api\index\v1\service\TradesService;
use app\library\BaseController;
use zhanshop\App;
use zhanshop\server\Request;
use zhanshop\server\Response;

class Trades extends BaseController
{
    public function postCreate(Request $request, Response $response)
    {
        $data = $request->validateRule([
            'items'   => 'required | array',
            'pay_way' => 'required | string',
            'remark'  => 'string',
        ])->getData();

        foreach ($data['items'] as $item) {
            $request->validateData($item, [
                'type'     => 'required | string',
                'sku_id'   => 'required | int',
                'quantity' => 'required | int',
            ])->getData();
        }

        $userId = (int)$request->getData('user.user_id');
        $result = App::make(TradesService::class)->postCreate($userId, $data);

        return $this->result($result);
    }
}
```

硬约束：

- 用户身份只取中间件写入的上下文；禁止从请求体接收 `user_id`。
- 控制器禁止出现 `App::database()`、`new 第三方SDK()`、`App::make(XxxRepository::class)`、金额计算、状态机判断。
- 框架只负责发送控制器返回值，不自动生成业务信封；系列项目必须通过项目 `BaseController` 的统一 `result()` 输出 `{code,msg,data}` 信封。
- 不自己拼信封；错误统一 `App::error()->setError()`。
- 依赖必须用 `App::make()` 获取，禁止 `new` 业务类；纯值对象除外。

### 5.2 管理后台型控制器

管理后台的控制器是通用入口，不按菜单建控制器。新增资源菜单时：

- 禁止改 `route/`、`Index.php`、`Controller.php`、`BaseFinder`。
- 只新增 `app/api/admin/v1/service/finder/{MenuId}Finder.php`，需要时补 provider/Client。
- `_method` 选择 Finder 方法；Finder 方法必须对未知动作返回明确错误，禁止静默回落到默认分支。

### 5.3 请求参数读取语义

源码事实（`src/zhanshop/server/Request.php`）：

- `param()`：请求存在 POST body 时只返回 POST；否则只返回 GET。**不会合并 GET 和 POST**。
- `validateRule()` 内部使用 `param()`，因此它看不到 JSON raw body。
- JSON 请求必须用 `getJson()` 取数，再 `validateData($json, $rules)` 校验。
- `get($name, $default)` / `post($name, $default)` 按各自来源读取。
- `getData($name)` 在值为 `null` 时直接报 400；读可选值必须给默认值。
- `getData()` 同时承载路由 `extra` 参数和中间件写入的上下文，来源不要混用键名。

---

## 6. 参数校验

规则串格式：`'字段' => 'required | int | min:0'`，规则之间必须是 ` | `（空格、竖线、空格），参数用 `:`。

| 规则 | 格式 | 行为 |
| --- | --- | --- |
| `required` | `required` | 值为 `''` 或 `null` 时报错（PHP 8 下 `0`、`'0'` 通过） |
| `int` | `int` | 非整数报错；结果转换为 `int` |
| `number` | `number` | 非数字报错；结果转换为 `float` |
| `float` | `float` | 非浮点报错；结果转换为 `float` |
| `string` | `string` | 非字符串报错；缺失时为空字符串 |
| `array` | `array` | 非数组报错；缺失时转换为 `[]` |
| `max` / `min` | `max:N` / `min:N` | 数值上限/下限 |
| `between` | `between:min,max` | 数值区间 |
| `length` | `length:min,max` | 字符串长度区间 |
| `email` / `url` / `mobile` / `card` | 同名 | 格式校验 |
| `date` / `time` | 同名 | 日期/时间格式校验 |
| `regex` | `regex:/pattern/` | 正则校验 |
| `func` | `func:callback` | 自定义函数校验 |

硬约束：

- 校验只做一次：入口校验过的字段，service/repository 不重复做格式校验；归属与业务前提在 service 校验。
- 嵌套数组必须逐项 `validateData()`，禁止用正则拼装、禁止只校验第一项。
- 可选字段缺失会按类型转换：`int/number/float` 为 0，`string` 为 `''`，`array` 为 `[]`；取默认值仍写 `$data['x'] ?? 默认值`。
- 错误提示必须能给用户看：使用中文业务字段名和明确规则，禁止只返回 PHP 异常文本。
- 允许的枚举值必须在 service 用常量校验，禁止只依赖前端下拉框。
- 禁止把 `@apiParam` 当校验；禁止相信客户端传入的金额、单价、用户身份、状态。

---

## 7. Service（业务编排）

### 7.1 职责

- 只做编排：组合 service / provider / repository，确定事务边界与失败补偿。
- 方法签名用纯数据：主体在前，选项/上下文在后并给默认值。
- **禁止接收 `Request` / `Response`**；需要请求上下文时由控制器提取后传入。
- 多步流程用编号注释（1..N），每步只做一件事。
- 跨 service 用 `App::make()`，禁止 `new`。

### 7.2 骨架（主流程 + 补偿）

```php
/**
 * 创建交易：查商品 → 算金额 → 写订单 → 锁资源 → 调支付
 */
public function postCreate(int $userId, array $input): array
{
    // 1. 业务前提校验（枚举、数量、状态）
    if (empty($input['items'])) {
        App::error()->setError('商品列表不能为空', ApiCode::NOT_ACCEPTABLE);
    }
    foreach ($input['items'] as $item) {
        if (($item['quantity'] ?? 0) <= 0) {
            App::error()->setError('商品数量必须大于0', ApiCode::NOT_ACCEPTABLE);
        }
    }

    // 2. 查商品 + 计算金额（纯计算交给 provider，查询交给 repository）
    $items = App::make(ProductService::class)->getDetails($input['items']);
    $amounts = App::make(PriceCalculator::class)->calculate($items);

    // 3. 多表写入放在 repository 的事务里
    $orderResult = App::make(OrderRepository::class)->createOrder($userId, $items, $amounts);

    // 4. 外部副作用：锁资源、调网关；失败必须补偿后原样上抛
    try {
        App::make(ResourceLocker::class)->lock($items);
        $payResp = App::make(PaymentGateway::class)->pay(
            $input['pay_way'],
            $orderResult['order_id'],
            $amounts['pay_amount']
        );
    } catch (\Throwable $e) {
        try {
            $this->compensate($orderResult['order_id']);
        } catch (\Throwable $ce) {
            App::log()->error("补偿失败 order={$orderResult['order_id']}: {$ce->getMessage()}");
        }
        throw $e;
    }

    return array_merge($payResp, ['order_id' => $orderResult['order_id']]);
}
```

硬约束：

- 补偿失败只记 `error` 日志；**原异常必须继续上抛**，禁止吞掉。
- 外部 IO 禁止放进 DB 事务：先落库、再调外部、失败补偿。
- 影响资金/状态一致性的失败必须 `error` 级别；可恢复的外部失败才允许 `warning` 后继续。
- 事务外的日志写入失败不得回滚主流程；日志调用单独 `try/catch`。
- service 里禁止写 SQL；需要数据一律走 repository 或 model。

### 7.3 事务与幂等

- 多表写必须事务：`App::database()->transaction(function ($pdo) use (...) { ... }, '连接名')`。
- 事务内所有读写必须传 `$pdo`，保证同一连接；事务外禁止调用「事务专用」repository 方法。
- 状态迁移必须原子 UPDATE + 受影响行数，禁止「先 find 再 update」：

```php
$affected = App::database()->table('trades_payment', 'trade')
    ->where(['pay_id' => $payId, 'pay_status' => 'pending'])
    ->update(['pay_status' => 'paid', 'pay_time' => $payTime, 'trade_no' => $tradeNo]);

if ((int)$affected === 0) {
    return; // 已被处理：重复回调幂等返回成功
}
```

- 回调、重试、消息消费入口必须幂等：同一业务单号重复到达只生效一次。
- 幂等判断必须靠数据库原子操作，禁止靠「先查后写」。
- 状态值来自 `app/constant/*`，禁止在 service 里硬编码 `'paid'`、`0/1/2`。

### 7.4 金额与时间

- 金额统一字符串 + `bcadd/bcsub/bcmul/bccomp(..., 2)`；禁止 float 做金额运算或比较。
- 分摊优惠按小计比例计算，最后一份兜底尾差；单份优惠不超过小计；结果不得为负。
- 时间统一 Unix 秒；只在解析第三方时间字符串时用 `strtotime`，落库前转 `int`。
- 展示层需要格式化时在控制器/前端处理，不改变 service 的输出类型。

---

## 8. Repository / Model / Query

### 8.1 职责

- repository 只做数据访问，返回数组或标量；按用例命名方法（`createOrder` / `findItems` / `claimPaid`）。
- 禁止把 Query Builder 暴露给上层。
- 结构数据允许 `findOrFail()` 抛「不存在」；业务规则不在 repository 判断。
- repository 不调 service/provider，不读配置、不验签、不发外部请求。

### 8.2 常用 API（源码事实）

```php
App::database()->table('trades_order', 'trade')   // 每次新建 Query
App::database()->transaction($callback, 'trade')  // $callback($pdo)
App::database()->query($sql, $bind, 'trade')      // 原生查询
App::database()->execute($sql, $bind, 'trade')    // 原生写入
```

| 方法 | 返回 | 说明 |
| --- | --- | --- |
| `find($pdo = null)` | 数组或 `null` | 单条 |
| `findOrFail($pdo = null)` | 数组 | 不存在时抛 404 |
| `select($pdo = null)` | 数组列表 | 多条 |
| `selectOrFail($pdo = null)` | 数组列表 | 空结果抛 404 |
| `finder($page, $limit = 20, $pdo = null)` | `['list' => [], 'total' => int]` | 分页；没有 `page()` 方法 |
| `value($field, $pdo = null)` | 标量或 `null` | 单字段 |
| `column($field, $key, $pdo = null)` | 数组 | 键值映射 |
| `count($field = '*', $pdo = null)` | int | 计数 |
| `insert(array $data, $pdo = null)` | 受影响行数 | 返回 float，比较时转 int |
| `insertGetId(array $data, $pdo = null)` | 自增 ID | 返回 float，使用时转 int |
| `insertAll(array $data, $bool = false, $pdo = null)` | 受影响行数 | 批量写入 |
| `update(array $data, $pdo = null)` | 受影响行数 | 返回 float，比较时转 int |
| `delete($pdo = null)` | 受影响行数 | model 配置软删除时转 UPDATE |

### 8.3 查询约束

- 批量查询用 `whereIn`；批量写入按类型分组，禁止在循环里单条查询（N+1）。
- 动态条件必须绑定参数：`whereRaw('title like :kw', ['kw' => '%' . $kw . '%'])`；禁止把变量拼进 SQL。
- 排序字段必须来自白名单；用户可控的 `order` 参数禁止直接传入。
- 分页必须服务端完成；禁止全量拉取后在前端过滤。
- `whereRaw` 的绑定值仍要经过类型校验，禁止把用户输入当字段名。

### 8.4 事务约束

```php
App::database()->transaction(function ($pdo) use ($userId, $items, $amounts, $orderId, &$itemIds) {
    App::database()->table('trades_order', 'trade')->insert([
        'order_id'    => $orderId,
        'user_id'     => $userId,
        'pay_amount'  => $amounts['pay_amount'],
        'status'      => OrderStatus::PENDING,
        'create_time' => time(),
    ], $pdo);

    foreach ($items as $item) {
        $itemIds[] = (int)App::database()->table('trades_order_item', 'trade')->insertGetId([
            'order_id' => $orderId,
            'sku_id'   => $item['sku_id'],
            'quantity' => $item['quantity'],
        ], $pdo);
    }
}, 'trade');
```

- 事务回调内的每一次读写都必须传 `$pdo`。
- 事务内禁止外部 HTTP、消息发送、文件上传等 IO。
- 事务回调必须可重入：内部不做依赖外部状态的副作用。

### 8.5 Model 的已知限制

源码事实：`Model::__callStatic()` 为每个模型类缓存一个实例，该实例复用一个 `Query`；`Query` 的查询条件不会在每次执行后自动清空。常驻进程下反复使用 `Model::where(...)` 会让条件、`field`、`order` 跨请求累积——静态链式调用路径上是必然的，不是概率问题。

硬约束：

- 并发路径和循环路径优先使用 `App::database()->table('表', '连接')`，它每次新建 Query。
- 禁止在循环里反复 `Model::where(...)`：每轮都会往同一个 Query 上叠加条件。
- 必须使用 Model 时，每次操作新建实例，或确认该次调用链只执行一次、不残留条件。
- 禁止把 Model 查询对象保存到静态属性或长生命周期容器。
- 已知缺陷：`Query::withTrashed()` 缺少 `return $this`（`Query.php:39-41`），不能链式调用；需要连同软删除数据一起查询时，在 Model 子类中把 `$deleteTime` 设为 `''`。

---

## 9. Provider（领域能力）

provider 按能力分三类，落到 `provider/` 目录，但约束不同。

| 类型 | 典型类 | 职责 | 硬约束 |
| --- | --- | --- | --- |
| 纯计算 | `PriceCalculator` | 金额、折扣、分摊、时长 | 无 IO/DB/状态；输入确定则输出确定；必须可单测 |
| 资源锁 | `ResourceLocker` | 库存/名额/房态的 lock/release | 原子 UPDATE + 条件；lock/release 成对；release 幂等 |
| 第三方网关 | `PaymentGateway` | 支付、短信、对象存储等外部系统 | 按渠道 `match` 分发；配置来自 env/config/DB；验签与响应归一化；不写业务状态 |

硬约束：

- provider 禁止读 `Request`、禁止写响应、禁止返回 `{code,msg,data}` 信封。
- 密钥/商户号只从 `App::env()`、`config/*` 或配置表读取；禁止写死，禁止进日志。
- 第三方返回必须归一成稳定结构（成功标志、业务单号、必要的原始参数），上层不解析第三方私有字段。
- 同一渠道的 pay/close/refund/callback 放在同一个 provider 内，按渠道用 `match` 分发；新增渠道只加分支，不改调用方。
- 回调验签在 provider 完成，业务处理委托给 service；回调必须幂等。
- provider 禁止持有请求级状态；Swoole 常驻进程下禁止静态可变属性。
- 资源锁的 release 必须有「是否真正锁定过」的标记（如 `plan_locked`），避免重复释放。
- 外部调用失败后由 service 决定补偿；provider 只负责把错误准确抛出。

---

## 10. 管理后台型：Finder 与菜单驱动 UI

### 10.1 请求链路

```
浏览器
  → POST /v1/index.table/{menuId}?_method=meta|GET|create|edit|delete|...
  → 通用 controller
  → 权限校验 + 按 menuId 实例化 {menuId}Finder
  → Finder 方法
  → provider
  → extend/.../XxxClient
```

- `menuId` 同时是路由参数、Finder 类名前缀、权限判定键。
- 类文件不存在时回落到通用 Finder 属于框架/项目既有行为；新增资源菜单必须显式提供 Finder，禁止依赖回落掩盖未实现。

### 10.2 菜单与按钮数据

- 菜单树存 `system_menu`；`meta` 必须是合法 JSON。
- 资源菜单 `target='table'`，`meta` 至少包含 `table_names`、`key_field`、`label_field`。
- 按钮是 `parent_id` 指向菜单的子行，`target` 只能是 `head_button` 或 `row_button`。
- 按钮行为由 `meta.event` / `meta.api` / `page` 决定；禁止在前端写死。
- `meta.visible` 是客户端 JS 表达式，由前端动态函数构造器求值（传入 `row` 后返回表达式结果）；空字符串视为真。写 SQL 时必须正确转义单引号。
- 列定义来自 Finder 的 `meta()`；按钮文案/图标/显隐只改 `docs/*.sql`，不改前端通用组件。

### 10.3 Finder 约束

- 一个菜单一个 Finder；方法名即 `_method` 协议，禁止一个方法内 `switch` 多种动作。
- Finder 只做菜单粒度编排：参数校验、权限/归属校验、调 provider、组装返回；禁止直接拼 K8s/第三方请求。
- 列表查询优先交给服务端过滤（labelSelector/fieldSelector），禁止全量拉取后客户端过滤。
- 返回结构必须稳定：列表返回 `list` / `total`；详情返回明确字段，禁止把第三方原始对象直接透出。
- 权限校验必须在服务端完成；前端隐藏按钮不构成权限控制。

### 10.4 前端

- 通用组件（`public/admin/js/layer/module/zhanshop/*`）默认不改；改前必须说明影响面并对全部菜单回归。
- 资源特有的复杂交互放 `public/admin/src/module/{模块}/*.html` 业务页。
- 业务页起步：`ready()` + `layui.use([...])` + 从 URL 取参数 + 从父窗口取 menuId + 框架请求封装。
- 所有用户可控内容渲染前必须 `esc()`；禁止 `innerHTML` 直接拼接未转义数据。
- 禁止在前端写死菜单、列、按钮、枚举文案；枚举从接口/元数据获取。
- iframe、SSE、WebSocket 必须在页面销毁时关闭并清理定时器，防止常驻页面泄漏。

---

## 11. 数据库与表设计

### 11.1 命名

| 对象 | 规则 | 示例 |
| --- | --- | --- |
| 表名 | snake_case、单数、全项目统一前缀 | `system_menu`、`trades_order` |
| 字段名 | snake_case | `user_id`、`create_time` |
| 主键 | `id` | `id` |
| 普通索引 | `idx_<表>_<字段>` | `idx_trades_order_user_id` |
| 唯一索引 | `uniq_<表>_<字段>` | `uniq_user_mobile` |
| 外键 | 不建物理外键 | 由 service 保证一致性 |

- 表名前缀由项目在「项目差异」中声明，同一项目必须统一；禁止同一项目混用多套前缀。
- 禁止使用数据库保留字（`order`、`group`、`key`、`desc` 等）作为表名/字段名。
- 禁止缩写歧义命名（`usr`、`cnt`、`amt`）；金额用 `amount`、`pay_amount`，时间用 `_time` 后缀。

### 11.2 标准字段

每张业务表必须具备：

| 字段 | 类型 | 约束 |
| --- | --- | --- |
| `id` | 整型自增主键 | 不自增的业务单号另建唯一索引，不复用主键 |
| `create_time` | int | Unix 秒，`NOT NULL DEFAULT 0` |
| `update_time` | int | Unix 秒，`NOT NULL DEFAULT 0` |

使用软删除的表必须增加 `delete_time` int，`NOT NULL DEFAULT 0`；0 表示未删除。

按需增加：

- `status`：tinyint，值来自 `app/constant/*`，必须有注释。
- `sortrank`：int，`NOT NULL DEFAULT 50`；数值越大越靠前或按项目约定。
- 业务单号：varchar，必须有唯一索引。

### 11.3 类型

| 数据 | 类型 | 约束 |
| --- | --- | --- |
| 金额 | `decimal(12,2)`（SQLite 用 `NUMERIC`/`DECIMAL`） | PHP 侧字符串 + `bc*`，禁止 float |
| 布尔 | `tinyint(1)` / `INTEGER` | 只用 0/1，禁止 `'Y'/'N'` |
| 时间 | `int` | Unix 秒；历史项目用 datetime 必须在项目差异中声明 |
| 枚举 | `tinyint` 或 `varchar` | 值必须来自常量类 |
| 短文本 | `varchar(255)` | 超长说明禁止塞 varchar |
| 长文本 | `text` | 必须限制入口长度 |
| JSON | `text` / `json` | 写入前 `json_encode`，读取后校验 `json_decode` 结果 |
| 外部系统标识 | `varchar(253)` | 按对应平台规则校验；K8s 资源名见 §14 的 DNS-1123 要求 |

### 11.4 索引与约束

- 主键、唯一业务键、高频查询条件必须有索引；索引列顺序按查询条件顺序。
- 低选择性字段（如 `status`）不得单独建索引，必须与排序字段或其它过滤条件组成联合索引。
- 唯一约束必须落到数据库；禁止只靠 service 判重。
- 金额、状态、时间字段必须 `NOT NULL DEFAULT`；禁止用 NULL 表达业务含义。
- 禁止物理外键：删除/归档顺序由 service 编排。

### 11.5 DDL 与变更

- 所有结构变更必须写成 `docs/*.sql`，禁止手改数据库结构后不留脚本。
- 脚本必须可重复执行，按数据库分别处理：
  - SQLite：`CREATE TABLE IF NOT EXISTS`、`CREATE INDEX IF NOT EXISTS`、`INSERT OR IGNORE`。
  - MySQL：`CREATE TABLE IF NOT EXISTS`、`INSERT ... ON DUPLICATE KEY UPDATE`；**MySQL 8.0 不支持 `CREATE INDEX IF NOT EXISTS`**，必须先查 `information_schema.statistics` 判存再建索引。
- 脚本头部必须写：用途、执行方式、回滚方式。
- DML 变更用 `BEGIN; ... COMMIT;` 包裹；DDL 在 MySQL 下会隐式提交，**事务无法回滚 DDL**，必须变更前备份并单独提供回滚脚本。SQLite 的 DDL 可随事务回滚。
- 执行后必须验证：表存在、字段类型、索引、菜单 JSON 合法。
- 禁止把测试产生的数据、`runtime/` 副产物随代码提交。

```bash
sqlite3 runtime/app.db "PRAGMA table_info(trades_order);"
sqlite3 runtime/app.db "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='trades_order';"
sqlite3 runtime/app.db "SELECT id, json_valid(meta) FROM system_menu WHERE id='10001';"
```

### 11.6 SQLite 注意事项

- 单文件数据库，写操作串行；长事务会阻塞其他写入，事务内禁止外部 IO。
- 不支持 MySQL 的 `SHOW FULL COLUMNS`，schema 字典必须手写并维护。
- `ALTER TABLE` 能力有限；复杂变更必须走「建新表 → 迁数据 → 改名」并写清回滚。
- 数据库文件被 git 跟踪的项目，禁止提交运行期写入的测试数据。

---

## 12. Schema 字典

`app/schema/<表>.php` 用于后台智能表单/列表，必须与真实表结构一致。

```php
<?php

return [
    'id' => [
        'field'      => 'id',
        'title'      => 'ID',
        'type'       => 'int',
        'input_type' => 'readonly',
        'in_list'    => true,
        'width'      => 80,
    ],
    'name' => [
        'field'      => 'name',
        'title'      => '名称',
        'type'       => 'varchar',
        'input_type' => 'text',
        'in_field'   => true,
        'in_list'    => true,
        'search'     => true,
        'sort'       => true,
    ],
    'status' => [
        'field'      => 'status',
        'title'      => '状态',
        'type'       => 'tinyint',
        'input_type' => 'select',
        'in_field'   => true,
        'in_list'    => true,
        'value'      => [
            1 => '启用',
            0 => '禁用',
        ],
    ],
];
```

硬约束：

- 第一个字段必须是主键。
- `field` 必须与数据库字段名完全一致；`title` 用中文业务名。
- `type` 用数据库类型；`input_type` 限定为 `text`、`textarea`、`select`、`radio`、`checkbox`、`number`、`readonly` 等项目已支持的类型。
- `value` 必须是「值 => 文案」映射；枚举值必须与常量类一致。
- `in_list`、`in_field`、`search`、`sort`、`width` 按列表/表单实际需要设置，禁止全量打开。
- 表结构变更后必须同步 schema；提交前做字段一致性检查。
- 框架 `SchemaService::create()` 依赖 MySQL `SHOW FULL COLUMNS`；SQLite 项目必须手写 schema，禁止假设能自动生成。

---

## 13. 错误码、异常与日志

### 13.1 错误码

- 错误码集中在 `app/constant/ApiCode.php`（或项目同名常量类）：4xx 沿用 HTTP 语义，业务码从 10000 起，一行一注释。
- 已发布编码不复用、不改号；新增编码必须避开已占用区间。
- 错误统一 `App::error()->setError($msg, $code)`；条件断言用 `App::error()->assert($cond, $msg, $code)`。
- 禁止 `throw new \Exception('...')` 让上层猜错误码。
- 鉴权失败使用 `TOKEN_*` 系列错误码；禁止把解密异常、堆栈、服务器路径返回前端。

### 13.2 异常渲染

- 项目的全局异常渲染类（框架调用其 `handle($request, $error)`）必须继承 `\zhanshop\Error`，输出统一信封并记录 URL、IP、code、msg、trace、runtime。
- 请求体、响应体在异常记录中必须截断，单字段不超过 4KB。
- 未授权调用方可见的错误消息必须脱敏，去掉绝对路径、SQL、类名细节。
- 禁止空 catch。捕获后必须满足其一：记录日志、执行补偿、原样上抛。

### 13.3 日志

```php
App::log()->debug('...');
App::log()->info('...');
App::log()->warning('...');
App::log()->error('...');
```

- 禁止记录 `token`、`password`、`secret`、完整证件号、完整手机号、支付密钥。
- 请求日志必须对敏感字段脱敏，并对 raw body / 响应做长度截断；记录完整 header 属于现状风险，禁止复制到新项目。
- 影响资金/状态一致性的失败必须 `error`；可恢复失败才允许 `warning`。
- 日志上下文用结构化字段，禁止把整个对象 `var_export` 进日志。

---

## 14. 安全

- 身份只信认证中间件写入的上下文；资源归属校验必须在 service 完成。
- 所有进入 SQL 的动态值必须绑定参数；字段名/排序必须白名单。
- 所有进入 URL、文件路径、命令、K8s 资源名的用户输入必须先校验再使用。
- K8s 资源键（namespace、name、label 值等）必须做 DNS-1123 校验；校验失败直接拒绝，禁止静默替换。
- 禁止把用户输入拼接进 shell 命令；确需调用外部命令时使用参数数组、转义参数并做白名单。
- 前端所有用户可控内容必须 `esc()`；禁止用动态代码执行用户输入；`meta.visible` 只允许管理员配置的表达式。
- 密钥只存环境变量、配置文件或 Secret；禁止写进代码、注释、日志、前端。
- 真实集群/生产环境破坏性操作（删除、重启、缩容、覆盖配置）必须先展示目标并取得确认。
- 最小权限：只申请业务必需的集群/数据库/缓存权限。

---

## 15. 性能与常驻内存

- 使用协程与框架异步客户端，禁止阻塞调用。
- 列表优先服务端过滤与分页；禁止全量拉取后客户端过滤。
- 批量查询用 `whereIn`，批量写入用 `insertAll`；禁止 N+1。
- 避免每请求全表扫描；高频查询必须有索引支撑。
- 大输出必须流式/分片处理，禁止一次性构造超大数组。
- 常驻进程注意内存泄漏：及时 `unset` 大对象，禁止把请求数据塞进静态属性/全局变量。
- 跨请求共享状态只放 `RamCache` 或 `Swoole\Table`；共享表必须在服务启动时预热。
- 定时任务必须可重入、可补偿；任务失败必须记录并可重试。
- 修改 master 进程已加载的类后，`reload` 可能不生效；必须 `stop` + `start`，否则 worker 可能反复致命退出。

---

## 16. 测试、验收与完成定义（DoD）

### 16.1 静态检查

```bash
php -l app/api/index/v1/controller/Trades.php
```

- 每个新增/修改的 PHP 文件都要跑，禁止只看一个文件。
- 路由改动后必须运行 `php cmd.php api:route`，并确认 `route/*` 与注解一致。

### 16.2 测试

- PHPUnit 测试必须用 `--filter` 只跑只读用例；禁止全量跑会写真实集群/真实数据库的用例。
- 测试会打真实外部系统时，必须在任务书写明副作用与清理方式。
- 单元测试优先覆盖纯计算 provider、金额分摊、状态机、幂等分支。

### 16.3 接口自测

```bash
curl -s -X POST 'http://127.0.0.1:8201/v1/trades.create' \
  -H 'token: <token>' \
  -H 'Content-Type: application/json' \
  -d '{"items":[],"pay_way":"alipay"}'
```

- 必须覆盖成功、参数缺失、无 token、越权、重复回调五类路径。
- 期望结果必须写进任务书，禁止只写「返回正常」。

### 16.4 UI 验收

- UI 改动必须用浏览器走通主路径，并检查空列表、失败、无权限三个边界。
- 记录截图路径或操作步骤；无法实际操作 UI 时必须明确说明「未验证」。

### 16.5 DoD

- 任务书已填完整，现状事实带 `文件:行号`。
- 改动清单逐项完成，无夹带无关文件。
- `php -l` 全部通过；测试/接口/UI 验收通过。
- 无调试残留、临时注入、硬编码密钥。
- 菜单 SQL 与数据库一致；schema 与表结构一致。
- 回滚方案可执行（代码 + 数据）。
- 未明确要求不 `commit`、不 `push`。

---

## 17. 反模式清单

| 反模式 | 正确做法 |
| --- | --- |
| 手改 `route/*` | 改 `@api` 注解后重新生成 |
| `@api POST trades.create`（重复控制器名） | `@api POST create` |
| 中间件写三参数 `handle(Request, Response, Closure)` | 两参数 `handle(Request, Closure)` |
| `param()` 期望合并 GET+POST | 明确用 `get()` / `post()`；JSON 用 `getJson()` |
| 控制器里 `App::database()->table(...)->find()` | 下沉 repository，控制器只调 service |
| 控制器里 `new Alipay()` 调网关 | 下沉 provider |
| `find()` 后 `update()` 改状态 | `UPDATE ... WHERE status=旧值` + 受影响行数 |
| `(float)$amount` 累加或比较 | `bcadd/bccomp(..., 2)` |
| `catch (\Throwable $e) {}` | 记录日志、补偿或上抛 |
| `whereRaw("id = $id")` | `whereRaw('id = :id', ['id' => $id])` |
| 循环里单条查询 | `whereIn` / `insertAll` 批量 |
| 回调先查后改，重复回调重复履约 | 原子 claim + 幂等返回 |
| 日志打印完整 header / 请求体 | 脱敏 + 截断 |
| 给每个 controller 配空 service/repository | 有编排才建 service，有 SQL 复用才建 repository |
| 为了「以后」预建 Validator/Repository 层 | 有第二个调用方或明确职责再建 |
| 在常驻进程中用静态可变属性存请求数据 | `RamCache` / `Swoole\Table` |
| `Model::where(...)` 在循环/并发中反复链式调用 | `App::database()->table()` 每次新建 Query |
| 用户输入直接拼进 K8s 资源名或 URL | DNS-1123 校验 + 编码 |
| 前端写死菜单/列/按钮/枚举 | 由 `system_menu` / 接口元数据下发 |

---

## 18. 提交与回滚

- 仅在明确要求时提交；一个需求一个聚焦提交。
- 提交信息用中文写「为什么」，不写「改了哪些文件」。
- 不提交 `runtime/` 副产物、测试数据库、日志、临时文件、无关未跟踪文件。
- 不主动 `push`；不 force push 主分支。
- 回滚方案必须写进任务书：代码回滚（`git revert` / 恢复文件）+ 数据回滚 SQL。
- 回滚 SQL 内联写在任务书，不另建文件；必须可重复执行或明确只能执行一次。

---

## 19. 与 README 的已知差异（以源码为准）

| README 写法 | 源码事实 | 正确做法 |
| --- | --- | --- |
| 中间件 `handle(Request, Response, Closure)` | `Dispatch::regRoute()` 只传 `$request, $next` | 使用两参数签名 |
| 中间件配置为扁平数组 | `App::config()->get('middleware.{app}')` 按 app 取 | 配置按 app 名分组 |
| 控制器示例直接查库/写库 | 示例为最小演示，不是分层规范 | 按本文 controller → service → repository |
| 路由 `group('/admin')` + `rule('GET','user')` | 生成器分组为控制器名 `lcfirst`，规则为 `@api` 第一段 | 手写路由仅用于框架特殊入口；业务路由走注解 |
| `SchemaService::create()` 自动生成 | 依赖 MySQL `SHOW FULL COLUMNS` | SQLite 项目手写 schema 并维护一致性 |

发现 README 与源码冲突时：

1. 以源码行为为准。
2. 在任务书「现状核验」记录冲突点与 `文件:行号`。
3. 需要修 README 时单独立项，禁止在业务需求里顺手改文档口径。

---

## 20. 附录：任务书最小要求

每个需求的任务书至少包含：

- 一句话目标。
- 背景与决策依据（为什么这样做、为什么不那样做）。
- 现状核验：逐条事实 + `文件:行号` + 「已核验/待核验」。
- 目标结构与动线（目录树/请求链路/菜单结构）。
- 改动清单（编号、文件、位置、内容、硬约束）。
- 影响面与风险（权限、缓存、并发、真实集群副作用、兼容性）。
- 验收标准（功能、静态检查、接口、UI、数据/菜单）。
- 代码约束（分层、删多于增、零新依赖、不改生成物/通用组件）。
- 可选增强（默认不做，单列）。
- 回滚（代码 + SQL）。
- 执行顺序与执行记录（实测结果、已知情况、未做事项）。

任务书模板由各项目维护；模板中的分层与代码骨架必须引用本文，不得复制改写。

---

## 21. 规范维护

- 本文随框架版本演进；修改必须由框架维护者确认。
- 修改时必须核对源码，更新「事实基准」日期。
- 新增条款必须可执行、可验收；禁止写「尽量」「建议」「大概」这类无法判定的表述。
- 项目发现的通用问题，先改本文，再同步到项目差异；禁止只在单个项目里修口径。
