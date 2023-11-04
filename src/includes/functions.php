<?php

function dump($arg, $will_exit = false)
{
    $callBy = debug_backtrace()[0];
    echo "<pre>";
    echo "<b>" . $callBy['file'] . "</b> At Line : " . $callBy['line'];
    echo "<br/>";
    
    if (is_string($arg))
    {
        echo htmlspecialchars($arg);
    }
    else
    {
        print_r($arg);
    }
    
    echo "</pre>";

    if ($will_exit)
    {
        exit;
    }
}

function throw_exception($msg)
{
    throw new Exception($msg);
}