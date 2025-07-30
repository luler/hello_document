<?php

namespace app\common\exception;

use think\Exception;
use Throwable;

class CommonException extends Exception
{
    public function __construct($message = "", $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}