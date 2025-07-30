<?php

namespace app\model;
//                            _ooOoo_
//                           o8888888o
//                           88" . "88
//                           (| -_- |)
//                            O\ = /O
//                        ____/`---'\____
//                      .   ' \\| |// `.
//                       / \\||| : |||// \
//                     / _||||| -:- |||||- \
//                       | | \\\ - /// | |
//                     | \_| ''\---/'' | |
//                      \ .-\__ `-` ___/-. /
//                   ___`. .' /--.--\ `. . __
//                ."" '< `.___\_<|>_/___.' >'"".
//               | | : `- \`.;`\ _ /`;.`/ - ` : | |
//                 \ \ `-. \_ __\ /__ _/ .-` / /
//         ======`-.____`-.___\_____/___.-`____.-'======
//                            `=---='
//
//         .............................................
//                  佛祖镇楼                  BUG退散
class User extends BaseModel
{
    /**
     * 构造密码
     * @param $clear
     * @return string
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function translatePassword($clear)
    {
        return md5($clear . '%$&%^O(*)#$@%$%#$#$$%&^%*');
    }

    /**
     * 判断是否超级管理员
     * @param $uid
     * @return bool
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function isSuperAdmin($uid = 0)
    {
        if (empty($uid)) {
            $uid = is_login();
        }
        return self::where('id', $uid)->value('is_admin') ? true : false;
    }
}