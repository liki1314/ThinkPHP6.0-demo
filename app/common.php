<?php
if (!function_exists('timetostr')) {
    function timetostr($time)
    {
        if (is_numeric($time)) {
            $value = array(
                "years" => 0, "days" => 0, "hours" => 0, "minutes" => 0, "seconds" => 0,
            );
            if ($time >= 31556926) {
                $value["years"] = floor($time / 31556926);
                $time = ($time % 31556926);
            }
            if ($time >= 86400) {
                $value["days"] = floor($time / 86400);
                $time = ($time % 86400);
            }
            if ($time >= 3600) {
                $value["hours"] = floor($time / 3600);
                $time = ($time % 3600);
            }
            if ($time >= 60) {
                $value["minutes"] = floor($time / 60);
                $time = ($time % 60);
            }
            $value["seconds"] = floor($time);
            $t = '';
            if ($value["years"] > 0) {
                $t = sprintf("%d" . lang('years'), $value["years"]);
            }
            if ($value["days"] > 0) {
                $t .= sprintf("%d" . lang('day'), $value["days"]);
            }
            if ($value["hours"] > 0) {
                $t .= sprintf("%d" . lang('hours'), $value["hours"]);
            }
            if ($value["minutes"] > 0) {
                $t .= sprintf("%d" . lang('minute'), $value["minutes"]);
            }
            if ($value["seconds"] >= 0) {
                $t .= sprintf("%d" . lang('seconds'), $value["seconds"]);
            }
            return $t;
        } else {
            return sprintf("0" . lang('seconds'));
        }
    }
}


if (!function_exists('get_rand_str')) {
    function get_rand_str($len = 6)
    {
        return substr(md5(uniqid()), 0, $len);
    }
}
if (!function_exists('fix_number_precision')) {
    function fix_number_precision($data, $precision = 2)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = fix_number_precision($value, $precision);
            }
            return $data;
        }

        if (is_numeric($data)) {
            $precision = is_float($data) ? $precision : 0;
            return number_format($data, $precision, '.', '');
        }
        return $data;
    }
}

/**
 * 字节格式化
 *
 * @param int $bytes 字节
 * @param integer $decimals
 * @return string
 */
function human_filesize($bytes, $decimals = 2)
{
    if (empty($bytes)) return '0KB';
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

/**
 * 时间格式化
 *
 * @param int $secs 秒
 * @return string
 */
function time_elapsed($secs)
{
    if ($secs == 0) return '0秒';

    $bit = array(
        /* '年' => $secs / 31556926 % 12,
        '周' => $secs / 604800 % 52,
        '天' => $secs / 86400 % 7,
        '小时' => $secs / 3600 % 24, */
        '小时' => intdiv($secs, 3600),
        '分钟' => $secs / 60 % 60,
        '秒' => $secs % 60
    );

    $ret = [];
    foreach ($bit as $k => $v) {
        if ($v > 0) {
            $ret[] = $v . $k;
        }
    }

    return join('', $ret);
}





if (!function_exists('history_time')) {
    function history_time($time)
    {
        $sec = time() - $time;
        $year = intval($sec / 3600 / 24 / 365);
        if ($year > 0) {
            return sprintf("%d年之前", $year);
        } else {
            $month = intval($sec / 3600 / 24 / 30);
            if ($month > 0) {
                return sprintf("%d个月之前", $month);
            } else {
                $day = intval($sec / 3600 / 24);
                if ($day > 0) {
                    return sprintf("%d日之前", $day);
                } else {
                    $hour = intval($sec / 3600);
                    if ($hour > 0) {
                        return sprintf("%d小时之前", $hour);
                    } else {
                        $minute = intval($sec / 60);
                        if ($minute > 0) {
                            return sprintf("%d分钟之前", $minute);
                        } else {
                            return sprintf("%d秒之前", $sec);
                        }
                    }
                }
            }
        }
    }
}




if (!function_exists('get_rand_code')) {
    function get_rand_code($len = 6)
    {
        $str = '';
        $strPol = "ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz";//大小写字母以及数字
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $len; $i++) {
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }
}

if (!function_exists('get_rand_num')) {
    function get_rand_num($len = 6)
    {
        $str = '';
        $strPol = "123456789";//大小写字母以及数字
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $len; $i++) {
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }
}