<?php
namespace HardeepVicky\Json;

use Exception;

class JsonDatabase
{
    const INFO_FILE = "info";

    private static $info_json_database;
    private static bool $show_error_as_html = true;

    private String $path;
    private String $file_name;

    private Array $required_attributes = [];

    private Array $unique_attributes = [];

    public static function showErrorAsHtml(bool $v)
    {
        self::$show_error_as_html = $v;
    }

    public static function throwException($msg)
    {
        $callBy = debug_backtrace()[1];

        throw new \RuntimeException($callBy['function'] . "() : " . $msg);
    }

    public static function showErrors($title, $error_list)
    {
        $html = "<p class='json-database-errors'><span>$title</span>";
            $html .= '<ul>';
                foreach($error_list as $error_str)
                {
                    $html .= "<li>" . $error_str . "</li>";
                }
            $html .= '</ul>';
        $html .= '</p>';

        echo $html;

        exit;
    }

    public static function jsonEncode($obj)
    {
        $json = json_encode($obj, JSON_INVALID_UTF8_IGNORE);

        switch (json_last_error()) 
        {            
            case JSON_ERROR_NONE:
                //no error
                break;

            default:
                self::throwException(json_last_error_msg());
                break;
        }

        return $json;
    }

    public static function jsonDecode($string)
    {
        $json_obj = json_decode($string, true);

        switch (json_last_error()) 
        {            
            case JSON_ERROR_NONE:
                //no error
                break;
                
            default:
                self::throwException(json_last_error_msg());
                break;
        }

        return $json_obj;
    }


    public function __construct(String $path, String $file_name)
    {
        $this->path = strtolower(trim($path)); 

        if (!$this->path)
        {
            self::throwException("path is empty");
        }

        $this->path = Util::addPathSlashs($this->path, 'END');

        if (!file_exists($this->path))
        {
            if (!mkdir($this->path, 0777, TRUE)) {
                self::throwException("Fail to create $this->path");
            }
        }

        if (!is_dir($this->path))
        {
            self::throwException("$this->path should be directory");
        }
        
        $this->file_name = strtolower(trim($file_name));
        
        if (!$this->file_name)
        {
            self::throwException("file_name is empty");
        }
    }
    
    public function getInfoFile()
    {
        return self::INFO_FILE;
    }

    public function getFileName()
    {
        return $this->file_name;
    }

    public function getFile()
    {
        return $this->path . $this->file_name . ".json";
    }

    public function setRequiredAttr(Array $arr)
    {
        foreach($arr as $v)
        {
            if (!is_string($v))
            {
                self::throwException("value of array should be string");
            }

            if (strlen($v) == 0)
            {
                self::throwException("string length of value is 0");
            }
        }

        $this->required_attributes = $arr;
    }

    public function getRequiredAttr()
    {
        return $this->required_attributes;
    }

    public function setUniqueAttr(Array $arr)
    {
        foreach($arr as $v)
        {
            if (!is_string($v))
            {
                self::throwException("value of array should be string");
            }

            if (strlen($v) == 0)
            {
                self::throwException("string length of value is 0");
            }

        }

        $this->unique_attributes = $arr;
    }

    public function getUniqueAttr()
    {
        return $this->unique_attributes;
    }

    public static function getInfo(String $path)
    {
        $path = strtolower(trim($path)); 

        if (!$path)
        {
            self::throwException("path is empty");
        }

        $path = Util::addPathSlashs($path, 'END');

        if (!file_exists($path))
        {
            self::throwException("path $path not exist");
        }

        if (!self::$info_json_database)
        {
            self::$info_json_database = new JsonDatabase($path, self::INFO_FILE);
        }

        return self::$info_json_database->get();
    }

    protected function onWrite(array $json_array, String $json_str)
    {
        if (!self::$info_json_database)
        {
            self::$info_json_database = new JsonDatabase($this->path, self::INFO_FILE);
        }

        $json_obj = self::$info_json_database->get();

        $file_size_bytes = filesize($this->getFile());
        $file_size_kb = floor($file_size_bytes / 1024);
        $file_size_mb = floor($file_size_kb / 1024);

        $json_obj[$this->file_name] = [];
        $json_obj[$this->file_name]['json_array_count'] = count($json_array);
        $json_obj[$this->file_name]['json_string_length'] = strlen($json_str);
        $json_obj[$this->file_name]['file_info']['size']['bytes'] = $file_size_bytes;        
        $json_obj[$this->file_name]['file_info']['size']['kb'] = $file_size_kb;
        $json_obj[$this->file_name]['file_info']['size']['mb'] = $file_size_mb;

        $json = self::jsonEncode($json_obj);

        $info_file = self::$info_json_database->getFile();
        $result = file_put_contents($info_file, $json);

        if ($result === false) {
            self::throwException("Fail To put json in $info_file");
        }
    }

    private function write(array $json_array)
    {
        $file = $this->getFile();

        $json_str = self::jsonEncode($json_array);

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To write json in $file");
        }

        $this->onWrite($json_array, $json_str);
    }

    public function get()
    {
        $file = $this->getFile();

        if (file_exists($file))
        {
            $str = file_get_contents($file, FILE_USE_INCLUDE_PATH);

            return self::jsonDecode($str);
        }

        return [];
    }

    private function _checkBeforeSave(Array $records, Array $save_record, $index = null)
    {
        $error_list = [];

        if ($this->required_attributes)
        {
            foreach($this->required_attributes as $key)
            {
                if ( !array_key_exists($key, $save_record) )
                {
                    $error_list[] = "$key should be set in save_record";
                }
            }

            if (count($error_list) > 0)
            {
                if (self::$show_error_as_html)
                {
                    self::showErrors("Required Errors", $error_list);
                }
                else 
                {
                    throw new UserDataException("Fail To Save", $error_list);
                }
            }
        }

        //check for unique_records
        if ($this->unique_attributes) 
        {
            $error_list = [];

            foreach($this->unique_attributes as $unique_key)
            {
                if ( !array_key_exists($unique_key, $save_record) )
                {
                    $error_list[] = "$unique_key should be set in save_record";
                }
                else 
                {
                    $value_type = gettype($save_record[$unique_key]);

                    $allow_types_for_unique_values = [
                        "string", "int", "float", "NULL"
                    ];

                    if (!in_array($value_type, $allow_types_for_unique_values))
                    {
                        $str = implode(" or ", $allow_types_for_unique_values);
                        $error_list[] = "value of $unique_key should be ($str) in save_record";
                    }
                }
            }

            if (empty($error_list))
            {
                if (is_null($index))
                {
                    //insert

                    foreach($this->unique_attributes as $unique_key)
                    {
                        $is_found = false;
                        
                        foreach($records as $k => $record)
                        {
                            if (isset($record[$unique_key]) && !is_null($record[$unique_key]))
                            {
                                if ($record[$unique_key] == $save_record[$unique_key])
                                {
                                    $is_found = true;
                                }
                            }
                        }

                        if ($is_found)
                        {
                            $error_list[] = "duplicate $unique_key : " . $save_record[$unique_key];
                        }                        
                       
                    }
                }
                else
                {
                    //update
                    foreach($this->unique_attributes as $unique_key)
                    {
                        $is_found = false;

                        foreach($records as $k => $record)
                        {
                            if (isset($record[$unique_key]) && !is_null($record[$unique_key]))
                            {
                                if ($k != $index && $record[$unique_key] == $save_record[$unique_key])
                                {
                                    $is_found = true;
                                }
                            }
                        }

                        if ($is_found)
                        {
                            $error_list[] = "duplicate $unique_key : " . $save_record[$unique_key];
                        }                        
                    }
                }
            }

            if (count($error_list) > 0)
            {
                if (self::$show_error_as_html)
                {
                    self::showErrors("Unique Errors", $error_list);
                }
                else 
                {
                    throw new UserDataException("Fail To Save", $error_list);
                }
            }
        }
    }

    public function insert(Array $save_record)
    {
        $records = $this->get();
        
        $this->_checkBeforeSave($records, $save_record);

        $records[] = $save_record;

        $this->write($records);
    }

    public function update(Array $save_record, int $index)
    {
        $records = $this->get();

        if (!isset($records[$index]))
        {
            self::throwException("index $index not found in records");
        }

        $this->_checkBeforeSave($records, $save_record, $index);

        $records[$index] = array_merge($records[$index], $save_record);

        $this->write($records);
    }

    public function delete(int $index)
    {
        $records = $this->get();

        if (isset($records[$index]))
        {
            unset($records[$index]);

            $this->write(array_values($records));

            return true;
        }

        return false;
    }
}
