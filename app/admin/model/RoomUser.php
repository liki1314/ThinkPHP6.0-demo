<?php

declare(strict_types=1);

namespace app\admin\model;

use think\model\Pivot;

/**
 * @mixin \think\Model
 */
class RoomUser extends Pivot
{
    /**
     * 批量报班
     *
     * @param array $courses 课程id数组
     * @param array $students 学生id数组
     * @return void
     */
    public static function attendClass(array $courses = [], array $students = [])
    {
        $insertData = [];

        Room::where('starttime', '>', time())
            ->whereIn('course_id', $courses)
            ->select()
            ->each(function ($item) use (&$insertData, $students) {
                foreach ($students as  $student) {
                    $insertData[] = [
                        'room_id' => $item->getKey(),
                        'front_user_id' => $student
                    ];
                }
            });

        self::extra('IGNORE')->insertAll($insertData);
    }


    /**
     * 根据课节ID批量报班
     * @param array $room_id 课节id数组
     * @param array $students 学生id数组
     */
    public static function attendLesson(array $room_id = [], array $students = [])
    {
        $insertData = [];
        Room::where('starttime', '>', time())
            ->whereIn('id', $room_id)
            ->select()
            ->each(function ($item) use (&$insertData, $students) {
                foreach ($students as $student) {
                    $insertData[] = [
                        'room_id' => $item->getKey(),
                        'front_user_id' => $student
                    ];
                }
            });

        if ($insertData) {
            self::extra('IGNORE')->insertAll($insertData);
        }
        return true;
    }
}
