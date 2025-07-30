<?php

namespace app\common\exception;

use think\Exception;
use Throwable;

class SystemErrorException extends Exception
{
    public function __construct($message = "系统内部错误", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}