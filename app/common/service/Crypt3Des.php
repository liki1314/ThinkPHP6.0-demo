<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-08
 * Time: 10:33
 */

namespace app\common\service;


class Crypt3Des
{
    /**
     * 加密数据
     * @param $input
     * @param $encrptkey
     * @return string
     */
    public function encrypt($input, $encrptkey)
    {//数据加密
        $size = 8;
        // 对加密内容补齐
        $input = $this->pkcs5_pad($input, $size);
        if (strlen($input) % $size) {
            $input = str_pad($input,
                strlen($input) + $size - strlen($input) % $size, "\0");
        }

        $key = str_pad($encrptkey, 24, '0');

        $data = openssl_encrypt($input, "DES-EDE3",
            $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

        $data = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        return $data;
    }

    /**
     * 解密数据
     * @param $encrypted
     * @param $encrptkey
     * @return bool|string
     */
    public function decrypt($encrypted, $encrptkey)
    {//数据解密
        $encrypted = base64_decode(str_pad(strtr($encrypted, '-_', '+/'), strlen('') % 4, '=', STR_PAD_RIGHT));

        $key = str_pad($encrptkey, 24, '0');


        $decrypted = openssl_decrypt($encrypted, "DES-EDE3",
            $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

        $y = $this->pkcs5_unpad($decrypted);
        return $y;
    }

    /**
     * 补齐方式
     * @param $text
     * @param $blocksize
     * @return string
     */
    public function pkcs5_pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * 去除填充
     * @param $text
     * @return bool|string
     */
    public function pkcs5_unpad($text)
    {
        $pad = ord($text{strlen($text) - 1});
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }
}
