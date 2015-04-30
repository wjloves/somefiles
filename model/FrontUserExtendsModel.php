<?php
/**
 * Created by PhpStorm.
 * User: morgan
 * Date: 15-4-10
 * Time: 下午12:51
 */

class FrontUserExtendsModel extends FrontDBModel{
    protected static $_instance = null;
    protected $_tbl = 'video_user_extends';


    /**
     * @param null $bankname [开户名称]
     * @param $banknumber   [银行帐号]
     * @return array|bool
     * @author D.C
     * @update 2015-04-15
     * @description 根据银行卡信息进行用户信息反查
     */
    public function getUserbybankInfo($bankname = null, $banknumber = null){
        if (!$bankname && !$banknumber ) return false;
        $sql = "SELECT e.*, u.username, u.nickname FROM video_user_extends e LEFT  JOIN video_user u ON e.uid = u.uid WHERE 1";
        if(!empty($banknumber)){
            $sql.= " AND  e.banknumber LIKE '%$banknumber%'";
        }
        if(!empty($bankname)){
            $sql.= " AND  e.bankname LIKE '%$bankname%'";
        }
        $user = $this->getOne($sql);
        return $user ?: false;
    }
}