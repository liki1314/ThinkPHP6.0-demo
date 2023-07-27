<?php

namespace app\common\service;

class Filter
{
    public static function trim($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }
}
