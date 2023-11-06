<?php
namespace HardeepVicky\Json;

use Exception;

class JsonDatabase
{
    const INFO_FILE = "info";

    const ALLOW_VALUES_IN_JSON_RECORD = [
        'boolean', 'integer', 'double', 'string', 'NULL'
    ];

    const DEFAULT_CONFIG = [
        'attributes' => [
            'created' => false,
            'updated' => false,
        ]
    ];

    const DEFAULT_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private static $info_json_database, $composer_json;
    private static bool $show_error_as_html = true;

    private String $path;
    private String $file_name;
    private Array $config;

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

    public static function getComposerJson()
    {
        $path = dirname(__FILE__);

        $file = $path . "/../composer.json";

        if (!file_exists($file))
        {
            self::throwException("$file not found");
        }

        $json_str = file_get_contents($file, FILE_USE_INCLUDE_PATH);

        self::$composer_json = self::jsonDecode($json_str);
    }


    public function __construct(String $path, String $file_name, Array $config = [])
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

        $this->config = array_merge_recursive(self::DEFAULT_CONFIG, $config);
    }

    public static function isValidStringArrayList(Array $list)
    {
        foreach($list as $v)
        {
            if (!is_string($v))
            {
                return "value of array should be string";
            }

            if (strlen($v) == 0)
            {
                return "string length of value is 0";
            }
        }

        return true;
    }

    public static function isValidValueInArrayList($value, Array $allow_types_for_unique_values)
    {
        $value_type = gettype($value);

        if (!in_array($value_type, $allow_types_for_unique_values))
        {
            $str = implode(" or ", $allow_types_for_unique_values);
            return "value should be ($str)";
        }

        return true;
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

    public function setRequiredAttributes(Array $list)
    {
        $result = self::isValidStringArrayList($list);

        if (is_string($result))
        {
            self::throwException($result);
        }

        $this->required_attributes = $list;
    }

    public function getRequiredAttributes()
    {
        return $this->required_attributes;
    }

    public function setUniqueAttributes(Array $list)
    {
        $result = self::isValidStringArrayList($list);

        if (is_string($result))
        {
            self::throwException($result);
        }

        $this->unique_attributes = $list;
    }

    public function getUniqueAttributes()
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

        $info_json_obj = self::$info_json_database->get();

        self::getComposerJson();

        if (!isset(self::$composer_json['version']))
        {
            self::throwException("version is not set in composer_json");
        }

        $info_json_obj['JsonDatabase'] = [
            'version' => self::$composer_json['version']
        ];

        $file_size_bytes = filesize($this->getFile());
        $file_size_kb = floor($file_size_bytes / 1024);
        $file_size_mb = floor($file_size_kb / 1024);

        if (!isset($info_json_obj['files'][$this->file_name]))
        {
            $info_json_obj['files'][$this->file_name] = [];
            $info_json_obj['files'][$this->file_name]['file_info'] = [
                'created' => date(self::DEFAULT_DATE_TIME_FORMAT)
            ];
        }

        $info_json_obj['files'][$this->file_name]['file_info']['size']['bytes'] = $file_size_bytes;
        $info_json_obj['files'][$this->file_name]['file_info']['size']['kb'] = $file_size_kb;
        $info_json_obj['files'][$this->file_name]['file_info']['size']['mb'] = $file_size_mb;
        $info_json_obj['files'][$this->file_name]['file_info']['modified'] = date(self::DEFAULT_DATE_TIME_FORMAT);

        $info_json_obj['JsonDatabase']['file_count'] = count($info_json_obj['files']);

        $info_json_obj['files'][$this->file_name]['json_array_count'] = count($json_array);
        $info_json_obj['files'][$this->file_name]['json_string_length'] = strlen($json_str);

        $json_str = self::jsonEncode($info_json_obj);

        $info_file = self::$info_json_database->getFile();

        $result = file_put_contents($info_file, $json_str);

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
                    $result = self::isValidValueInArrayList($save_record[$unique_key], self::ALLOW_VALUES_IN_JSON_RECORD);

                    if (is_string($result))
                    {
                        $error_list[] = "save_record : $result";
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

    
    private function _alterRecordBeforeSave(array &$json_array, $index = null)
    {
        if ($this->config['attributes']['created'])
        {
            if (is_null($index))
            {
                $json_array['created'] = date(self::DEFAULT_DATE_TIME_FORMAT);
                $json_array['updated'] = null;
            }
        }

        if ($this->config['attributes']['updated'])
        {
            if (!is_null($index))
            {
                $json_array['updated'] = date(self::DEFAULT_DATE_TIME_FORMAT);
            }
        }
    }

    public function insert(Array $save_record)
    {
        $records = $this->get();

        $this->_checkBeforeSave($records, $save_record);

        $this->_alterRecordBeforeSave($save_record);

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

        $this->_alterRecordBeforeSave($save_record, $index);

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
        }
    }

    public function empty()
    {
        $this->write([]);
    }

    public function filter(Array $records, Array $attributes, Array $conditions, String $sort_by = "", String $sort_dir = "asc")
    {
        $error_list = [];

        $result = self::isValidStringArrayList($attributes);

        if (is_string($result))
        {
            $error_list[] = "attributes : $result";
        }

        if (!empty($conditions))
        {
            foreach($conditions as $key => $cond_value)
            {
                if (!is_string($key))
                {
                    $error_list[] = "conditions : key should be string";
                }

                if (is_callable($cond_value))
                {
                    //its is function
                }
                else
                {
                    $result = self::isValidValueInArrayList($cond_value, self::ALLOW_VALUES_IN_JSON_RECORD);

                    if (is_string($result))
                    {
                        $error_list[] = "conditions : $result";
                    }
                }
            }
        }

        if ($sort_by)
        {
            if (!in_array($sort_by, $attributes))
            {
                $attributes[] = $sort_by;
            }

            $sort_dir = strtolower(trim($sort_dir));

            if (!in_array($sort_dir, ["asc", "desc"]))
            {
                $error_list[] = "sort_dir : $result";
            }
        }

        if (count($error_list) > 0)
        {
            if (self::$show_error_as_html)
            {
                self::showErrors("filter Errors", $error_list);
            }
            else
            {
                throw new UserDataException("filter Errrors", $error_list);
            }
        }

        foreach($records as $k => $record)
        {
            if (!empty($conditions))
            {
                foreach($conditions as $key => $cond_value)
                {
                    if ( array_key_exists($key, $record))
                    {
                        $result = self::isValidValueInArrayList($record[$key], self::ALLOW_VALUES_IN_JSON_RECORD);

                        if (is_string($result))
                        {
                            $error_list[] = "Records -> $k : $result";
                        }

                        if (empty($error_list))
                        {
                            if (is_callable($cond_value))
                            {
                                $result = $cond_value($k, $record, $key);

                                if ($result === false)
                                {
                                    unset($records[$k]);
                                }
                            }
                            else
                            {
                                $record_value = (string) $record[$key];

                                $record_value = strtolower(trim($record_value));

                                $cond_value = (string) $cond_value;

                                $cond_value = strtolower(trim($cond_value));

                                if ($record_value != $cond_value)
                                {
                                    unset($records[$k]);
                                }
                            }
                        }
                    }
                    else
                    {
                        unset($records[$k]);
                    }
                }
            }

            if (!empty($attributes))
            {
                foreach($attributes as $key)
                {
                    if ( !array_key_exists($key, $record))
                    {
                        unset($records[$k][$key]);
                    }
                }
            }
        }

        if (count($error_list) > 0)
        {
            if (self::$show_error_as_html)
            {
                self::showErrors("filter Errors", $error_list);
            }
            else
            {
                throw new UserDataException("filter Errrors", $error_list);
            }
        }

        if ($sort_by)
        {
            usort($records, function($a, $b) use($sort_by, $sort_dir)
            {
                if (isset($a[$sort_by]) && isset($b[$sort_by]))
                {
                    if (is_numeric($a[$sort_by]) && is_numeric($b[$sort_by]))
                    {
                        if ($a[$sort_by] == $b[$sort_by])
                        {
                            return 0;
                        }

                        if ($sort_dir == "asc")
                        {
                            return $a[$sort_by] > $b[$sort_by] ? 1 : -1;
                        }

                        if ($sort_dir == "desc")
                        {
                            return $a[$sort_by] < $b[$sort_by] ? 1 : -1;
                        }
                    }

                    if (is_string($a[$sort_by]) && is_string($b[$sort_by]))
                    {
                        if ($a[$sort_by] == $b[$sort_by])
                        {
                            return 0;
                        }

                        if ($sort_dir == "asc")
                        {
                            return strcmp($a[$sort_by], $b[$sort_by]);
                        }

                        if ($sort_dir == "desc")
                        {
                            return strcmp($b[$sort_by], $a[$sort_by]);
                        }
                    }
                }
            });
        }

        return $records;
    }
}
