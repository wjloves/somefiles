<?php
/**
 * Created by PhpStorm.
 * User: raby
 * Date: 15-04-06
 * Time: 下午12:45
 * 域名管理
 */

header("content-type:text/html;charset=utf-8");

class DomainController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->_domainModel = FrontDomainModel::getInstance();
        $this->_redirectModel = FrontRedirectModel::getInstance();
        $this->_agentsModel = FrontAgentsModel::getInstance();
    }

    public function defaultAction(){
        echo "<script>location.href='./index.php?do=index-index';</script>";
        return;
    }
    /**
     * [addAction 新增域名]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function addAction()
    {
        //判断权限
        if(!in_array(50,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        include_once("./tpl/domain-add.html");
    }

    /**
     * [ajaxDomainAddAction AJAX新增域名]
     * @param String $url_domain [域名]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function ajaxDomainAddAction()
    {
        $url_domain = isset($_POST['url_domain']) ? trim($_POST['url_domain']) : null;

        if ($url_domain) {
            //不存在，则添加
            $sql = "INSERT INTO `video_domain`(url,type,ctime)  SELECT '$url_domain', '0',now() FROM DUAL WHERE NOT EXISTS(SELECT id FROM video_domain WHERE url='$url_domain')";
            $this->_domainModel->execute($sql);
            $id = $this->_domainModel->insert_id();
            if ($id) {
                echo "增加成功";
            } else {
                echo "增加失败,请检查域名是否已经存在";
            }
        } else {
            echo "输入数据非法，无效的域名";
        }
    }

    /**
     * [ajaxDomainDelAction AJAX删除域名]
     * @param String $url_domain [域名]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function ajaxDomainDelAction()
    {
        $url_domain = isset($_POST['url_domain']) ? trim($_POST['url_domain']) : null;
        if ($url_domain) {
            //查询域名ID
            $data = $this->_domainModel->eq(array('url' => $url_domain, 'type' => 0))->getOne();
            if ($data) {
                //删除关联数据
                $rs_delete = $this->_redirectModel->delete('did=' . $data['id']);
                if ($rs_delete) {
                    //删除域名
                    $rs_domain = $this->_domainModel->delete('id = ' . $data['id']);
                    if ($rs_domain) {
                        echo "成功";
                    } else {
                        echo "失败";
                    }
                } else {
                    echo "关联失败";
                }
            }else{
                echo "失败,不存在的域名";
            }
        } else {
            echo "失败,输入无效的域名";
        }
    }

    /**
     * [ajaxPageAddAction AJAX新增跳转页面]
     * @param String $url_page [跳转页面]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function ajaxPageAddAction()
    {
        $url_page = isset($_POST['url_page']) ? trim($_POST['url_page']) : null;
        $regex = "/^((http|https):\/\/)?([\w-_]+\.)+([A-Za-z]{2,3})(\/[\w-_]+)*\/?$/";
        $rs_regex = preg_match($regex, $url_page);
        if ($url_page&&$rs_regex) {
            //不存在，则添加
            $sql = "INSERT INTO `video_domain`(url,type,ctime)  SELECT '$url_page', '1',now() FROM DUAL WHERE NOT EXISTS(SELECT id FROM video_domain WHERE url='$url_page')";
            $this->_domainModel->execute($sql);
            $id = $this->_domainModel->insert_id();
            if ($id) {
                echo "增加成功";
            } else {
                echo "增加失败,请检查跳转页面是否已经存在";
            }
        } else {
            echo "输入数据非法，无效的页面地址";
        }
    }

    /**
     * [ajaxPageDelAction AJAX删除跳转页面]
     * @param String $url_page [跳转页面]
     * @author [raby]
     * @date(2015/04/06)
     */
    public function ajaxPageDelAction()
    {
        $url_page = isset($_POST['url_page']) ? trim($_POST['url_page']) : null;
        if ($url_page) {
            //查找出id
            $page_data = $this->_domainModel->eq(array('url' => $url_page, 'type' => 1))->getOne();
            //删除关联数据
            if($page_data){
                $rs_delete = $this->_redirectModel->delete('rid=' . $page_data['id']);
                if ($rs_delete) {
                    //删除跳转页面
                    if ($this->_domainModel->delete('url = "' . $url_page . '" and type=1')) {
                        echo "成功";
                    } else {
                        echo "失败";
                    }
                } else {
                    echo "关联成功";
                }
            }else{
                echo "不存在的跳转页面";
            }
        } else {
            echo "非法地址";
        }
    }

    /**
     * [redirectSetAction 跳转设定]
     * @param String $url_domain [域名]
     * @param String $url_page [跳转页面]
     * @author raby
     * date   2015/04/6
     */
    public function redirectSetAction()
    {
        //判断权限
        if(!in_array(51,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $url_domain = isset($_GET['search']['url_domain']) ? trim($_GET['search']['url_domain']) : null;
        $url_page = isset($_GET['search']['url_page']) ? trim($_GET['search']['url_page']) : null;

        /**
         * 设置跳转设定，添加
         */
        $tip_msg = '';
        if ($url_domain && $url_page) {
            //查询域名ID，type:0域名 ，1跳转页面
            $domain_data = $this->_domainModel->eq(array('url' => $url_domain, 'type' => 0))->getOne();
            //查询跳转页面ID
            $page_data = $this->_domainModel->eq(array('url' => $url_page, 'type' => 1))->getOne();
            if ($domain_data && $page_data) {
                $rs_redirect = $this->_redirectModel->eq(array('did' => $domain_data['id'], 'rid' => $page_data['id']))->getOne();
                //存在关联，则不处理
                if (empty($rs_redirect)) {
                    $field = array('did' => $domain_data['id'], 'rid' => $page_data['id'], 'ctime' => date('Y-m-d H:i:s', time()));
                    $rs_insert = $this->_redirectModel->insert($field);
                    if($rs_insert){
                        $tip_msg = "添加成功";
                    }else{
                        $tip_msg = "添加失败";
                    }
                }else{
                    $tip_msg = "已存在关联";
                }
            }
        }

        /**
         * 查询显示所有关联记录
         */
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size * ($page - 1);
        $redirect_data = $this->_redirectModel->limit($offset, $page_size)->getAll();
        foreach ($redirect_data as $k => $v) {
            $redirect_data[$k]['url_domain'] = $this->_domainModel->eq(array('id' => $v['did']))->getOne()['url'];
            $redirect_data[$k]['url_page'] = $this->_domainModel->eq(array('id' => $v['rid']))->getOne()['url'];
        }

        $count = $this->_domainModel->fields(array("count(id) as nums"))->getOne();
        $nums = $count['nums'];

        //显示域名列表
        $data['domain_data'] = $this->_domainModel->eq(array('type' => 0))->getAll();
        //显示跳转页面列表
        $data['page_data'] = $this->_domainModel->eq(array('type' => 1))->getAll();
        //显示跳转设定列表
        $data['redirect_data'] = $redirect_data;
        $data['pages'] = $this->global->pages($page_size, $nums, 5, $page);
        $data['tip_msg'] = $tip_msg;

        include_once("./tpl/domain-redirectset.html");
    }

    /**
     * [domainAgentsAction 添加域名至代理]
     * @param String did [域名ID]
     * @param String aid [代理ID]
     * @author raby
     * date   2015/04/13
     */
    public function domainAgentsAction(){
        //判断权限
        if(!in_array(60,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $did = $_GET['search']['did'];
        $aid = $_GET['search']['aid'];
        /*
         * 设置域名至代理关联
         * 域名有关联，更新代理ID，不存在添加
         * */
        if($aid&&$did){
            $rs_agent = $this->_agentsModel->eq(array('did'=>$did,'id'=>$aid))->getOne();
            if($rs_agent){
            }else{
                $rs_agent_update = $this->_agentsModel->update(array('did'=>$did)," id=$aid");
            }
        }

        /**
         * 查询域名至代理(代理账号。。。)
         * 1.显示域名列表
         * 2.查询代理名称列表
         */
        $domainSql = "SELECT id,url as domain FROM `video_domain` where type=0";
        $data['redirect_list'] = $this->_domainModel->getAll($domainSql);

        $data['agents_list'] = $this->_agentsModel->fields(array('id','agentname'))->getAll();

        $agent_redirect_sql = "SELECT agentname,agentaccount,h.url as domain FROM  video_agents as a
                                    LEFT JOIN video_domain as h ON a.did=h.id";
        $agentsList = $this->_agentsModel->getAll($agent_redirect_sql);

        $data['agents_domain_list'] = $agentsList;

        include_once("./tpl/domain-domainagents.html");

    }

    /**
     * [queryAction 域名查询]
     * @param String did [域名ID]
     * @param String aid [代理ID]
     * @param String ge [查询开始时间]
     * @param String le [查询结束时间]
     * @author raby
     * date   2015/04/13
     */
    public function queryAction(){
        //判断权限
        if(!in_array(61,$this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        $did = $_GET['search']['did'];
        $aid = $_GET['search']['aid'];
        $created_ge = $_GET['search']['created']['ge'];
        $created_le = $_GET['search']['created']['le'];


        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size * ($page - 1);

        //显示域名列表
        $data['redirect_list'] = $this->_domainModel->fields(array('id','url'))->eq(array('type'=>0))->getAll();

        //查询代理名称列表
        $data['agents_list'] = $this->_agentsModel->fields(array('id','agentname'))->getAll();

        /**
         * 显示域名相关信息
         * aid,did为空，则查询所有
         */
        $whereEqData = array();
        if($did){
            $whereEqData['did'] = $did;
        }
        if($aid){
            $whereEqData['id'] = $aid;
        }
        $agentsList = $this->_agentsModel->fields(array('id','did','agentaccount','agentname'))->eq($whereEqData)->neq(array('did'=>0))->limit($offset,$page_size)->getAll();
        $rs_data = array();
        foreach($agentsList as $k=>$v){
            $did = $v['did'];
            $id = $v['id'];
            //分配域名
            $domain_agents = $this->_domainModel->eq(array('id'=>$did))->getOne();

            //该域名的注册人数
            $register_where = '';
            if($created_ge&&$created_le){
                $register_where .= "and ui.created>='$created_ge' and ui.created<='$created_le'";
            }

            $register_sql = "SELECT count(au.uid) as user_num FROM  video_agent_relationship as au
                    LEFT JOIN video_user as ui ON ui.uid=au.uid
                    WHERE au.aid=$id $register_where";
            $register = $this->_agentsModel->getOne($register_sql);

            //该条域名的充值钻石统计
            $recharge_where = '';
            if($created_ge){
                $recharge_where .= "and re.created>='$created_ge'";
            }
            if($created_le){
                $recharge_where .= "and re.created<='$created_le'";
            }
            $recharge_sql = "SELECT  SUM(re.points) as points_num FROM video_agent_relationship as au
                    LEFT JOIN video_recharge as re ON re.uid=au.uid
                     WHERE re.pay_status=1 and re.pay_type=1 and au.aid=$id $recharge_where
                    ";
            $recharge = $this->_agentsModel->getOne($recharge_sql);

            //该条域名的消费钻石统计
            $mall_where = '';
            if($created_ge){
                $mall_where .= "and ma.created>='$created_ge'";
            }
            if($created_le){
                $mall_where .= "and ma.created<='$created_le'";
            }
            $mall_sql = "SELECT  SUM(ma.points) as points_num FROM video_agent_relationship as au
                    LEFT JOIN video_mall_list as ma ON ma.send_uid=au.uid
                     WHERE au.aid=$id $mall_where
                    ";
            $mall = $this->_agentsModel->getOne($mall_sql);

            //有记录，添加
            $rs_data[$k]['agentname']=$v['agentname'];
            $rs_data[$k]['agentaccount']=$v['agentaccount'];
            $rs_data[$k]['click']=$domain_agents['click'];
            $rs_data[$k]['redirect']=$domain_agents['url'];
            $rs_data[$k]['user_num']=$register['user_num'];
            $rs_data[$k]['recharge_num']=$recharge['points_num'];
            $rs_data[$k]['mall_num']=$mall['points_num'];
        }

        $count = $this->_agentsModel->fields(array("count(id) as nums"))->eq($whereEqData)->getOne();
        $nums = $count['nums'];

        //显示跳转页面列表
        $data['pages'] = $this->global->pages($page_size, $nums, 5, $page);

        $data['agents_redirect_list']=$rs_data;
        include_once("./tpl/domain-query.html");
    }
} 
