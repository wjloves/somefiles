<?php
/** * Redis 操作，支持 Master/Slave 的负载集群 * * @author V哥 */
class redisClusterClass{
    // 是否使用 M/S 的读写集群方案
    private $_isUseCluster = false;
    // Slave 句柄标记
    private $_sn = 0;
    // 服务器连接句柄 
    private $_linkHandle = array(
        'master'=>null,// 只支持一台 Master
        'slave'=>array(),// 可以有多台 Slave
    );

    /**
     * 构造函数
     *
     * @param boolean $isUseCluster 是否采用 M/S 方案
     */
    public function __construct($isUseCluster=false){
        $this->_isUseCluster = $isUseCluster;
    }

    /**
     * 连接服务器,注意：这里使用长连接，提高效率，但不会自动关闭
     *
     * @param array $config Redis服务器配置
     * @param boolean $isMaster 当前添加的服务器是否为 Master 服务器
     * @return boolean
     */
    public function connect($config=array('host'=>'127.0.0.1','port'=>6379), $isMaster=true){
        // default port
        if(!isset($config['port'])){
            $config['port'] = 6379;
        }
        // 设置 Master 连接
        if($isMaster){
            $this->_linkHandle['master'] = new Redis();
            $ret = $this->_linkHandle['master']->pconnect($config['host'],$config['port']);
        }else{
            // 多个 Slave 连接
            $this->_linkHandle['slave'][$this->_sn] = new Redis();
            $ret = $this->_linkHandle['slave'][$this->_sn]->pconnect($config['host'],$config['port']);
            ++$this->_sn;
        }
        return $ret;
    }

    /**
     * 关闭连接
     *
     * @param int $flag 关闭选择 0:关闭 Master 1:关闭 Slave 2:关闭所有
     * @return boolean
     */
    public function close($flag=2){
        switch($flag){
            // 关闭 Master
            case 0:
                $this->getRedis()->close();
                break;
            // 关闭 Slave
            case 1:
                for($i=0; $i<$this->_sn; ++$i){
                    $this->_linkHandle['slave'][$i]->close();
                }
                break;
            // 关闭所有
            case 2:
                $this->getRedis()->close();
                for($i=0; $i<$this->_sn; ++$i){
                    $this->_linkHandle['slave'][$i]->close();
                }
                break;
        }
        return true;
    }

    /**
     * 得到 Redis 原始对象可以有更多的操作
     *
     * @param boolean $isMaster 返回服务器的类型 true:返回Master false:返回Slave
     * @param boolean $slaveOne 返回的Slave选择 true:负载均衡随机返回一个Slave选择 false:返回所有的Slave选择
     * @return redis object
     */
    public function getRedis($isMaster=true,$slaveOne=true){
        // 只返回 Master
        if($isMaster){
            return $this->_linkHandle['master'];
        }else{
            return $slaveOne ? $this->_getSlaveRedis() : $this->_linkHandle['slave'];
        }
    }
    /**
     * 写缓存
     *
     * @param string $key 组存KEY
     * @param string $value 缓存值
     * @param int $expire 过期时间， 0:表示无过期时间
     */
    public function set($key, $value, $expire=0){
        // 永不超时
        if($expire == 0){
            $ret = $this->getRedis()->set($key, $value);
        }else{
            $ret = $this->getRedis()->setex($key, $expire, $value);
        }
        return $ret;
    }

    /**
     * 读缓存
     *
     * @param string $key 缓存KEY,支持一次取多个 $key = array('key1','key2')
     * @return string || boolean  失败返回 false, 成功返回字符串
     */
    public function get($key){
        // 是否一次取多个值
        $func = is_array($key) ? 'mGet' : 'get';
        // 没有使用M/S
        if(! $this->_isUseCluster){
            return $this->getRedis()->{$func}($key);
        }
        // 使用了 M/S
        return $this->_getSlaveRedis()->{$func}($key);
    }

    /**
     * 构建一个无序集合
     * @param $key  名称
     * @param $val  值
     * @return int
     */
    public function sadd($key,$val){
        if(! $this->_isUseCluster){
            return $this->getRedis()->sAdd($key,$val);
        }
        return $this->_getSlaveRedis()->sAdd($key,$val);
    }

    /**
     * 取集合对应的元素
     * @param $name   key名称
     * @return array
     */
    public function smembers($name){
        if(! $this->_isUseCluster){
            return $this->getRedis()->sMembers($name);
        }
        return $this->_getSlaveRedis()->sMembers($name);
    }

    /**
     * 构建一个列表（先进后厨，类似栈）
     * @param $key 名称
     * @param $val 值
     * @return int
     */
    public function lpush($key,$val){
        if(! $this->_isUseCluster){
            return $this->getRedis()->lPush($key,$val);
        }
        return $this->_getSlaveRedis()->lPush($key,$val);
    }

    /**
     * 构建一个列表（先进先出，类似队列）
     * @param $key  名称
     * @param $val  值
     * @return int
     */
    public function rpush($key,$val){
        if(! $this->_isUseCluster){
            return $this->getRedis()->rPush($key,$val);
        }
        return $this->_getSlaveRedis()->rPush($key,$val);
    }

    /**
     * 获取所有列表数据(从头到尾)
     * @param $key  键名称
     * @param $head 开始位置
     * @param $tail 结束位置
     * @return array
     */
    public function lrange($key,$head,$tail){
        if(! $this->_isUseCluster){
            return $this->getRedis()->lRange($key,$head,$tail);
        }
        return $this->_getSlaveRedis()->lRange($key,$head,$tail);
    }

    /**
     * 设置hash中某个字段的数据
     * @param $name     表名字key
     * @param $field    hash中的字段名称
     * @param $val      字段的值
     * @return int
     */
    public function hset($name,$field,$val){
        if(! $this->_isUseCluster){
            return $this->getRedis()->hSet($name,$field,$val);
        }
        return $this->_getSlaveRedis()->hSet($name,$field,$val);
    }

    /**
     * 从hash中获取某个字段的数据
     * @param $name     表名字key
     * @param $field    hash中的字段名称
     * @return string
     */
    public function hget($name,$field){
        if(! $this->_isUseCluster){
            return $this->getRedis()->hGet($name,$field);
        }
        return $this->_getSlaveRedis()->hGet($name,$field);
    }

    /**
     * 从hash中获取所有字段的数据
     * @param $name     表名字key
     * @return array
     */
    public function hgetall($name){
        if(! $this->_isUseCluster){
            return $this->getRedis()->hGetAll($name);
        }
        return $this->_getSlaveRedis()->hGetAll($name);
    }

    /**
     * 设置hash中的值
     * @param $name 表名字name
     * @param $val  值
     * @return bool
     */
    public function hmset($name,$val){
        if(! $this->_isUseCluster){
            return $this->getRedis()->hMset($name,$val);
        }
        return $this->_getSlaveRedis()->hMset($name,$val);
    }

    /**
     * 批量获取某个hash中一组成员的数据
     * @param $name     表名字key
     * @param $fields   hash中的字段名称
     * @return array
     */
    public function hmget($name,$fields){
        if(! $this->_isUseCluster){
            return $this->getRedis()->hMGet($name,$fields);
        }
        return $this->_getSlaveRedis()->hMGet($name,$fields);
    }

    /**
     * 条件形式设置缓存，如果 key 不存时就设置，存在时设置失败
     *
     * @param string $key 缓存KEY
     * @param string $value 缓存值
     * @return boolean
     */
    public function setnx($key, $value){
        return $this->getRedis()->setnx($key, $value);
    }

    /**
     * 删除缓存
     *
     * @param string || array $key 缓存KEY，支持单个健:"key1" 或多个健:array('key1','key2')
     * @return int 删除的健的数量
     */
    public function remove($key){
        // $key => "key1" || array('key1','key2')
        return $this->getRedis()->delete($key);
    }

    /**
     * 值加加操作,类似 ++$i ,如果 key 不存在时自动设置为 0 后进行加加操作
     *
     * @param string $key 缓存KEY
     * @param int $default 操作时的默认值
     * @return int　操作后的值
     */
    public function incr($key,$default=1){
        if($default == 1){
            return $this->getRedis()->incr($key);
        }else{
            return $this->getRedis()->incrBy($key, $default);
        }
    }

    /**
     * 值减减操作,类似 --$i ,如果 key 不存在时自动设置为 0 后进行减减操作
     *
     * @param string $key 缓存KEY
     * @param int $default 操作时的默认值
     * @return int　操作后的值
     */
    public function decr($key,$default=1){
        if($default == 1){
            return $this->getRedis()->decr($key);
        }else{
            return $this->getRedis()->decrBy($key, $default);
        }
    }

    /**
     * 添空当前数据库
     *
     * @return boolean
     */
    public function clear(){
        return $this->getRedis()->flushDB();
    }

    /* =================== 以下私有方法 =================== */
    /**
     * 随机 HASH 得到 Redis Slave 服务器句柄
     *
     * @return redis object
     */
    private function _getSlaveRedis(){
        // 就一台 Slave 机直接返回
        if($this->_sn <= 1){
            return $this->_linkHandle['slave'][0];
        }
        // 随机 Hash 得到 Slave 的句柄
        $hash = $this->_hashId(mt_rand(), $this->_sn);
        return $this->_linkHandle['slave'][$hash];
    }

    /**
     * 根据ID得到 hash 后 0～m-1 之间的值
     *
     * @param string $id
     * @param int $m
     * @return int
     */
    private function _hashId($id,$m=10)
    {
        //把字符串K转换为 0～m-1 之间的一个值作为对应记录的散列地址
        $k = md5($id);
        $l = strlen($k);
        $b = bin2hex($k);
        $h = 0;
        for($i=0;$i<$l;$i++){
            //相加模式HASH
            $h += substr($b,$i*2,2);
        }
        $hash = ($h*1)%$m;
        return $hash;
    }// End Class
}