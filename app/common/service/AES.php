<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-01-21
 * Time: 15:47
 */

namespace app\common\service;

class AES
{
    //设置AES秘钥
    private static $aes_key = 'bUYJ3nTV6VBasdJF'; //此处填写前后端共同约定的秘钥

    /**
     * 加密
     * @param string $str 要加密的数据
     * @param string $key
     * @return bool|string   加密后的数据
     */
    static public function encrypt($str, $key = "")
    {

        $data = openssl_encrypt($str, 'AES-128-ECB', sprintf("%s%d", self::$aes_key, $key), OPENSSL_RAW_DATA);
        $data = base64_encode($data);

        return $data;
    }

    /**
     * 解密
     * @param string $str 要解密的数据
     * @param string $key
     * @return string        解密后的数据
     */
    static public function decrypt($str, $key = "")
    {

        $decrypted = openssl_decrypt(base64_decode($str), 'AES-128-ECB', sprintf("%s%s", self::$aes_key, $key), OPENSSL_RAW_DATA);
        return $decrypted;
    }
}
