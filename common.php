<?php
/**
 * Created by PhpStorm.
 * User: kid
 * Date: 14-10-22
 * Time: 下午6:54
 */

function __autoload($file){
    $folder  = array('controller','class','model','conf');
    $num = count($folder);
    $flag = true;
    for($i=0;$i<$num;$i++){
        if(file_exists($folder[$i].'/'.$file.'.php')){
            include_once($folder[$i].'/'.$file.'.php');
            return ;
        }else{
        	$flag = false;
        }
    }
    if(!$flag){
    	echo "<script>location.href='./index.php?do=login';</script>";
        exit;
    }
}