<?php
/**
 * Memcached 操作类文件
 *
 * 本文件在实现在没有安装Memcached扩展的情况下依然能够操作Memcached缓存服务器.
 *
 * @Date:       2016/2/9 19:52
 * @version:    1.0
 * @Author:     零上一度 <xuyi5918@live.cn>
 */


Class Memcached {
    private $host=null;     #主机地址
    private $port=null;     #主机端口
    private $handle=null;   #建立socket连接后的资源

    /**
     *
     * Memcached constructor.
     * @param string $host  Memcached 服务器地址
     * @param int $port     Memcached 服务端口
     */
    public function  __construct($host="127.0.0.1",$port=11211){
        $host&$port||exit("Sorry,Memcached is Host and Prot not null");
        $this->host=$host;
        $this->port=$port;
    }

    /**
     *  判断服务器是否能够连接(似乎有点多余).
     *
     * @return bool 返回服务器是否能够连接
     */
    public function ping()
    {
        if($this->execute_command('PING')==="ERROR"){
            return true;
        }
        return false;
    }


    /**
     * 用来连接到远端Memcached服务器.
     *
     * @return bool|null|resource 返回连接情况
     */
    private function connection()
    {
        if ( !$this->handle )
        {
            if ( !$sock = fsockopen($this->host, $this->port, $errno, $errstr) )
            {
                return false;
            }

            $this->handle = $sock;
        }

        return $this->handle;
    }

    /**
     * 该方法用来发送需要执行的Memcached命令.
     *
     * @param $commands     获取用户传递的Memcached命令
     * @return bool|string  返回执行后的结果或状况
     */
    private function execute_command( $commands )
    {
        $this->connection();
        if ( !$this->handle ) return false;

        if ( is_array($commands) )
        {
            $commands = implode("\r\n", $commands);
        }
        $command=null;
        $command .= $commands . "\r\n";

        for ( $written = 0; $written < strlen($command); $written += $fwrite )
        {
            if ( !$fwrite = fwrite($this->handle, substr($command, $written)) )
            {
                return false;
            }
        }

        return $this->get_response();
    }

    /**
     * 获得命令查询的最终结果.
     *
     * @return bool|string 获得最终值
     */
    private function get_response()
    {
        if ( !$this->handle ) return false;

        return trim(fgets($this->handle), "\r\n ");
    }

    /**
     * 对Memcached中的数据进行Base64解码.
     *
     * @param $packed 需要处理的数据
     * @return string 返回处理后的数据
     */
    private function unpack_value( $packed )
    {
        if ( is_numeric($packed) )
        {
            return $packed;
        }
        return base64_decode($packed);
    }

    /**
     * 将需要存入Memcached中的数据进行Base64处理
     * @param $value    需要处理的数据
     * @return string   返回处理后的数据
     */
    private function pack_value( $value )
    {
        if ( is_numeric($value) )
        {
            return $value;
        }
        return base64_encode($value);
    }

    /**
     * 判断Memcached返回的数据是否是正确的.
     *
     * @param $response
     * @return bool|null
     */
    private function error($response){
        /**
         * 服务器返回ERROR代表系统错误
         */
        if($response=="ERROR"){
            return false;
        }
        /**
         * 缓存值过期后返回Null
         */
        if($response=="END"){
            return null;
        }
        /**
         *  设置成功后返回true
         */
        if($response=="STORED"){
            return true;
        }
        return $response;
    }


    /**
     * 获取Memcached中的缓存值.
     * (当返回false时可能缓存未设置,返回null时可以缓存已经过期)
     * @param $key
     * @return bool|null|string
     */
    public function get( $key )
    {
        $response = $this->execute_command("get {$key}");
        if(!$Result=$this->error($response)){
            return $Result;
        }
        $value = $this->get_response();
        return $this->unpack_value($value);

    }

    /**
     * 设置Memcached的缓存数据
     *
     * @param $key          需要设置的缓存名称
     * @param $value        缓存值
     * @param int $flags    缓存额外内容
     * @param int $exptime  缓存时间
     * @return bool         返回命令是否执行成功
     */
    public function set($key,$value,$flags=0,$exptime=3306)
    {
        $value = $this->pack_value($value);

        $cmd = "set {$key} {$flags} {$exptime} ".strlen($value)."\r\n{$value}\r\n";

        $response = $this->execute_command( $cmd );

        return $this->error($response);
    }

    /**
     * 删除Memcached中的缓存.
     *
     * @param $key      需要删除的缓存名称
     * @return bool     返回删除结果
     */
    public function delete($key){
        $response = $this->execute_command("delete {$key}");
        if($response=="DELETED"){
            return true;
        }else{
            return false;
        }
    }
}

/**
 *  Class memcached_pool(Memcached服务器地址池)
 *  本类实现Memcached服务器列表管理
 */
class memcached_pool{

    private static $connections = array();
    private static $servers = array();

    /**
     * 增加Memcached服务器地址列表
     * @param $list 地址数组
     */
    public function add_servers( $list = array())
    {

        foreach ( $list as $alias => $data )
        {
            self::$servers[$alias] = $data;
        }
    }

    /**
     * 该方法用来选择连接那个Memcached地址
     * @param $alias    服务器名称
     * @return mixed    返回Memcached类的实例
     */
    public function get( $alias )
    {

        if ( !array_key_exists($alias, self::$connections) )
        {
            self::$connections[$alias] = new Memcached(self::$servers[$alias][0], self::$servers[$alias][1]);
        }

        return self::$connections[$alias];
    }

}

/**
 *Class memcachedClient
 *实例化本类进行Memcached操作也可以扩展本类增加逻辑需要的功能.
 */
Class memcachedClient extends memcached_pool{

    public $exec=null;  #保持获得的Memcached对象

    public function __construct($list){
        $this->add_servers($list);
    }

    public function select($alias){
        $this->exec=$this->get($alias);
        return $this;
    }

    /**
     * PS 例如本方法
     */
    public  function setint(){
       echo  $this->exec->set('kkk',"wwww");
    }
}
