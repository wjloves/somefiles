<?php
/**
 * Created by PhpStorm.
 * User: cannyco
 * Date: 14-10-23
 * Time: 下午12:45
 * 主播
 */

header("content-type:text/html;charset=utf-8");
class PayBonusController extends BaseController{
    public function __construct(){
        parent::__construct();
        $this->_videoPayBonusModel      = EndPayBonusModel::getInstance();
        $this->_videoUserModel = FrontUserModel::getInstance();
        $this->_videoEndAdminModel = EndAdminModel::getInstance();
        $this->_userModel = FrontUserModel::getInstance();
    }

    public function defaultAction()
    {
        //echo "<script>location.href='./index.php?do=index-index';</script>";
        //return;
    }

    /**
     * [mainAction 工资和奖金申请(运营1类看)]
     * @author cannyco
     * 2015/04/17
     */
    public function mainAction(){
        //判断权限
        if(!in_array(68, $this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        //读取下当前用户申请的工资及资金记录
        $this->seachOrder();
        Search::getCondition($this->_videoPayBonusModel);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size*($page-1);
        $replyList = $this->_videoPayBonusModel
                          ->eq(array("replyid" => $this->_login['admin_id']))
                          ->order('ctime desc')
                          ->limit($offset, $page_size)
                          ->getAll();
        //获取下主播的信息
        $checkUserList = array();
        if (is_array($replyList) && $replyList) {
            foreach($replyList as $key => $val) {
                //查询用户的一些信息
                $userInfo = $this->_videoUserModel->eq(array("uid" => $val['uid']))->getOne();
                $replyList[$key]['nickname'] = $userInfo['nickname'];
                $replyList[$key]['username'] = $userInfo['username'];
                $replyList[$key]['family'] = '-';

                //如果审核通过，查询下审核员的用户名
                if(!array_key_exists($val['checkid'], $checkUserList)) {
                    if ($val['checkid']) {
                        $checkUserinfo = $this->_videoEndAdminModel->eq(array("admin_id" => $val['checkid']))->getOne();
                        $replyList[$key]['check'] = $checkUserinfo['name'];
                        $checkUserList[$val['checkid']] = $checkUserinfo['name'];
                    } else {
                        $replyList[$key]['check'] = '-';
                    }
                } else {
                    $replyList[$key]['check'] = $checkUserList[$val['checkid']];
                }
                unset($replyList[$key]['checkid']);
            }
        }

        $data['list'] = $replyList;

        include_once("./tpl/paybonus-main.html");
    }

    /**
     * [mainAction 添加申请记录]
     * @author morgan
     * 2015/04/10
     */
    public function DealAction()
    {
        //获取下当前是哪类主播
        $account = isset($_POST['account'])?$_POST['account']:'';
        $reuid = isset($_POST['uid'])?$_POST['uid']:0;
        $message = isset($_POST['message'])?$_POST['message']:'';
        $amount = isset($_POST['amount'])?$_POST['amount']:0;
        $paytype = isset($_POST['paytype'])?$_POST['paytype']:1;
        if (!$amount || !is_numeric($amount)) {
            echo json_encode(array('code' => 1, 'message' => '充值金额只能为数字！'));exit;
        }

        if (!$reuid) {
            echo json_encode(array('code' => 5, 'message' => '主播ID不能为空！'));exit;
        }
        //判断下uid是否存在
        if ($reuid) {
            if (!is_numeric($reuid) || !$reuid) {
                echo json_encode(array('code' => 5, 'message' => '主播id只能是数字!'));exit;
            }
           //查询下这个用户id是否存在
            $reUser = $this->_videoUserModel->eq(array("uid" => $reuid))->getOne();
            if (!$reUser) {
                echo json_encode(array('code' => 3, 'message' => '主播用户不存在！'));exit;
            }
        }

        //获取下这类主播的配置信息
        $insertArr = array(
            'ctime' => date('Y-m-d H:i:s'),
            'uid' => $reuid,
            'pay_money' => $amount,
            'pay_type' => $paytype,
            'note' => $message,
            'replyid' => $this->_login['admin_id'],
            'pay_status' => 0
        );
        $insertResult = $this->_videoPayBonusModel->insert($insertArr);
        //var_dump($curWithdrawalResult);exit;
        if (!$insertResult) {
            echo json_encode(array('code' => 4, 'message' => '提交申请失败！'));exit;
        }
        echo json_encode(array('code' => 0, 'message' => 'success'));exit;
    }

    /**
     * [mainAction 工资和奖金申请(运营2类看)]
     * @author cannyco
     * 2015/04/17
     */
    public function CheckAction(){
        //判断权限
        if(!in_array(69, $this->_priv_arr)){
            echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
            return;
        }
        //读取下当前用户申请的工资及资金记录
        $this->seachOrder();
        Search::getCondition($this->_videoPayBonusModel);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page_size = 20;
        $offset = $page_size*($page-1);
        $replyList = $this->_videoPayBonusModel
                          ->order('ctime desc')
                          ->limit($offset, $page_size)
                          ->getAll();
        //获取下主播的信息
        $replyUserList = array();
        $checkUserList = array();
        if (is_array($replyList) && $replyList) {
            foreach($replyList as $key => $val) {
                //查询用户的一些信息
                $userInfo = $this->_videoUserModel->eq(array("uid" => $val['uid']))->getOne();
                $replyList[$key]['nickname'] = $userInfo['nickname'];
                $replyList[$key]['username'] = $userInfo['username'];
                $replyList[$key]['family'] = '-';
                //查询下申请员的用户名
                if(!array_key_exists($val['replyid'], $replyUserList)) {
                    $replyUserinfo = $this->_videoEndAdminModel->eq(array("admin_id" => $val['replyid']))->getOne();
                    $replyList[$key]['reply'] = $replyUserinfo['name'];
                    $replyUserList[$val['replyid']] = $replyUserinfo['name'];
                } else {
                    $replyList[$key]['reply'] = $replyUserList[$val['replyid']];
                }
                unset($replyList[$key]['replyid']);
                //如果审核通过，查询下审核员的用户名
                if(!array_key_exists($val['checkid'], $checkUserList)) {
                    if ($val['checkid']) {
                        $checkUserinfo = $this->_videoEndAdminModel->eq(array("admin_id" => $val['checkid']))->getOne();
                        $replyList[$key]['check'] = $checkUserinfo['name'];
                        $checkUserList[$val['checkid']] = $checkUserinfo['name'];
                    } else {
                        $replyList[$key]['check'] = '-';
                    }
                } else {
                    $replyList[$key]['check'] = $checkUserList[$val['checkid']];
                }
                unset($replyList[$key]['checkid']);
            }
        }

        $search = $_GET;
        $data['list'] = $replyList;

        include_once("./tpl/paybonus-check.html");
    }

    /**
     * [mainAction 添加申请记录]
     * @author morgan
     * 2015/04/10
     */
    public function CheckedAction()
    {
        //获取下当前是哪类主播
        $auid = $_POST['auid'];
        $checkCode = $_POST['result'];
        if($checkCode != 1 && $checkCode != 2) {
            echo json_encode(array('code' => 1, 'message' => '提交结果有误！'));exit;
        }
        //获取下这类主播的配置信息
        $updateArr = array(
            'pay_status' => $checkCode,
            'mtime' => date('Y-m-d H:i:s'),
            'checkid' => $this->_login['admin_id']
        );
        $where = " auid = " . $auid;
        $insertResult = $this->_videoPayBonusModel->update($updateArr, $where);
        //var_dump($insertResult);exit;
        if (!$insertResult) {
            echo json_encode(array('code' => 1, 'message' => '确认申请失败！'));exit;
        }
        echo json_encode(array('code' => 0, 'message' => 'success'));exit;
    }

    /**
     * [seachOrder 搜索条件]
     * @author cannyco
     * date 2015/04/06
     */
    public function  seachOrder(){
        if(isset($_GET['search']) && !empty($_GET['search'])) {
            //处理下判断条件
            $replyName = $_GET['search']['reply'];
            if ($replyName) {
                //查询下用户id
                $sql = 'select admin_id from vbos_admin where `name` like "%' . $replyName . '%" limit 20';
                $uidlist = $this->_videoEndAdminModel->getAll($sql);
                if($uidlist) {
                    array_walk($uidlist, function(&$v, $k){$v = $v['admin_id'];});
                    $this->_videoPayBonusModel->in(array("replyid" => $uidlist));
                }
            }

            $checkName = $_GET['search']['check'];
            if ($checkName) {
                //查询下用户id
                $sql = 'select admin_id from vbos_admin where `name` like "%' . $checkName . '%" limit 20';
                $uidlist = $this->_videoEndAdminModel->getAll($sql);
                if($uidlist) {
                    array_walk($uidlist, function(&$v, $k){$v = $v['admin_id'];});
                    $this->_videoPayBonusModel->in(array("checkid" => $uidlist));
                }
            }

            $searchstatus = $_GET['search']['pay_status'];
            if ($searchstatus == "0" || $searchstatus) {
                $this->_videoPayBonusModel->eq(array("pay_status" => $searchstatus));
            }

            //订单生成时间
            $searchctime = $_GET['search']['ctime'];
            if ($searchctime['ge']) {
                $this->_videoPayBonusModel->ge(array("ctime" => $searchctime['ge']));
            }
            if ($searchctime['lt']) {
                $this->_videoPayBonusModel->le(array("ctime" => $searchctime['le']));
            }
        }
    }
}