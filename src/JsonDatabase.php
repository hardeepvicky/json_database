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
        $this->required_attributes = $arr;
    }

    public function getRequiredAttr()
    {
        return $this->required_attributes;
    }

    public function setUniqueAttr(Array $arr)
    {
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

    protected function onPut(array $json_array, String $json_str)
    {
        if (!self::$info_json_database)
        {
            self::$info_json_database = new JsonDatabase($this->path, self::INFO_FILE);
        }

        $json_obj = self::$info_json_database->get();

        $json_obj[$this->file_name]['array_count'] = count($json_array);
        $json_obj[$this->file_name]['string_length'] = strlen($json_str);
        $json_obj[$this->file_name]['filesize'] = filesize($this->getFile());

        $json = self::jsonEncode($json_obj);

        $info_file = self::$info_json_database->getFile();
        $result = file_put_contents($info_file, $json);

        if ($result === false) {
            self::throwException("Fail To put json in $info_file");
        }
    }

    private function put(array $json_array)
    {
        $file = $this->getFile();

        $json_str = self::jsonEncode($json_array);

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To put json in $file");
        }

        $this->onPut($json_array, $json_str);
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
        //check for unique_records
        if ($this->unique_attributes) 
        {
            $error_list = [];

            if (is_null($index))
            {
                foreach($this->unique_attributes as $unique_key)
                {
                    if (isset($save_record[$unique_key]))
                    {
                        $is_found = false;

                        //insert
                        foreach($records as $k => $record)
                        {
                            if (isset($record[$unique_key]))
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
                    else
                    {
                        $error_list[] = "$unique_key should be set in save_record";
                    }
                }
            }
            else
            {
                //update
                foreach($this->unique_attributes as $unique_key)
                {
                    if (isset($save_record[$unique_key]))
                    {
                        $is_found = false;

                        //insert
                        foreach($records as $k => $record)
                        {
                            if (isset($record[$unique_key]))
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
                    else
                    {
                        $error_list[] = "$unique_key should be set in save_record";
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
        $error_list = [];
        foreach($this->required_attributes as $key)
        {
            if (!isset($save_record[$key]))
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

        $records = $this->get();
        
        $this->_checkBeforeSave($records, $save_record);

        $records[] = $save_record;

        $this->put($records);
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

        $this->put($records);
    }

    public function delete(int $index)
    {
        $records = $this->get();

        unset($records[$index]);

        $this->put(array_values($records));
    }
}
