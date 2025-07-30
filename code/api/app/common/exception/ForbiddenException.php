<?php

namespace app\common\exception;

use think\Exception;
use Throwable;

class ForbiddenException extends Exception
{
    public function __construct($message = "无权访问", $code = 403, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}