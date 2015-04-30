<?php

/**
 * Class RoomController
 * @author D.C
 * @update 2015-04-16
 * @description 房间状态控制器
 */
class RoomController extends BaseController{

    protected static $roomType = null;

    public function defaultAction(){
        $this->statusAction();
    }


    /**
     * @return mixed
     * @author.D.C
     * @update 2015-04-17
     * @description 默认控制器
     */
    public function statusAction(){
        $k = isset($_GET['k'])?addslashes($_GET['k']):null;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size*($page-1);

        $users = FrontUserModel::getInstance()->fields(array('uid','username','nickname','safemail', 'lv_type','lv_exp','logined'))->eq(array('roled'=>3));

        if(!empty($_GET['k'])){
            $likes = array('uid'=>$k, 'username'=>$k, 'nickname'=>$k, 'safemail'=>$k);
            $users->like($likes);
        }
        $users = $users->limit($offset,$page_size)->getAll();
        $total = FrontUserModel::getInstance()->fields(array('count(uid) as total'))->eq(array('roled'=>3));
        !empty($_GET['k']) && $total->like($likes);
        $total = $total->getOne();
        $total = $total['total'];
        $data['pages'] = $this->global->pages($page_size,$total,5,$page);
        return include_once("./tpl/room-status.html");
    }

    /**
     * @author D.C
     * @update 2015-04-17
     * @description ajax更新房间动作
     */
    public function ajaxPostAction(){
        if(!in_array(66,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        if (!in_array('1',array_values($_POST['data']))){
            exit(json_encode('对不起！必须保证开启一个房间类型'));

        }
        $uid = sprintf("%s",$_POST['uid']);
        $pwd = sprintf("%s",$_POST['pwd']);
        $rooms = $_POST['data'];

        foreach($rooms as $tid=>$val){
            $data = array('status'=>$val);
            if( $val && $tid==2 ) {
                $data = array_merge($data,array('pwd'=>$pwd));
                unset($pwd);
            }
            $roomStatus = FrontRoomStatusModel::getInstance()->eq(array('uid'=>$uid,'tid'=>$tid))->getOne();
            if ($roomStatus){;
                $result = FrontRoomStatusModel::getInstance()->update($data, 'uid='.$uid.' and tid='.$tid);
            }else{
                $data = array_merge($data, array('uid'=>$uid,'tid'=>$tid));
                $result = FrontRoomStatusModel::getInstance()->insert($data);
            }
            if(!$this->_updateRedisCache('hroom_status',$uid,array($tid=>$data))){
                exit(json_encode('更新缓存失败'));
            }


            if(!$result) exit(json_encode('对不起！更新过程出错'));
        }
        exit(json_encode('更新操作成功'));
    }


    /**
     * ajaxGetStatus控制方法
     * @author.DC.
     * @update 2015-04-20
     * @description null
     */
    public function ajaxGetStatusAction(){
        if(!in_array(66,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $uid = $_GET['uid'];
        $status = FrontRoomStatusModel::getInstance()->eq(array('uid'=>$uid,'status'=>1))->getAll();
        exit(json_encode($status));
    }


    /**
     * @return mixed
     * @author D.C
     * @update 2015-04-20
     * @description 弹出框列表
     */
    public function dialogListAction(){
        if(!in_array(66,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $uid = sprintf("%u",$_GET['uid']);
        $tid = sprintf("%u",$_GET['tid']);
        $rooms = FrontRoomDurationModel::getInstance()->eq(array('uid'=>$uid,'roomtid'=>$tid))->getAll();
        $rooms = $rooms?:array();
        return include_once("./tpl/room-dialoglist.html");
    }


    public function setDurationAction(){
        if(!in_array(66,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $id = sprintf('%u',$_GET['id']);

        if($_POST['id']>0){
            $id = sprintf("%s", $_POST['id']);
            $tid = sprintf("%u",$_POST['roomtid']);
            $uid = sprintf("%u",$_POST['uid']);
            $starttime = sprintf("%s %s",$_POST['date'], $_POST['time']);
            $duration = sprintf("%s", $_POST['duration']);

            if(!$this->_getSameTimebyDuration($id, $uid, $starttime, $duration)){
                echo '<script type="text/javascript">alert(\'修改失败：时间排班上出现重复\');history.go(-1);</script>';
                exit;
            }

            $data = array('starttime'=>$starttime,'duration'=>$duration);
            $data = $tid==3 ? array_merge($data,array('tickets'=>sprintf("%u",$_POST['tickets']))) : array_merge($data,array('points'=>sprintf("%u",$_POST['points'])));
            $update = FrontRoomDurationModel::getInstance()->update($data,"id=".$id);

            if($update){
                $this->_updateRedisCache('hroom_duration',$uid,array($tid=>$data));
               echo '<script type="text/javascript">alert(\'修改成功\');top.location.href="/index.php?do=room-status";</script>';
            }

        }

        $room = FrontRoomDurationModel::getInstance()->eq(array('id'=>$id))->getOne();
        return include_once('./tpl/room-setduration.html');
    }


    /**
     * @author D.C
     * @update 2015-04-20
     * @description 时长房间类型删除操作
     */
    public function delDurationAction(){

        $id = sprintf('%u',$_GET['id']);
        if(FrontRoomDurationModel::getInstance()->update(array('status'=>0),'id='.$id)){
            echo json_encode('删除成功！');

        }
        return;
    }


    /**
     * @param null $hash
     * @param null $uid
     * @param null $data
     * @return bool
     * @author.D.C
     * @update 2015-04-21
     * @description Redis缓存更新操作
     */
    private function _updateRedisCache($hash = null, $uid = null, $data = null){
        if(!$hash || !$uid || !is_array($data)) return false;
        $redis = new Redis();
        $redis->connect($this->config['redis_ip'],$this->config['redis_port']);
        $pipe = $redis->multi(Redis::PIPELINE);
        foreach($data as $key=>$val){
                if(is_array($val)){
                    foreach($val as $k=>$v){
                        $pipe->hset($hash.':'.$uid.':'.$key,$k,$v);
                    }
                }else{
                    $pipe->hset($hash.':'.$uid,$key,$val);
                }
        }

        $pipe->exec();
        $redis->close();
        return true;
    }


    /**
     * @param null $id
     * @param null $uid
     * @param null $starttime
     * @param null $duration
     * @return bool
     * @author D.C
     * @update 2015-04-21
     * @description 重叠时间计算方法
     */
    private function _getSameTimebyDuration($id= null, $uid = null,$starttime = null, $duration = null){
        if(!$id || !$uid || !$starttime) return false;
        $gstime = strtotime($starttime);
        $getime = $gstime+($duration*60);
        $rooms = FrontRoomDurationModel::getInstance()->fields(array('starttime','duration'))->eq(array('uid'=>$uid))->neq(array('id'=>$id))->getAll();
        $i=0;
        $_array = array($gstime=>'new',$getime=>'new');
        foreach($rooms as  $room){
            $i++;
            $stime = strtotime($room['starttime']);
            $etime = $stime + ($room['duration']*60);
            $_array[$stime]=$i;
            $_array[$etime]=$i;
        }
        ksort($_array);
        reset($_array);
        while(key($_array) != $gstime) next($_array);
        if(next($_array)!='new'){
            return false;
        }
        reset($_array);
        while(key($_array) != $getime) next($_array);
        if(prev($_array)!='new'){
            return false;
        }
        return true;
    }




    /**
     * @param null $uid
     * @return int|string
     * @author D.C
     * @update 2015-04-16
     * @description 获取直播时间函数
     */
    private function  _getTotalLiveTime($uid = null){
        if(!$uid){
            return 0;
        }
        $redis = new redis();
        $redis->connect($this->config['redis_ip'],$this->config['redis_port']);
        $times =  $redis->get('live_total_time:'.$uid);
        $redis->close();
        return $times ?$this->global->sec2time($times): 0;
    }

    /**
     * @param null $uid
     * @return bool|string
     * @author D.C
     * @update 2015-04-16
     * @description 获取房间最后修改时间
     */
    private function _getFormatLastModifyTime($uid = null){
        if(!$uid) return false;
        $time = FrontRoomStatusModel::getInstance()->fields(array('time'))->eq(array('uid'=>$uid))->getOne();
        return $time['time'];

    }

    /**
     * @param null $uid
     * @return bool
     * @author.D.C
     * @update 2015-04-16
     * @description 获取最后直播时间
     */
    private function _getLastLiveTime($uid = null){
        if(!$uid) return false;
        $live = FrontLiveModel::getInstance()->eq(array('uid'=>$uid))->order('start_time desc')->getOne();
        return isset($live['start_time']) ? $live['start_time'] : '';
    }


    /**
     * @param null $uid
     * @return string
     * @author D.C
     * @update 2015-04-16
     * @description 获取主播在线状态
     */
    private function _getUserOnlineStatus($uid = null){
        $is_online = @$this->global->vedioInterface(array('uid'=>$uid),$this->config['is_online']);

        if($is_online && ($is_online['ret']==1)){
            return $is_online['loc']>-1? '在线':'下线';
        }
    }


    /**
     * @return null
     * @author D.C
     * @update 2015-04-16
     * @description 获取房间类型
     */
    private function _getRoomType(){
        if(!self::$roomType){
            self::$roomType = FrontRoomTypeModel::getInstance()->getAll();
        }
        return self::$roomType;
    }


    /**
     * @param null $uid 【用户ID】
     * @param null $by 【索引主键名称】
     * @return mixed
     * @author D.C
     * @update 2015-04-17
     * @description 获取房间状态
     */
    private function _getRoomStatus($uid = null, $by = null){
        $RoomStatus = FrontRoomStatusModel::getInstance()->eq(array('uid'=>$uid,'status'=>1))->getAll();
        if(!$RoomStatus) return array();
        if($by){
            $rooms = array();
            foreach($RoomStatus as $room){
                $rooms[$room['uid']][$room[$by]] = $room;
            }
            return $rooms;
        }else{
            return $RoomStatus;
        }
    }
}