<?php
/**
 * Created by PhpStorm.
 * User: morgan
 * Date: 14-12-14
 * Time: 下午19::30
 * 提款
 */

class ExtractController extends BaseController{
    public function __construct(){
        parent::__construct();
        $this->F_user        = FrontUserModel::getInstance();
        $this->F_drawal      = FrontdrawalModel::getInstance();
        $this->F_userentends = FrontUserExtendsModel::getInstance();
        $this->E_admin       = EndAdminModel::getInstance();
        $this->E_cash        = EndCashModel::getInstance();
        
    }
    /**
     * [defaultAction 提款审批]
     * @author morgan
     * date(2014/11/03)
     */
    public function defaultAction(){

        $page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size*($page-1);
        $data['keyword']  = addslashes($_GET['keyword']);

        /*搜索条件*/
        if(empty($_GET['search']['order'])){
            $_GET['search']['order'] = 'created';
        } 
        Search::getCondition($this->F_drawal);
        /*搜索关键字*/
        self::keyword($data['keyword'],$this->F_drawal);
        $list  = $this->F_drawal->limit($offset,$page_size)->getAll();

        //查询用户表和用户扩展表
        foreach ($list as $key => $value) {
            //主播信息  
            $redis = new redis();
            $redis->connect($this->config['redis_ip'],$this->config['redis_port']);

            $list[$key]['nickname'] = $redis->hget('huser_info:'.$value['uid'],'nickname');
            $list[$key]['username'] = $redis->hget('huser_info:'.$value['uid'],'username');
            $list[$key]['points']   = $redis->hget('huser_info:'.$value['uid'],'points');
            $list[$key]['roled']    = $redis->hget('huser_info:'.$value['uid'],'roled');
            
            
            //判断redis中是否有用户信息
            if(empty($list[$key]['username'])){

                //如果redis没有用户信息，则去DB中查询
                $userinfo = $this->F_user->fields(array("uid",'nickname','username','points','roled'))->eq(array("uid"=>$value['uid']))->getOne();
                
                if($userinfo){
                    $list[$key]['nickname'] = $userinfo['nickname'];
                    $list[$key]['username'] = $userinfo['username'];
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
                $list[$key]['issuing_bank'] = self::checkBankMessage($usrExtends['banknumber']);
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
            if(!empty($value['editor'])){
                $E_user = $this->_endadminModel->fields(array('admin_id,name'))->eq(array('status'=>0,'admin_id'=>$value['editor']))->getOne();
                $list[$key]['admin_id']   = $E_user['admin_id'];
                $list[$key]['admin_name'] = $E_user['admin_name'];
            }
        }
        /*搜索条件*/
        Search::getCondition($this->F_drawal);
        /*搜索关键字*/
        self::keyword($data['keyword'],$this->F_drawal);
        $count  = $this->F_drawal->fields(array('count(id) as nums'))->getOne();
        $nums   = $count['nums'];
        $data['list']  = $list;
        $data['pages'] = $this->global->pages($page_size,$nums,5,$page);
        include_once("./tpl/extract.html");
    }

    /**
     * [keyword 搜索]
     * @return  object
     * @author  morgan
     * date   2014/12/14
     */
    public static function keyword($keyword,$model){
        $F_user        = FrontUserModel::getInstance();
        $F_userentends = FrontUserExtendsModel::getInstance();
        if($keyword){
                $users_id = $F_user->fields(array('uid'))->eq(array('status'=>1))->like(array('username'=>$keyword,'uid'=>$keyword))->getAll(); 
                $uids = array('111111111111111');
                if($users_id){
                    unset($uids[0]);
                    foreach ($users_id  as  $v) {
                        $uids[] = $v['uid'];
                    }
                }
                $model->in(array('uid'=>$uids));
        }
        if(isset($_GET['banknumber'])&&!empty($_GET['banknumber'])){
                $banknumber = intval($_GET['banknumber']);
                $bank_uids = $F_userentends->fields(array('uid'))->like(array('banknumber'=>$banknumber))->getAll();
                $uids = array('111111111111111');
                if($bank_uids){
                    unset($uids[0]);
                    foreach ($bank_uids  as  $v) {
                        $uids[] = $v['uid'];
                    }
                }
                $model->in(array('uid'=>$uids));
        }
        
        return $model;
    }

    /**
     * [checkBankMessage 通过银行卡号匹配银行信息]
     * @param  string $banknumber [银行卡号]
     * @return string             [银行名称]
     */
    public static function checkBankMessage($banknumber = ''){
          if(empty($banknumber)){
            return "未知";
          }
          $E_bank        = EndBankModel::getInstance();
          $number = substr($banknumber,6);
          $bankmessage = $E_bank->fields(array('issuing_bank'))->eq(array('bin'=>$number))->getOne();
          if(!empty($bankmessage)){
            return $bankmessage['issuing_bank'];
          }else{
            return "未知";
          }

    }

    /**
     * [ajaxAuditAction 查询一条主播提现申请详细信息]
     * @return json [处理结果]
     */
    public function  ajaxGetOneAction(){
            $id = intval($_GET['id']);
            $usrMsg = $this->F_drawal->getOne("SELECT e.realname,e.bankname,e.banknumber,e.bankaddress,w.withdrawalnu,w.uid,w.money FROM `video_withdrawal_list` w LEFT JOIN `video_user_extends` e ON e.uid=w.uid where  w.id=".$id);
            if($usrMsg){
                echo json_encode($usrMsg);
            }else{
                echo 0;
            }
    }

    /**
     * [ajaxEditAction 主播提现审批]
     * @param array $data   订单信息
     * @author morgan  
     * date  2015/4/11
     */
    public function ajaxEditAction(){
        $data = json_decode($_POST['data'],true);
        $condition['status'] = $data['status'];
        $condition['content'] = $data['content'];
        $status = $this->F_drawal->update($condition," id =".$data['order_id']);
        if(!$status){
            echo json_encode("操作失败");
        }else if($data['status']==1){
            $data['ctime']  = date('Y-m-d H:i:s',time());
            $data['status'] = 0;
            $data['host_editor'] = $this->_login['admin_id'];
            unset($data['content']);
            $state = $this->E_cash->insert($data);
            if($state){
                echo json_encode("操作成功");
            }else{
                $condition['status'] = 0;
                $this->F_drawal->update($condition," id =".$data['order_id']);
                echo json_encode("操作失败");
            }
        }else{
            echo json_encode("操作成功");
        }
    }
} 