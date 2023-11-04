<?php

namespace HardeepVicky\Json;

class Util
{
    /**
     * remove the slashs in path
     * @param string $path
     * @param string $side FIRST, LAST
     * @return string
     */
    public static function removePathSlashs($path, $side = '')
    {
        $side = strtoupper($side);
        $path = trim(str_replace('\\', '/', $path));

        if ($side == 'FIRST' || $side == 'START' || empty($side)) {
            if (substr($path, 0, 1) == "/") {
                $path = substr($path, 1, strlen($path));
            }
        }

        if ($side == 'LAST' || $side == 'END' || empty($side)) {
            if (substr($path, -1) == "/") {
                $path = substr($path, 0, strrpos($path, "/"));
            }
        }
        return $path;
    }

    /**
     * Add slashs in path
     * @param string $path
     * @param string $side FIRST, LAST
     * @return string
     */
    public static function addPathSlashs($path, $side = '')
    {
        $side = strtoupper($side);
        $path = trim(str_replace('\\', '/', $path));

        if ($side == 'FIRST' || $side == 'START' || empty($side)) {
            if (substr($path, 0, 1) != "/") {
                $path = "/" . $path;
            }
        }

        if ($side == 'LAST' || $side == 'END' || empty($side)) {
            if (substr($path, -1) != "/") {
                $path .= "/";
            }
        }

        return $path;
    }

    /**
     * Following function convert any type of object to array
     * it can convert xml, json object to array
     * 
     * @param object $obj
     * @return array
     */
    public static function objToArray($obj)
    {
        $arr = array();
        if (gettype($obj) == "object") {
            $arr = self::objToArray(get_object_vars($obj));
        } else if (gettype($obj) == "array") {
            foreach ($obj as $k => $v) {
                $arr[$k] = self::objToArray($v);
            }
        } else {
            $arr = $obj;
        }

        return $arr;
    }
}
