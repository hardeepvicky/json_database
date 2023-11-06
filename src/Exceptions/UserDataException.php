<?php

namespace HardeepVicky\Json;

use Exception;

class UserDataException extends Exception
{
    public Array $error_list = [];
    public function __construct(String $msg, Array $error_list)
    {
        parent::__construct($msg, 500);

        $this->error_list = $error_list;
    }
}