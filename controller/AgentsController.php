<?php
/**
 * Created by PhpStorm.
 * User: raby
 * Date: 15-04-06
 * Time: 下午12:45
 * 域名管理
 */

header("content-type:text/html;charset=utf-8");

class AgentsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->_agentsModel = FrontAgentsModel::getInstance();
        $this->_redirectModel = FrontRedirectModel::getInstance();
        $this->_frontexitroomModel = FrontExitRoomModel::getInstance();
    }

    public function defaultAction(){
        echo "<script>location.href='./index.php?do=index-index';</script>";
        return;
    }
    /**
     * [addAction 新增代理]
     * @param array agents_type[ 代理类别:0"代理商",1"渠道商"]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function addAction()
    {
        //判断权限
        if(!in_array(59,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $data['atype'] =  $this->config['agents_type'];
        $data['testaccount'] = array("是","否");
        include_once("./tpl/agents-add.html");
    }
    /**
     * [ajaxAgentsAddAction AJAX新增代理]
     * @param string  $agentaccount [帐号]
     * @param string  $password [密码]
     * @param string  $nickname [昵称]
     * @param string  $atype [代理类型]
     * @param string  $rebate [返点]
     * @param string  $agentname [名称]
     * @param string  $withdrawalname [代理提款姓名]
     * @param string  $bank [提款银行类型]
     * @param string  $bankaccount [银行帐号]
     * @param string  $testaccount [测试帐号]
     * @author [raby]
     * @date(2015/04/13)
     */
    public function ajaxAgentsAddAction(){

        $agentaccount = $_POST['agentaccount'];
        $password = $_POST['password'];
        $nickname = $_POST['nickname'];
        $atype = $_POST['atype'];
        $rebate = $_POST['rebate'];
        $agentname = $_POST['agentname'];
        $withdrawalname = $_POST['withdrawalname'];
        $bank = $_POST['bank'];
        $bankaccount = $_POST['bankaccount'];
        $testaccount = $_POST['testaccount'];

        $regExpRate = "/^(?:0|[1-9][0-9]?|100)$/";
        $rs_regExpRate = preg_match($regExpRate, $rebate);
        $rs_data = $this->_agentsModel->eq(array('agentaccount'=>$agentaccount))->getOne();
        if($rs_data){
            echo "帐号已存在";
        }else{
            if($rs_regExpRate){
                $rs_insert = $this->_agentsModel->insert(
                    array('agentaccount'=>$agentaccount,'password'=>$password,'nickname'=>$nickname,'atype'=>$atype,
                        'rebate'=>$rebate,'agentname'=>$agentname,'withdrawalname'=>$withdrawalname,'bank'=>$bank,
                        'bankaccount'=>$bankaccount,'testaccount'=>$testaccount,'regtime'=>date('Y-m-d H:i:s',time())
                    )
                );
                if($rs_insert){
                    echo "添加成功";
                }else{
                    echo "添加失败";
                }
            }else{
                echo "失败,返点必须为0-100的数字";
            }
        }
    }

    /**
     * [listAction 代理列表]
     * @param String agentaccount [代理帐号]
     * @param String atype [代理类型]
     * @param String id [代理ID]
     * @param String regtime [代理注册时间]
     * @param String nickname [代理昵称]
     * @param String multi_key [查询类型]
     * @param String $multi_ge []
     * @param String $multi_le []
     * @author raby
     * date   2015/04/16
     */
    public function  listAction(){
        //判断权限
        if(!in_array(62,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $multi = isset($_GET['search']['multi_key']) ? trim($_GET['search']['multi_key']) : '';
        $multi_ge = isset($_GET['search']['multi']['ge']) ? trim($_GET['search']['multi']['ge']) : '';
        $multi_le = isset($_GET['search']['multi']['le']) ? trim($_GET['search']['multi']['le']) : '';

        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size * ($page - 1);

        $search = $_GET['search'];
        unset($search['multi']);
        unset($search['multi_key']);
        $whereSql = ' 1';
        $whereSql .= $this->whereSql($search);
        $sql = '';
        if($multi_ge&&$multi_le){
            $multi_ge = $multi_ge*10;
            $multi_le = $multi_le*10;

            switch($multi){
                case 'cost_num';
                     $whereSql .=" and SUM(ml.points)>=$multi_ge and sum(ml.points)<=$multi_le";
                    $sql .= "SELECT  a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                                FROM video_agents as a
                                LEFT JOIN `video_agent_relationship` AS ar ON a.id=ar.aid
                                LEFT JOIN video_mall_list AS ml ON ar.uid=ml.send_uid
                                group by ar.uid
                                    having  $whereSql";
                    break;
                case 'recharge_num';
                    $whereSql .=" and SUM(ml.points)>=$multi_ge and sum(ml.points)<=$multi_le";
                    $whereSql2 = " where ml.pay_status=1 and ml.pay_type=1 ";
                    $sql .= "SELECT a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                                FROM video_agents as a
                                LEFT JOIN `video_agent_relationship` AS ar ON a.id=ar.aid
                                    LEFT JOIN video_recharge AS ml ON ar.uid=ml.uid
                                    $whereSql2
                                    group by ar.uid
                                    having  $whereSql";
                    break;
                case 'rebate_num';
                    $whereSql .=" and SUM(ml.points)*(a.rebate/100)>=$multi_ge and SUM(ml.points)*(a.rebate/100)<=$multi_le";
                    $sql .= "SELECT a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                                FROM video_agents as a
                                LEFT JOIN `video_agent_relationship` AS ar ON a.id=ar.aid
                                LEFT JOIN video_mall_list AS ml ON ar.uid=ml.send_uid
                                group by ar.uid
                                    having $whereSql";
                    break;
                case 'points';
                    $whereSql .=" and SUM(ml.points)*(a.rebate/100)>=$multi_ge and sum(ml.points)*(a.rebate/100)<=$multi_le";
                    $whereSql2 = " where ml.pay_status=1 and ml.pay_type=1 ";
                    $sql .= "SELECT a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                                FROM video_agents as a
                                LEFT JOIN `video_agent_relationship` AS ar ON a.id=ar.aid
                                LEFT JOIN video_recharge AS ml ON ar.uid=ml.uid
                                $whereSql2
                                group by ar.uid
                                having  $whereSql";
                    break;
                default:
                    $sql .= "SELECT a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                                FROM  `video_agents` as a where $whereSql";
                    ;
            }
        }else{
                    $sql .= "SELECT a.id as id,a.agentname as agentname,a.agentaccount as agentaccount,a.nickname as nickname,a.atype as atype,a.rebate as rebate, a.regtime as regtime
                            FROM  `video_agents` as a where $whereSql";
        }
        $limit_sql = $sql." limit $offset,$page_size";
        $agents_data = $this->_agentsModel->limit($offset,$page_size)->getAll($limit_sql);
        $rs_data = array();
        foreach($agents_data as $k=>$v){
            $rs_data[$k] =$v;
            $id = $v['id'];
            //团队累计消费总额
            $points_sql = "SELECT SUM(ml.points) as cost_num FROM video_agent_relationship as ar
                        LEFT JOIN video_mall_list AS ml ON ar.uid=ml.send_uid where ar.aid=$id";
            $rs_points = $this->_agentsModel->getOne($points_sql);
            $rs_data[$k]['cost_num'] = number_format($rs_points['cost_num']/10,2,'.','');

            //团队累计充值总额
            $recharge_sql = "SELECT SUM(ml.points) as recharge_num FROM video_agent_relationship as ar
                        LEFT JOIN video_recharge AS ml ON ar.uid=ml.uid where ml.pay_status=1 and ml.pay_type=1 and ar.aid=$id";
            $rs_recharge = $this->_agentsModel->getOne($recharge_sql);
            $rs_data[$k]['recharge_num'] = number_format($rs_recharge['recharge_num']/10,2,'.','');

            //下属总人数
            $member_sql = "SELECT count(id) as recharge_num FROM video_agent_relationship as ar  where ar.aid=$id";
            $rs_member = $this->_agentsModel->getOne($member_sql);
            $rs_data[$k]['member_num'] = $rs_member['recharge_num'];

            //团队累计返点
            $rebate_num = ($rs_points['cost_num']*$v['rebate'])/100;
            $rs_data[$k]['rebate_num'] = number_format($rebate_num/10,2,'.','');

            //代理帐户余额
            $points_num = ($rs_recharge['recharge_num']*$v['rebate'])/100;
            $rs_data[$k]['points'] = number_format($points_num/10,2,'.','');
        }


        $agents_all = $this->_agentsModel->getAll($sql);
        $nums = count($agents_all);

        //显示跳转页面列表
        $data['pages'] = $this->global->pages($page_size, $nums, 5, $page);
        $data['query_type']=array('cost_num'=>'累计消费总额','recharge_num'=>'累计充值总额','rebate_num'=>'累计返点','points'=>'代理余额');
        $data['atype'] = $this->config['agents_type'];
        $data['agents_data']=$rs_data;
        include_once("./tpl/agents-list.html");
    }

    /**
     * [memberListAction 代理的成员列表]
     * @param string  $username [用户账号]
     * @param string  lv_rich [会员等级]
     * @param string  $created_ge [用户注册时间-启]
     * @param string  $created_le [用户注册时间-止]
     * @param string  $points_ge [余额-启]
     * @param string  $points_le [余额-止]
     * @param string  multi_key [条件排序]
     * @param string  aid [代理ID]
     * @author [raby]
     * @date(2015/04/16)
     */
    public function memberListAction(){
        $multi_key = isset($_GET['search']['multi_key']) ? trim($_GET['search']['multi_key']) : '';
        $aid = isset($_GET['aid']) ? trim($_GET['aid']) : '';

        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size * ($page - 1);

        $search = $_GET['search'];
        $search['aid']=$aid;
        $search['points']['ge'] = $search['points']['ge'] ? $search['points']['ge']*10 : '';
        $search['points']['le'] = $search['points']['ge'] ?  $search['points']['le']*10 : '';
        unset($search['multi_key']);
        $whereSql = 'where 1';

        $whereSql .= $this->whereSql($search);
        switch($multi_key){
            case 'lv_rich':
                $whereSql.=' order by lv_rich ASC';
                break;
            case 'created':
                $whereSql.=' order by created DESC';
                break;
            case 'points':
                $whereSql.=' order by points DESC';
                break;
            case 'logined':
                $whereSql.=' order by logined DESC';
                break;
            default:;
        }
        $sql="SELECT * FROM `video_agent_relationship` as  ar LEFT JOIN video_user as u ON ar.uid=u.uid $whereSql";

        $limit_sql = $sql." limit $offset,$page_size";
        $member_data = $this->_agentsModel->getAll($limit_sql);

        //累计登录
        foreach($member_data as $k=>$v){
            $user_online = $this->_frontexitroomModel->fields(array('count(uid) as login_num','sum(duration) as online_times'))->eq(array('uid'=>$v['uid']))->getOne();

            //查看用户登录次数和累计在线时间
            if($user_online){
                $member_data[$k]['login_long_num']  =  $this->global->sec2time($user_online['online_times']);
            }
        }
        //代理团队名称
        $agents = $this->_agentsModel->fields(array('agentname'))->eq(array('id'=>$aid))->getOne();

        //显示跳转页面列表
        $agents_all = $this->_agentsModel->getAll($sql);
        $nums = count($agents_all);
        $data['pages'] = $this->global->pages($page_size, $nums, 5, $page);

        $data['member_data']=$member_data;
        $data['agentname']=$agents['agentname'];
        $data['query_type']=array('lv_rich'=>'按等级从小到大','created'=>'按注册时间从长到短','points'=>'按余额从高到低','logined'=>'按登录时间从长到短');
        include_once("./tpl/agents-member-list.html");
    }

    /**
     * @param $search
     * @description 适用于无法利用数据库封装的查询语句
     * @return string
     * @author [raby]
     * @date(2015/04/24)
     */
    private function whereSql($search){
        $whereSql = '';
        foreach($search as $k=>$v){
            if(trim($v)!==''){
                if( is_array($v)){
                    foreach($v as $kk=>$vv ){
                        if(trim($vv)!==''){
                            if($kk=='ge'){
                                $whereSql.=" and $k>='$vv'";
                            }
                            if($kk=='le'){
                                $whereSql.=" and $k<='$vv'";
                            }
                        }
                    }
                }else{
                    $whereSql.=" and $k='$v'";
                }
            }
        }
        return $whereSql;
    }
    /**
     * [editAction 修改代理]
     * @param string  aid [代理ID]
     * @author [raby]
     * @date(2015/04/16)
     */
    public function editAction(){
        $data['atype']=$this->config['agents_type'];
        $aid = $_GET['aid'];

        $data['agents'] = $this->_agentsModel->eq(array('id'=>$aid))->getOne();
        include_once("./tpl/agents-edit.html");
    }
    /**
     * [ajaxEditAction ajaxEditAction修改代理]
     * @param string  agentaccount [代理帐号]
     * @param string  password [密码]
     * @param string  nickname [代理昵称]
     * @param string  atype [代理类型]
     * @param string  rebate [返点]
     * @param string  agentname [代理团队名称]
     * @param string  withdrawalname [代理提款姓名]
     * @param string  bank [银行类型]
     * @param string  bankaccount [银行帐号]
     * @param string  id [代理ID]
     * @author [raby]
     * @date(2015/04/16)
     */
    public function ajaxEditAction(){
        $id = $_POST['id'];
        $rebate = $_POST['rebate'];
        $updateArray = $_POST;
        $regExpRate = "/^(?:0|[1-9][0-9]?|100)$/";
        $rs_regExpRate = preg_match($regExpRate, $rebate);
        if($rs_regExpRate){
            $rs_update = $this->_agentsModel->update($updateArray," id=$id");
            if($rs_update){
                echo "更新成功";
            }else{
                echo "更新失败";
            }
        }else{
            echo "失败,返点必须为0-100的数字";
        }
    }
} 