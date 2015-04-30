<?php

class BankController extends BaseController{


    public function __construct(){
        parent::__construct();
    }


    /**
     * @return mixed
     * @author D.C
     * @update 2015-04-15
     * @description 用户反查功能控制器
     */
    public function userAction(){
        $user = null;
        if($_POST['banknumber'] || $_POST['bankname']){
            $user = FrontUserExtendsModel::getInstance()->getUserbybankInfo($_POST['bankname'], $_POST['banknumber']);
            if(!$user){
                $error = '对不起！没有匹配记录';
            }
        }
        return require_once("./tpl/bank-user.html");
    }

}