<?php
namespace system\library\decorator\ifs;

/**
 * 默认装饰器接口
 *
 * @author topnuomi 2018年11月22日
 */
interface DefaultDecoratorIfs {

    /**
     * 前置操作
     */
    public function before();

    /**
     * 后置操作
     * 
     * @param array $data            
     */
    public function after($data);
}