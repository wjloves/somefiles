<?php
/**
 * Created by PhpStorm.
 * User: kid
 * Date: 14-10-23
 * Time: 下午12:45
 */

header("content-type:text/html;charset=utf-8");
class PercentageController extends BaseController{
    public function __construct(){
        parent::__construct();
        $this->_bonusruleModel = FrontBonusRuleModel::getInstance();
       
    }

    public function defaultAction(){
        //判断权限
        if(!in_array(21,$this->_priv_arr)){
                echo "<script>alert('没有权限');location.href='./index.php?do=index-index';</script>";
                return;   
        }
        $list = $this->_bonusruleModel->order(' host_level asc')->getAll();
        include_once("./tpl/percentage-list.html");
    }

    /**
     * [ajaxEditAction AJAX修改等级比例]
     * @param Int $auid                   [业务ID]
     * @param Int $contion['host_shares'] [修改的比例]
     * @author morgan
     * date   2014/11/07
     */
    public function ajaxEditAction(){
    	$auid = intval($_GET['id']);
    	$contion['host_shares'] = addslashes($_GET['shares']);
    	$status = $this->_bonusruleModel->update($contion,' auid = '.$auid);
    	echo  $status;
    }

    /**
     * [ajaxAddAction AJAX新增等级比例]
     * @param Int $contion['host_level']  [新增等级]
     * @param Int $contion['host_shares'] [新增比例]
     * @author morgan
     * date   2014/12/11
     */
    public function ajaxAddAction(){
        $contion['host_level']  =  intval($_POST['level']);
        $contion['host_shares'] = intval($_POST['shares']);
        $is_had = $this->_bonusruleModel->eq(array('host_level'=>$contion['host_level']))->getOne();
        if($is_had){
           echo json_encode("已有此等级");return;
        }
        $status = $this->_bonusruleModel->insert($contion);
        if($status){
           echo json_encode("添加成功");
        }else{
           echo json_encode("添加失败");
        }
    }
} 