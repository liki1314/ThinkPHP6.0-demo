<?php

namespace app\common\service;

class Math
{
    public $scale;  //保留的小数位

    public function __construct($scale = 2)
    {
        $this->scale  = $scale;
    }


    /**
     * 将任意精度的数值 转换成 字符串,并保留指定小数位
     * @param $num
     * @return string
     */
    public function toNumber($num)
    {
        return bcadd($num,0,$this->scale);
    }


    /**
     * 除法
     * @param $num1
     * @param $num2
     * @return string|null
     */
    public function div($num1,$num2)
    {
        if(!$num1 || !$num2) return $num1;
        return bcdiv($num1,$num2,$this->scale);
    }

    /**
     * 乘法
     * @param $list
     */
    public function mul($list)
    {
        $total = 1;
        foreach ($list as $num) {
            $total = bcmul($total, $num,$this->scale);
        }
        return $total;
    }
}