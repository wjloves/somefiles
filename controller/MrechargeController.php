<?php
/**
 * Created by PhpStorm.
 * User: raby
 * Date: 15-04-07
 * Time: 下午12:45
 */

header("content-type:text/html;charset=utf-8");
class MrechargeController extends BaseController{
    public function __construct(){
         parent::__construct();
        $this->_endMrechargeModel = EndMrechargeModel::getInstance();
        $this->_endMrechargeStatModel = EndRechargeModel::getInstance();
        $this->_frontUserModel = FrontUserModel::getInstance();
        $this->_frontAgentsModel = FrontAgentsModel::getInstance();
    }

    public function defaultAction(){
        echo "<script>location.href='./index.php?do=index-index';</script>";
        return;
    }
    /**
     * [addApplyAction 人工充值（申请）]
     * @param array recharge_account_type [充值帐号类型 0'代理',1'用户');]
     * @param array recharge_type [充值类型 0'人工充值',1'活动奖励',2'平台赔偿');]
     * @author raby
     * date   2015/04/07
     */
    public function addApplyAction(){
        //判断权限
        if(!in_array(53,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $data['rechargetype'] = $this->config['recharge_type'];
        include_once("./tpl/Mrecharge-addapply.html");
    }

    /**
     * [checkAction 人工充值（申请）]
     * @param String $uid [充值帐号]
     * @param String $rechargetype [充值账号类型]
     * @param String $type [充值类型]
     * @param String $plus_gold [充值金额]
     * @param String $remark [备注]
     * @author raby
     * date   2015/04/07
     *
     */
    public function ajaxAddapplyAction(){
        //查询
        $uid = isset($_POST['uid']) ? trim($_POST['uid']) : 0;
        $rechargetype = isset($_POST['rechargetype']) ? trim($_POST['rechargetype']) : 0;
        $plus_gold = isset($_POST['plus_gold']) ? trim($_POST['plus_gold']) : 0;
        $remark = isset($_POST['remark']) ? trim($_POST['remark']) : 0;

            $query_sql="select uid from video_user where uid='$uid' or username='$uid'";
            $rs_user = $this->_frontUserModel->getOne($query_sql);
            if(empty($rs_user)){
                    echo "账号不存在";
            }else{
                if(is_numeric($plus_gold)){
                    if(empty($remark)){
                        echo "备注不能为空";
                        return;
                    }
                    $data =  array(
                        'uid'=>$rs_user['uid'],
                        'plus_gold'=>$plus_gold,
                        'minus_gold'=>0,
                        'rechargetype'=>$rechargetype,
                        'status'=>0,    //待审核
                        'apply_name'=>$this->_login['name'],
                        'check_name'=>'',
                        'apply_time'=>date('Y-m-d H:i:s',time()),
                        'check_time'=>'0000-00-00 00:00:00',
                        'remark'=>$remark,
                    );
                    $rs_insert = $this->_endMrechargeModel->insert($data);
                    if($rs_insert){
                        echo "成功";
                    }else{
                        echo "失败";
                    }
                }else{
                    echo "金额必须为数据";
                }
            }
    }
    /**
     * [checkAction 人工充值（审核）]
     * @param String status [充值帐号]
     * @param String apply_name [充值账号类型]
     * @param String check_name [充值类型]
     * @param String check_time [充值金额]
     * @author raby
     * date   2015/04/07
     */
    public function checkAction(){
        //判断权限
        if(!in_array(54,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $check_time_ge = isset($_GET['search']['check_time']['ge']) ? trim($_GET['search']['check_time']['ge']) : '';
        $check_time_le = isset($_GET['search']['check_time']['le']) ? trim($_GET['search']['check_time']['le']) : '';

        $where = array();
        $search = $_GET['search'];
        foreach($search as $k=>$v){
            if($v===''){
            }else{
                if(is_array($v)){
                }else{
                    $where[$k]=$v;
                }
            }
        }

        if($check_time_ge&&$check_time_le){
            $list = $this->_endMrechargeModel->eq($where)->gt(array('plus_gold'=>0))->ge(array('check_time'=>$check_time_ge))->le(array('check_time'=>$check_time_le))->order("apply_time desc")->getAll();
        }else{
            $list = $this->_endMrechargeModel->eq($where)->gt(array('plus_gold'=>0))->order("apply_time desc")->getAll();
        }

       
        //调用代理接口----所属团队
        foreach($list as $k=>$v){
            $uid = $v['uid'];
            $sql = "SELECT agentname FROM `video_agents` as a LEFT JOIN video_agent_relationship as r on a.id = r.aid WHERE r.uid=$uid";
            $agents = $this->_frontAgentsModel->getOne($sql);
            if($agents){
                $list[$k]['team'] = $agents['agentname'];
            }else{
                $list[$k]['team'] = '';
            }
            $user = $this->_frontUserModel->eq(array('uid'=>$uid))->getOne();
            if($user){
                $list[$k]['username'] = $user['username'];
            }
        }

        $data['mrecharge_data'] = $list;
        $data['status'] = array(0=>'等待审核',1=>'审核通过',2=>'审核失败');
        include_once("./tpl/Mrecharge-check.html");
    }

    /**
     * [ajaxCheckAction AJAX人工充值（审核）]
     * @param String id [ID]
     * @param String status [是否通过]
     * @param String check_remark [审核不通过原因]
     * @author raby
     * date   2015/04/07
     */
    public function ajaxCheckAction(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $check_remark = $_POST['check_remark'];
        $rs_update = $this->_endMrechargeModel->update(array('status'=>$status,'check_name'=>$this->_login['name'],'check_time'=>date('Y-m-d H:i:s',time()),'check_remark'=>$check_remark), ' id='.$id);
        if($rs_update){
            //是否审核通过
            if($status==1){
                $rs_data = $this->_endMrechargeModel->eq(array('id'=>$id))->getOne();
                $ExcuseDate = array('uid'=>$rs_data['uid'],'points'=>$rs_data['plus_gold']*10);
                $status  = $this->global->vedioInterface($ExcuseDate,$this->config['recharge_url']);
                if($status){
                    //是否扣款成功
                    if($status['ret']==1){
                        echo "充值成功";

                    }else{
                        echo "充值失败".$status['ret'];
                    }
                    $ret = $status['ret'];
                }else{
                    $ret = -1;
                    echo "充值接口调用失败";
                }
                //写日志
                $field = array(
                    'uid'=>$rs_data['uid'],
                    'admin_id'=>$this->_login['admin_id'],
                    'charge_amount'=>$rs_data['plus_gold'],
                    'content'=>$rs_data['remark'],
                    'ctime'=>date('Y-m-d H:i:s',time()),
                    'status'=>$ret,
                    'type'=>1       //`type` tinyint(2) NOT NULL DEFAULT '0' COMMENT '手动：  1 ： 增加    2 ：  扣减',
                );
                $rs_insert = $this->_endMrechargeStatModel->insert($field);
            }else{
                echo "更新成功";
            }
        }else{
            echo "更新失败";
        }
    }



    /**
     * [minusApplyAction 人工扣减（申请）]
     * @param  array type   [充值帐号类别：0'代理'，1'用户']
     * @author raby
     * date   2015/04/07
     */
    public function minusApplyAction(){
        //判断权限
        if(!in_array(55,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        include_once("./tpl/Mrecharge-minusapply.html");
    }

    /**
     * [ajaxMinusApplyAction AJAX人工扣减（申请）]
     * @param String $uid [扣减帐号]
     * @param String $rechargetype [扣减账号类型]
     * @param String $minus_gold [扣减金额]
     * @param String $remark [备注]
     * @author raby
     * date   2015/04/07
     */
    public function ajaxMinusApplyAction(){
        //查询
        $uid = isset($_POST['uid']) ? trim($_POST['uid']) : 0;
        $minus_gold = isset($_POST['minus_gold']) ? trim($_POST['minus_gold']) : 0;
        $remark = isset($_POST['remark']) ? trim($_POST['remark']) : 0;

            $query_sql="select uid from video_user where uid='$uid' or username='$uid'";
            $rs_user = $this->_frontUserModel->getOne($query_sql);
            if(empty($rs_user)){
                echo "账号不存在";
            }else{
                if(is_numeric($minus_gold)){
                    if($remark){
                        $data =  array(
                            'uid'=>$rs_user['uid'],
                            'plus_gold'=>0,
                            'minus_gold'=>$minus_gold,
                            'status'=>0,    //待审核
                            'apply_name'=>$this->_login['name'],
                            'check_name'=>'',
                            'apply_time'=>date('Y-m-d H:i:s',time()),
                            'check_time'=>'0000-00-00 00:00:00',
                            'remark'=>$remark,
                        );
                        $rs_insert = $this->_endMrechargeModel->insert($data);
                        if($rs_insert){
                            echo "成功";
                        }else{
                            echo "失败";
                        }
                    }else{
                        echo "备注不能为空";
                    }
                }else{
                    echo "金额必须是数字";
                }
            }
    }

    /**
     * [checkMinusAction 人工扣减（审核）]
     * @param String status [扣减帐号]
     * @param String apply_name [申请人]
     * @param String check_name [审核人]
     * @param String check_time_ge [审核开始时间]
     * @param String check_time_le [审核结束时间]
     * @author raby
     * date   2015/04/07
     */
    public function checkMinusAction(){
        //判断权限
        if(!in_array(56,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $check_time_ge = isset($_GET['search']['check_time']['ge']) ? trim($_GET['search']['check_time']['ge']) : '';
        $check_time_le = isset($_GET['search']['check_time']['le']) ? trim($_GET['search']['check_time']['le']) : '';

        $where = array();
        foreach($_GET['search'] as $k=>$v){
            if($v===''){
            }else{
                if(is_array($v)){
                }else{
                    $where[$k]=$v;
                }
            }
        }
        if($check_time_ge&&$check_time_le){
            $list = $this->_endMrechargeModel->eq($where)->gt(array('minus_gold'=>0))->ge(array('check_time'=>$check_time_ge))->le(array('check_time'=>$check_time_le))->order("apply_time desc")->getAll();
        }else{
            $list = $this->_endMrechargeModel->eq($where)->gt(array('minus_gold'=>0))->order("apply_time desc")->getAll();
        }

        //调用代理接口----所属团队
        foreach($list as $k=>$v){
            $uid = $v['uid'];
            $sql = "SELECT agentname FROM `video_agents` as a LEFT JOIN video_agent_relationship as r on a.id = r.aid WHERE r.uid=$uid";
            $agents = $this->_frontAgentsModel->getOne($sql);
            if($agents){
                $list[$k]['team'] = $agents['agentname'];
            }
            $user = $this->_frontUserModel->eq(array('uid'=>$uid))->getOne();
            if($user){
                $list[$k]['username'] = $user['username'];
            }
        }

        $data['mrecharge_data'] = $list;
        $data['status'] = array(0=>'等待审核',1=>'审核通过',2=>'审核失败');
        include_once("./tpl/Mrecharge-checkminus.html");
    }

    /**
     * [ajaxCheckAction AJAX人工扣减（审核）]
     * @param String id [ID]
     * @param String status [是否通过]
     * @param String check_remark [审核不通过原因]
     * @author raby
     * date   2015/04/07
     */
    public function ajaxCheckMinusAction(){
        $id = $_POST['id'];
        $status = $_POST['status'];
        $check_remark = $_POST['check_remark'];
        $rs_update = $this->_endMrechargeModel->update(array('status'=>$status,'check_name'=>$this->_login['name'],'check_time'=>date('Y-m-d H:i:s',time()),'check_remark'=>$check_remark), ' id='.$id);
        if($rs_update){
            //是否审核通过
            if($status==1){
                $rs_data = $this->_endMrechargeModel->eq(array('id'=>$id))->getOne();
                $ExcuseDate = array('uid'=>$rs_data['uid'],'points'=>$rs_data['minus_gold']*10);
                $status  = $this->global->vedioInterface($ExcuseDate,$this->config['minus_gold_url']);
                if($status){
                    //是否扣款成功
                    if($status['ret']==1){
                        echo "扣款成功";
                    }else{
                        echo "扣款失败".$status['ret'];
                    }
                    $ret = $status['ret'];
                }else{
                    $ret = -1;
                    echo "充值接口调用失败";
                }
                //写日志
                $field = array(
                    'uid'=>$rs_data['uid'],
                    'admin_id'=>$this->_login['admin_id'],
                    'charge_amount'=>$rs_data['minus_gold'],
                    'content'=>$rs_data['remark'],
                    'ctime'=>date('Y-m-d H:i:s',time()),
                    'status'=>$ret,
                    'type'=>2       //手动：  1 ： 增加    2 ：  扣减'
                );
                $rs_insert = $this->_endMrechargeStatModel->insert($field);
            }else{
                echo "更新成功";
            }
        }else{
            echo "更新失败";
        }
    }

    /**
     * [ajaxGetOneAction 查看用户是否存在]
     * @param [type] [varname] [description]
     * @author raby
     * @return INT | JSON [数字标示符或JSON格式字符串]
     * date 2015/04/20
     */
    public function  ajaxGetOneAction(){
        $user = addslashes($_GET['id']);
        $usermsg = $this->_frontUserModel->getOne("SELECT uid,username,nickname,lv_rich FROM `video_user` where uid='".$user."' OR username='".$user."' OR nickname = '".$user."'");
        if(!$usermsg){
            echo 2;return;
        }
        echo json_encode($usermsg);
    }
} 