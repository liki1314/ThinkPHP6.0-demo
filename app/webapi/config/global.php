<?php
return [
    //ip白名单
    'ip_list' => env('global.ip_list', []),
    //md5验证秘钥
    'secret_key' => env('global.secret_key', ''),
];
