<?php
// 这是系统自动生成的公共文件
/**
 * 在app\webapi\model\VideoLog
 * 获取对应时间区间
 * @param $dr
 * @return array|bool
 */
function getDateBetween($dr)
{
    $startTime = 0;
    $endTime = 0;
    switch ($dr) {
        case 'today':
            //今天
            $startTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("d"), date("Y"));
            break;
        case 'yesterday':
            //昨天
            $startTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("d") - 1, date("Y"));
            break;
        case 'this_week':
            //本周
            $startTime = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"));
            break;
        case 'last_week':
            //上周
            $startTime = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1 - 7, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7 - 7, date("Y"));
            break;
        case '7days':
            //最近7天
            $startTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("d"), date("Y"));
            break;
        case 'this_month':
            //本月
            $startTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), date("t"), date("Y"));
            break;
        case 'last_month':
            //上个月
            $startTime = mktime(0, 0, 0, date("m") - 1, 1, date("Y"));
            $endTime = mktime(23, 59, 59, date("m"), 0, date("Y"));
            break;
        case 'this_year':
            //今年
            $startTime = mktime(0, 0, 0, 1, 1, date("Y"));
            $endTime = mktime(23, 59, 59, 1, 0, date("Y") + 1);
            break;
        case 'last_year':
            //去年
            $startTime = mktime(0, 0, 0, 1, 1, date("Y") - 1);
            $endTime = mktime(23, 59, 59, 1, 0, date("Y"));
            break;
    }
    return $startTime == 0 ? [] : [$startTime, $endTime];
}



function byteToSize($value)
{
    if ($value >= (1024 * 1024 * 1024)) {
        $value = number_format($value / 1073741824, 2) . ' GB';
    } elseif ($value >= (1024 * 1024)) {
        $value = number_format($value / 1048576, 2) . ' MB';
    } elseif ($value >= 1024) {
        $value = number_format($value / 1024, 2) . ' KB';
    } else {
        $value = $value . ' byte';
    }
    return $value;
}
