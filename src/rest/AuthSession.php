<?php


namespace hardy\http\rest;

use hardy\http\session\TokenCache;

class AuthSession
{
    // http header authorization
    public $authKey = 'authorization';
    function __construct()
    {
        // 初始化
        $this->initialize();
        $info = [];
        // 获取请求头
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $info[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        if (array_key_exists($this->authKey, $info) && !empty($info[$this->authKey])){
            /*
             * 获取头部的  authorization
             */
            $auth_id = $info[$this->authKey];
        } else {
            if(array_key_exists($this->authKey, $_COOKIE) && $_COOKIE[$this->authKey]){
                /*
                 * 获取cookie中的authorization
                 */
                $auth_id = $_COOKIE[$this->authKey];
            }
        }
        if (!empty($auth_id)){
            // 设置authorization的值
            TokenCache::setId($auth_id);
        } else {
            /*
             * authorization的值不存在则创建一个
             */
            TokenCache::setId();
        }
    }
    // 初始化
    protected function initialize()
    {}

}