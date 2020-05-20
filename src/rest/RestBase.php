<?php


namespace hardy\http\rest;


use think\exception\HttpResponseException;
use think\Response;
use hardy\http\session\TokenCache;

class RestBase  extends AuthSession
{
    // 分页数据
    protected $count = 0;
    protected $size = 0;
    protected $page = 0;
    // 允许请求方法类型
    protected $methods = ['get', 'post', 'put', 'delete'];

    //  session 保存的键名
    public static $_login_key = 'auth_key';

    // 默认请求方法
    protected $defaultMethod = 'get';

    // 当前请求方法
    protected $method;

    // 当前资源类型
    protected $type;

    // 默认资源类型
    protected $defaultType   = 'json';

    //请求对应的方法
    protected $invokeList = [
        'get' => 'index,read/id',
        'post' => 'create',
        'put' => 'update',
        'delete' => 'delete'
    ];

    // 用户id
    protected $userId = 0;

    // 当前版本号
    protected $version = '';

    // 无需权限即可访问
    protected $noAuthorization = [];

    /**
     * 架构函数 取得模板对象实例
     * RestBase constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        // 初始化
        parent::__construct();
        // 资源类型
        $this->type = $this->getResponseType();
        // 请求方式检测
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        //跨域嗅探,直接返回200
        if($method == 'options'){
            $this->response(true, '',null, ['status'=>204]);
        }
        //参数过滤
        //$this->filterHandle();
        if (!in_array($method, $this->methods)) {
            // 请求方式非法 则用默认请求方法
            $method = $this->defaultMethod;
        }
        $this->method = $method;
        $this->getVersion();
        $this->route();
        exit();
        //匹配
    }
    /**
     * 获取请求方法对应的参数
     */
    protected function getMethodFunc(){
        $func = $this->invokeList[$this->method];
        if (!empty($func) && strpos($func, ',') !== false){
            $arr = explode(',', $func);
            // 设置默认的方法，默认的方法是只有字母数字下划线组成的函数
            foreach ($arr as $item){
                if (preg_match('/^[0-9a-zA-Z_]{1,}$/',$item)){
                    $func = $item;
                    break;
                }
            }
            // 获取目标函数
            $data = input($this->method.'.');
            $keys = array_keys($data);

            foreach ($arr as $item){
                // 不存在参数则继续
                if (preg_match('/^[0-9a-zA-Z_]{1,}$/',$item)){
                    continue;
                }
                // 不存在"/"继续
                if(strpos($item, '/') === false){
                    continue;
                }
                $route = explode('/', $item);
                if (empty($route[1])){
                    continue;
                }
                // 以“:”分开每一个参数
                $params = explode(':', $route[1]);
                if (count($params) == 0){
                    continue;
                }
                $flag = 0;
                foreach ($params as $v){
                    if (in_array($v, $keys) && $data[$v] !== ''){
//                    if (in_array($v, $keys)){
                        $flag++;
                    }
                }
                // 取值函数名则退出循环
                if (count($params) == $flag){
                    $func = $route[0];
                    break;
                }
            }
        }
        if(!empty($func) && $this->version != ''){
            $func .= '_v' . $this->version;
        }
        if (empty($func)){
            $this->response(false, '访问不存在的方法，请认真阅读开发文档！', [], ['status'=>404]);
        }
        return $func;
    }
    /**
     * REST 调用路由
     * @throws \Exception
     */
    protected function route()
    {
        $method = $this->getMethodFunc();
        if(method_exists($this, $method)){
            if(empty($this->noAuthorization) || !in_array($method, $this->noAuthorization)){
                $this->auth();
            }
            $this->$method();
        } else {
            throw new \RuntimeException('未定义的方法名:' . $method);
        }
    }

    /**
     * 检验登陆
     */
    protected function auth(){
        $user =  TokenCache::get(self::$_login_key);
        if(!$user){
            $this->response(false, '未登录', null, ['status'=>401]);
        }
        $this->userId = $user['id'];
    }

    /**
     * 本版号检查
     */
    protected function getVersion(){
        $word_reg = "/.*version=(\d+(?:\.\d+)?)/";
        if(preg_match($word_reg, $_SERVER['HTTP_ACCEPT'], $matches)){
            $this->version = str_replace('.', '_', $matches[1]);
        }
    }
    /**
     * 成功
     * @param array $data
     * @param string $message
     */
    public function success($data=[], $message='success'){
        if ($this->count !== 0 || $this->size !== 0 ){
            // 总数
            $jsonData['count'] = $this->count;
            // 分页数
            $jsonData['size'] = $this->size;
            // 页码
            $jsonData['page'] = $this->page;
            //当前记录列表
            if(empty($data)){
                $jsonData['list'] = [];
            }else{
                $jsonData['list'] = $data;
            }
        } else {
            $jsonData = $data;
        }
        $this->response(true, $message, $jsonData);
    }
    /**
     * 失败
     * @param string $message       错误信息
     * @param array $data           可带数据
     */
    public function failed($message='Error message',$data = []){
        $this->response(false, $message,$data);
    }
    /**
     * 错误
     * @param string $message
     * @param array $data
     */
    public function error($message='Failed to load response data',$data = []){
        $this->response(false, $message, $data, ['status'=>401]);
    }
    /**
     * 响应请求
     * @param bool $status
     * @param string $msg
     * @param array $data
     * @param array $header
     */
    protected function response($status, $msg='', $data=[], array $header=[]) {
        $return_data['status'] = $status;
        $return_data['message'] = $msg;
        $return_data['data'] = $data;
        $header['Access-Control-Allow-Origin']  = '*';
        $header['Access-Control-Allow-Headers'] = 'X-Requested-With,Content-Type,XX-Device-Type,XX-Token,XX-Api-Version,XX-Wxapp-AppId';
        $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE,OPTIONS';
        $response = Response::create($return_data, $this->type)->header($header);
        throw new HttpResponseException($response);
    }
    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        // 获取当前请求的Accept头信息
        $type = array(
            'xml'   =>  'application/xml,text/xml,application/x-xml',
            'json'  =>  'application/json,text/x-json,application/jsonrequest,text/json',
            'html'  =>  'text/html,application/xhtml+xml'
        );

        foreach($type as $key=>$val){
            $array   =  explode(',',$val);
            foreach($array as $k=>$v){
                if(stristr($_SERVER['HTTP_ACCEPT'], $v)) {
                    return $key;
                }
            }
        }
        return $this->defaultType;
    }

}