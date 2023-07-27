<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-02
 * Time: 10:45
 */

namespace app\common\http;


class ServerArea extends WebApi
{

    public function getList()
    {
        $res = self::httpGet("/WebAPI/serverAreaList");

        return $res['data'];
    }

    public function change($serial, $userid, $serverareaname)
    {
        self::httpPost("/WebAPI/serverAreaChange", [
            'serial' => $serial,
            'userid' => $userid,
            'serverareaname' => $serverareaname
        ]);
    }
}
