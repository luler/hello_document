<?php

namespace app\job;

use app\common\logic\FileLogic;
use think\queue\Job;

class TransformJob
{
    public function fire(Job $job, $data)
    {
        if ($job->attempts() >= 2) {
            $job->delete();
            return;
        }

        FileLogic::startWork();

        $job->delete();
    }

    public function failed($data)
    {
        // ...任务达到最大重试次数后，失败了
    }
}