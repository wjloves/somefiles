<?php
/**
 * Created by PhpStorm.
 * User: morgan
 * Date: 15-4-10
 * Time: 上午12:45
 */

header("content-type:text/html;charset=utf-8");
class CashController extends BaseController{
    public function __construct(){
        parent::__construct();
        $this->E_admin       = EndAdminModel::getInstance();
        $this->E_cash        = EndCashModel::getInstance();
        $this->E_host_info   = EndHostInfoModel::getInstance();

        $this->F_user        = FrontUserModel::getInstance();
        $this->F_userentends = FrontUserExtendsModel::getInstance();
        $this->F_drawal      = FrontdrawalModel::getInstance();
    }
    /**
     * [defaultAction 提现申请]
     * @author morgan 
     * date 2015/4/10
     */
    public function defaultAction(){
          //判断权限
          if(!in_array(16,$this->_priv_arr)){
                  echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
                  return;   
          }

          $page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
          $page_size = 20;
          $offset = $page_size*($page-1);
          $data['keyword']  = addslashes($_GET['keyword']);
          if(!$_GET['search']['status']){
              $_GET['search']['status']['eq'] = 0;
          }
          /*搜索条件*/
          
          Search::getCondition($this->E_cash);
          /*搜索关键字*/
          ExtractController::keyword($data['keyword'],$this->E_cash);
          $list  = $this->E_cash->limit($offset,$page_size)->getAll();

          foreach ($list as $key => $value) {
              //主播信息  
              $redis = new redis();
              $redis->connect($this->config['redis_ip'],$this->config['redis_port']);
              $list[$key]['nickname'] = $redis->hget('huser_info:'.$value['uid'],'nickname');
              $list[$key]['points']   = $redis->hget('huser_info:'.$value['uid'],'points');
              $list[$key]['roled']    = $redis->hget('huser_info:'.$value['uid'],'roled');
              $list[$key]['username'] = $redis->hget('huser_info:'.$value['uid'],'username');
              //判断redis中是否有用户信息
              if(empty($list[$key]['username'])){
                  //如果redis没有用户信息，则去DB中查询
                  $userinfo = $this->F_user->fields(array('nickname','points','roled'))->eq(array("uid"=>$value['uid']))->getOne();
                  if($userinfo){
                      $list[$key]['nickname'] = $userinfo['nickname'];
                      $list[$key]['points'] = $userinfo['points'];
                      $list[$key]['roled'] = $userinfo['roled'];
                  }else{
                      unset($list[$key]);
                  }
              }
              //扩展信息
              $usrExtends  = $this->F_userentends->fields(array("bankname",'banknumber','bankaddress'))->eq(array("uid"=>$value['uid']))->getOne();
              if($usrExtends){
                  $list[$key]  = array_merge($usrExtends,$list[$key]);
                  $list[$key]['issuing_bank'] = ExtractController::checkBankMessage($usrExtends['banknumber']);
              }
              //获取实时用户可用余额
              $redis->set('checkBalance',true);
              $returnMsg = $this->global->vedioInterface(array('uid'=>$value['uid']),$this->config['usr_real_cash']);
              $list[$key]['realmoney'] = 0;
              if($returnMsg['ret'] == 1 ){
                  $list[$key]['realmoney'] = $returnMsg['msg'];
              }
              
              $redis->close();
              //获取客服列表
              if(!empty($value['apply_editor'])){
                  $E_user = $this->E_admin->fields(array('admin_id,name'))->eq(array('status'=>0,'admin_id'=>$value['apply_editor']))->getOne();
                  $list[$key]['admin_id']   = $E_user['admin_id'];
                  $list[$key]['admin_name'] = $E_user['name'];
              }
              $list[$key]['points'] += $value['money']*10;
          }
          /*搜索条件*/
          Search::getCondition($this->E_cash);
          /*搜索关键字*/
          ExtractController::keyword($data['keyword'],$this->E_cash);
          $count  = $this->E_cash->fields(array('count(id) as nums'))->getOne();
          $nums   = $count['nums'];
          $data['list']  = $list;
          
          $data['pages'] = $this->global->pages($page_size,$nums,5,$page);
          include_once("./tpl/cash-list.html");
    }


    /**
     * [ajaxGetOneAction 查询一条主播提现申请详细信息]
     * @return json [处理结果]
     */
    public function  ajaxGetOneAction(){
            $id = intval($_GET['id']);
            $uid = intval($_GET['uid']);
            $status = intval($_GET['status']);

            $usrMsg = $this->F_drawal->getOne("SELECT realname,bankname,banknumber,bankaddress FROM `video_user_extends` where uid=".$uid);
            $orderMsg = $this->E_cash->fields(array('order_number,username,money'))->eq(array('id'=>$id,"status"=>$status))->getOne();
            if($usrMsg && $orderMsg){
               echo json_encode(array_merge($orderMsg,$usrMsg));
            }else{
               echo 0;
            }
    }

    /**
     * [ajaxEditAction 主播提现审批]
     * @param  array $data   订单信息
     * @author morgan  
     * date    2015/4/11
     */
    public function ajaxEditAction(){
        $data = json_decode($_POST['data'],true);
        $uid  = intval($data['uid']);
        $condition['status'] = $data['status'];
        $condition['app_content'] = $data['content'];
        $condition['app_time']  = date('Y-m-d H:i:s',time());
        $condition['apply_editor'] = $this->_login['admin_id'];
        $state = $this->E_cash->update($condition, ' id='.$data['id']);
        if($data['status']==2){
            //状态为2的情况  是申请或审核失败
           $orderState = $this->F_drawal->update(array('status'=>2),' withdrawalnu='.$data['order_number']);
            //状态修改为失败后，对主播扣除的积分进行添加
            if($orderState){
                // //查询当前主播是否是主播，前后台数据库进行查询
                // $video_host = $this->F_user->fields(array('uid'))->eq(array('uid'=>$uid,'roled'=>3,'status'=>1))->getOne();
                // $vbos_host  = $this->E_host_info->fields(array())->eq(array('uid'=>$uid,'dml_flag'=>1))->getOne();
                // //如果主播状态正常
                //if($video_host && $vbos_host){
                $orderMsg = $this->F_drawal->fields(array('moneypoints'))->eq(array('withdrawalnu'=>$data['order_number'],'uid'=>$uid))->getOne();
                if($orderMsg){
                      //申请或审核失败后，需把扣除的金额返还给主播
                      $redis = new redis();
                      $redis->connect($this->config['redis_ip'],$this->config['redis_port']);
                      $points = $redis->hget('huser_info:'.$uid,'points');
                      $newpoints = ($points+$orderMsg['moneypoints']);
                      $redis_status = $redis->hset('huser_info:'.$uid,'points', $newpoints);
                      
                      $redis->close();

                      $status = $this->F_user->update(array('points'=>$newpoints),' uid ='.$uid);
                      if(!$status){
                          echo   json_encode("操作失败"); die;
                      }else{
                          echo   json_encode("操作成功");die;
                      }
                }else{
                   echo json_encode("查询不到主播提现记录");die;
                }
                //}
            }
        }else if($data['status']==3){
             //状态为3的情况下   标示审核失败
             $orderState = $this->F_drawal->update(array('status'=>1),' withdrawalnu='.$data['order_number']);
             if ($state && $orderState) {
                echo json_encode("操作成功");die;
             }else{
                echo json_encode("操作失败");die;
             }
        }


        if($state){
              echo json_encode("操作成功");die;
        }else{
              echo json_encode("操作失败");die;
        }
    }

    /**
     * [auditAction 审核列表]
     * @return [type] [description]
     * @author morgan  
     * date    2015/4/11
     */
    public function  auditAction(){
        //判断权限
          if(!in_array(15,$this->_priv_arr)){
                  echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
                  return;   
          }
          $page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
          $page_size = 20;
          $offset = $page_size*($page-1);
          $data['keyword']  = addslashes($_GET['keyword']);
          if(!$_GET['search']['status']){
              $_GET['search']['status']['eq'] = 1;
          }
          /*搜索条件*/
          
          Search::getCondition($this->E_cash);
          /*搜索关键字*/
          ExtractController::keyword($data['keyword'],$this->E_cash);
          $list  = $this->E_cash->limit($offset,$page_size)->getAll();
          foreach ($list as $key => $value) {

              //主播信息  
              $redis = new redis();
              $redis->connect($this->config['redis_ip'],$this->config['redis_port']);
              $list[$key]['nickname'] = $redis->hget('huser_info:'.$value['uid'],'nickname');
              $list[$key]['points']   = $redis->hget('huser_info:'.$value['uid'],'points');
              $list[$key]['roled']    = $redis->hget('huser_info:'.$value['uid'],'roled');
              $list[$key]['username'] = $redis->hget('huser_info:'.$value['uid'],'username');
              //判断redis中是否有用户信息
              if(empty($list[$key]['username'])){
                  //如果redis没有用户信息，则去DB中查询
                  $userinfo = $this->F_user->fields(array('nickname','points','roled'))->eq(array("uid"=>$value['uid']))->getOne();
                  
                  if($userinfo){
                      $list[$key]['nickname'] = $userinfo['nickname'];
                      $list[$key]['points'] = $userinfo['points'];
                      $list[$key]['roled'] = $userinfo['roled'];
                  }else{
                      unset($list[$key]);
                  }
              }
              //获取实时用户可用余额
              $redis->set('checkBalance',true);
              $returnMsg = $this->global->vedioInterface(array('uid'=>$value['uid']),$this->config['usr_real_cash']);
              $list[$key]['realmoney'] = 0;
              if($returnMsg['ret'] == 1 ){
                  $list[$key]['realmoney'] = $returnMsg['msg'];
              }
              $redis->close();
              //扩展信息
              $usrExtends  = $this->F_userentends->fields(array("bankname",'banknumber','bankaddress'))->eq(array("uid"=>$value['uid']))->getOne();
              if($usrExtends){
                  $list[$key]  = array_merge($usrExtends,$list[$key]);
                  $list[$key]['issuing_bank'] = ExtractController::checkBankMessage($usrExtends['banknumber']);
              }
              //获取客服列表
              if(!empty($value['apply_editor'])){
                  $E_user = $this->E_admin->fields(array('admin_id,name'))->eq(array('status'=>0,'admin_id'=>$value['apply_editor']))->getOne();
                  $list[$key]['admin_id']   = $E_user['admin_id'];
                  $list[$key]['admin_name'] = $E_user['name'];
              }
              $list[$key]['points'] += $value['money']*10;
          }
          /*搜索条件*/
          Search::getCondition($this->E_cash);
          /*搜索关键字*/
          ExtractController::keyword($data['keyword'],$this->E_cash);
          $count  = $this->E_cash->fields(array('count(id) as nums'))->getOne();
          $nums   = $count['nums'];
          $data['list']  = $list;
          
          $data['pages'] = $this->global->pages($page_size,$nums,5,$page);
        
          
          include_once("./tpl/cash-audit.html");
    }

} 