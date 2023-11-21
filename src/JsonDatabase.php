<?php
namespace HardeepVicky\Json;

use Exception;

class JsonDatabase
{
    const FILE_INFO = "info";    
    const FILE_ATTRIBUTE = "attribute";    

    const PRIMARY_KEY = "primary_key";

    const ALLOW_VALUES_IN_JSON_RECORD = [
        'boolean', 'integer', 'double', 'string', 'NULL'
    ];

    const DEFAULT_CONFIG = [
        'attributes' => [
            'created' => false,
            'updated' => false,
        ]
    ];

    const RESERVE_FILE_NAMES = [self::FILE_INFO, self::FILE_ATTRIBUTE];    

    const DEFAULT_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private static $info_json_database, $composer_json, $attribute_json_database;
    private static bool $show_error_as_html = true;

    private String $path;
    private String $file_name;
    private Array $config;

    private Array $required_attributes = [];

    private Array $unique_attributes = [
        self::PRIMARY_KEY
    ];

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

        return self::$composer_json = self::jsonDecode($json_str);
    }


    private function __construct(String $path, String $file_name, Array $config = [])
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

    public static function getInstance(String $path, String $file_name, Array $config = [])
    {
        $instance = new self($path, $file_name, $config); 

        if (in_array($instance->file_name, self::RESERVE_FILE_NAMES))
        {
            self::throwException("$instance->file_name is reserved. this is file use by JsonDatabase");
        }

        return $instance;
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
            return "value type is $value_type, it should be ($str)";
        }

        return true;
    }

    public static function checkDataType(Array $save_record, String $msg_prefix = "", String $msg_postfix = "")
    {
        $error_list = [];

        foreach($save_record as $k => $value)
        {
            $result = self::isValidValueInArrayList($value, self::ALLOW_VALUES_IN_JSON_RECORD);

            if (is_string($result))
            {
                $error_list[] = $msg_prefix . $result . $msg_postfix;
            }
        }

        if (count($error_list) > 0)
        {
            if (self::$show_error_as_html)
            {
                self::showErrors("Data Type Errors", $error_list);
            }
            else
            {
                throw new UserDataException("Fail To Save", $error_list);
            }
        }
    }

    public function getInfoFile()
    {
        return self::FILE_INFO;
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
            self::$info_json_database = new JsonDatabase($path, self::FILE_INFO);
        }

        $info_json_obj = self::$info_json_database->get();

        if ( empty($info_json_obj) )
        {
            if ( empty(self::$composer_json) )
            {
                self::getComposerJson();

                if ( !isset(self::$composer_json['version']) )
                {
                    self::throwException("version is not set in composer.json");
                }
            }

            $info_json_obj['JsonDatabase'] = [
                'version' => self::$composer_json['version']
            ];
        }

        return $info_json_obj;
    }

    private static function _writeInfo(String $path, String $file_name, Array $file_info, Array $json_info = [])
    {
        $info_json_obj = self::getInfo($path);

        if (!isset($info_json_obj['files'][$file_name]))
        {
            $info_json_obj['files'][$file_name] = [
                'file_info' => [],
                'json_info' => [],
            ];

            $info_json_obj['JsonDatabase']['file_count'] = count($info_json_obj['files']);

            $file_info['created'] = date(self::DEFAULT_DATE_TIME_FORMAT);
        }

        $file_info['updated'] = date(self::DEFAULT_DATE_TIME_FORMAT);

        if ($file_info)
        {
            $info_json_obj['files'][$file_name]['file_info'] = array_merge($info_json_obj['files'][$file_name]['file_info'], $file_info);
        }

        if ($json_info)
        {
            $info_json_obj['files'][$file_name]['json_info'] = array_merge($info_json_obj['files'][$file_name]['json_info'], $json_info);
        }

        $json_str = self::jsonEncode($info_json_obj);

        $file = self::$info_json_database->getFile();

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To put json in $file");
        }

        return $info_json_obj;
    }

    public static function getAttributeInfo(String $path)
    {
        if (!self::$attribute_json_database)
        {
            self::$attribute_json_database = new JsonDatabase($path, self::FILE_ATTRIBUTE);
        }

        return self::$attribute_json_database->get();
    }

    public function getAttributeNextNumber($attribute_name)
    {
        $json_obj = self::getAttributeInfo($this->path);

        if (isset($json_obj[$this->file_name][$attribute_name]['last_value']))
        {
            if (!is_int($json_obj[$this->file_name][$attribute_name]['last_value']))
            {
                $json_obj[$this->file_name][$attribute_name]['last_value'] = 0;
            }
        }
        else
        {
            $json_obj[$this->file_name][$attribute_name]['data_types'][] = gettype(0);
            $json_obj[$this->file_name][$attribute_name]['first_value'] = 0;
            $json_obj[$this->file_name][$attribute_name]['last_value'] = 0;
        }

        $json_obj[$this->file_name][$attribute_name]['last_value'] += 1;

        $json_str = self::jsonEncode($json_obj);

        $file = self::$attribute_json_database->getFile();

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To put json in $file");
        }

        return $json_obj[$this->file_name][$attribute_name]['last_value'];
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

    private function write(array $json_array)
    {
        $file = $this->getFile();

        $json_str = self::jsonEncode($json_array);

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To write json in $file");
        }

        $file_info = [
            'file_size' => [
                'bytes' => 0,
                'kb' => 0,
                'mb' => 0,
            ]
        ];

        $file_info['file_size']['bytes'] = $file_size_bytes = filesize($this->getFile());
        $file_info['file_size']['kb'] = $file_size_kb = floor($file_size_bytes / 1024);
        $file_info['file_size']['mb'] = floor($file_size_kb / 1024);
        
        $json_info['json_array_count'] = count($json_array);
        $json_info['json_string_length'] = strlen($json_str);

        self::_writeInfo($this->path, $this->file_name, $file_info, $json_info);
    }

    

    private function _checkForRequired(Array $save_record)
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

    }

    private function _checkForUnique(Array $records, Array $save_record, $index = null)
    {
        $error_list = [];

        if ($this->unique_attributes)
        {
            foreach($this->unique_attributes as $unique_key)
            {
                $is_found = false;

                foreach($records as $k => $record)
                {
                    if (isset($record[$unique_key]) && !is_null($record[$unique_key]))
                    {
                        if ($k != $index && $record[$unique_key] == $save_record[$unique_key])
                        {
                            if (is_null($index))
                            {
                                $is_found = true;
                            }
                            else if ($k != $index)
                            {
                                $is_found = true;
                            }
                        }
                    }
                }

                if ($is_found)
                {
                    $error_list[] = "duplicate $unique_key : " . $save_record[$unique_key];
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

    private function _updateAttributeInfo(Array $save_record)
    {
        $json_obj = self::getAttributeInfo($this->path);

        foreach($save_record as $attribute_name => $v)
        {
            if (!isset($json_obj[$this->file_name][$attribute_name]))
            {
                $json_obj[$this->file_name][$attribute_name] = [
                    'data_types' => [],
                    'first_value' => $v,
                    'last_value' => "",
                ];
            };

            $data_type = gettype($v);

            if (!in_array($data_type, $json_obj[$this->file_name][$attribute_name]['data_types'] ) )
            {
                $json_obj[$this->file_name][$attribute_name]['data_types'][] = $data_type;
            }

            $json_obj[$this->file_name][$attribute_name]['last_value'] = $v;
        }

        $json_str = self::jsonEncode($json_obj);

        $file = self::$attribute_json_database->getFile();

        $result = file_put_contents($file, $json_str);

        if ($result === false) {
            self::throwException("Fail To put json in $file");
        }

        return $json_obj;
    }


    public function insert(Array $save_record)
    {
        self::checkDataType($save_record, " save_record : ");

        $this->_checkForRequired($save_record);

        $records = $this->get();

        $save_record[self::PRIMARY_KEY] = $this->getAttributeNextNumber(self::PRIMARY_KEY);

        $this->_checkForUnique($records, $save_record);

        $this->_alterRecordBeforeSave($save_record);

        $this->_updateAttributeInfo($save_record);

        $records[] = $save_record;

        $this->write($records);
    }

    public function update(Array $save_record, Array $conditions)
    {
        self::checkDataType($save_record, " save_record : ");

        $records = $this->get();

        $records_to_overide = $this->filter($records, [], $conditions);

        foreach($records_to_overide as $index => $record)
        {
            if (!isset($records[$index]))
            {
                self::throwException("index $index not found in records");
            }

            $new_record = array_merge($record, $save_record);

            $this->_checkForRequired($new_record);

            $this->_checkForUnique($records, $new_record, $index);

            $this->_alterRecordBeforeSave($new_record, $index);

            $this->_updateAttributeInfo($new_record);

            $records[$index] = $new_record;
        }

        $this->write($records);

        return count($records_to_overide);
    }

    public function delete(Array $conditions)
    {
        $records = $this->get();

        $records_to_delete = $this->filter($records, [], $conditions);

        foreach($records_to_delete as $index => $record)
        {
            if (!isset($records[$index]))
            {
                self::throwException("index $index not found in records");
            }

            unset($records[$index]);
        }

        $this->write(array_values($records));

        return count($records_to_delete);
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
                $error_list[] = "sort_dir should be (asc or desc)";
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
