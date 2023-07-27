<?php
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

function getRandStr($len = 6)
{
    return substr(md5(uniqid()), 0, $len);
}

function Sec2Time($time)
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
            $t = sprintf("%d年", $value["years"]);
        }
        if ($value["days"] > 0) {
            $t .= sprintf("%d天", $value["days"]);
        }
        if ($value["hours"] > 0) {
            $t .= sprintf("%d小时", $value["hours"]);
        }
        if ($value["minutes"] > 0) {
            $t .= sprintf("%d分钟", $value["minutes"]);
        }
        if ($value["seconds"] > 0) {
            $t .= sprintf("%d秒 ", $value["seconds"]);
        }
        Return $t;

    } else {
        return sprintf("0秒 ");
    }
}


