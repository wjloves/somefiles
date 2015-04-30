<?php
/**
 * Created by PhpStorm.
 * User: ws
 * Date: 14-10-23
 * Time: ����1:12
 */

class DBConf{
    public static function getEnd(){
        return array(
            'host'=>'127.0.0.1',
            'user'=>'root',

            'pwd'=>'',
            'db'=>'video_bos',
        );
    }

    public static function getFront(){
        return array(
'host'=>'127.0.0.1',
            'user'=>'root',

            'pwd'=>'',
            'db'=>'video',
        );
    }

    // public static function getEnd(){
    //     return array(
    //         'host'=>'10.146.180.30:3306',
    //         'user'=>'testuser',
    //         'pwd'=>'root1234',
    //         'db'=>'video_bos',
    //     );
    // }

    // public static function getFront(){
    //     return array(
    //         'host'=>'10.146.180.30:3306',
    //         'user'=>'testuser',
    //         'pwd'=>'root1234',
    //         'db'=>'video',
    //     );
    // }
}
