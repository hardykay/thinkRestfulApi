<?php


namespace hardy\http\session;

use think\facade\Cache;

/**
 * token 保存登陆状态
 * Class TokenCache
 * @package app\extra
 */
class TokenCache
{
    // token id 可以直接通过类名获取，而他的赋值在事件app\extra\behavior\AuthBehavior中
    public static $sid = '';
    // 判断是否为第一次设置token 
    public static $send_flg = false;
    // 缓存时间
    public static $expire = 24 * 3600;

    /**
     * 设置缓存内容
     * @param $key
     * @param $value
     * @param null $expire
     * @return mixed|void
     */
    public static function set($key, $value, $expire = null)
    {
        if (empty(self::$sid)) {
            return;
        }
        $data = Cache::get(self::$sid);
        if (is_null($value)) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }
        if (is_null($expire)) {
            $expire = self::$expire;
        }
        return Cache::set(self::$sid, $data, $expire);
    }

    /**
     * 获取缓存内容
     * @param $key
     * @return mixed|null
     */
    public static function get($key = '')
    {
        if (empty(self::$sid)) {
            return null;
        }
        $data = Cache::get(self::$sid);
        if ($key == '') {
            return $data;
        } else {
            return isset($data[$key]) ? $data[$key] : null;
        }
    }

    /**
     * 设置token id
     * @param string $sid
     */
    public static function setId($sid = '')
    {
        if ($sid) {
            self::$sid = $sid;
            self::$send_flg = false;
        } else {
            self::$sid = self::guid();
            self::$send_flg = true;
        }

        $data = Cache::get($sid);
        if ($data) {
            $expire = self::$expire;
            Cache::set($sid, $data, $expire);
        }
    }

    /**
     * 获取唯一id，作为token id的值
     * @return mixed|string
     */
    protected static function guid()
    {
        if (function_exists('com_create_guid')) {
            $str = com_create_guid();
            $str = str_replace('{', '', $str);
            $str = str_replace('}', '', $str);
            $str = str_replace('-', '', $str);
            return $str;
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            return strtoupper(md5(uniqid(rand(), true)));
        }
    }
}
