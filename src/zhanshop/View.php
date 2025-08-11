<?php
// +----------------------------------------------------------------------
// | zhanshop-shop / View.php    [ 2025/5/20 11:57 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

class View
{

    /**
     * @return \Smarty\Smarty
     */
    public function view($request)
    {
        $config = App::config()->get('view');
        list($controller) = $request->getHandler();
        $path = App::rootPath().DIRECTORY_SEPARATOR.str_replace('\\', '/', $controller);
        App::phar()->import('extend/library/smarty');
        $smarty = new \Smarty\Smarty();
        $smarty->debugging = false;
        $smarty->caching = false;
        $smarty->setLeftDelimiter($config['tpl_begin']);
        $smarty->setRightDelimiter($config['tpl_end']);
        $smarty->setTemplateDir(dirname($path, 2).DIRECTORY_SEPARATOR.$config['view_dir_name']);
        return $smarty;
    }
}