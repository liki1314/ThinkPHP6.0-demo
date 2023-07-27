<?php
return [
    'tasks' => [
        \app\admin\task\DemoTask::class,
        \app\admin\task\Notice::class,
        \app\admin\task\RoomNotice::class,
        \app\admin\task\SyncLesson::class,
        \app\admin\task\SyncUser::class,
        \app\wssx\task\SyncCompany::class,
        \app\wssx\task\CancleOrder::class,

    ]
];
