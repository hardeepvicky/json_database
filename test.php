<?php
require_once './vendor/autoload.php';
require_once './src/includes/functions.php';

use HardeepVicky\Json\JsonDatabase;

JsonDatabase::showErrorAsHtml(true);

$json_database = JsonDatabase::getInstance("./data_files", "users", [
    "attributes" => [
        "created" => true,
        "updated" => true,
    ]
]);

$json_database->setRequiredAttributes(["name", "age", "dob"]);
$json_database->setUniqueAttributes(["name"]);

    //$update_count = $json_database->update(["name" => "Elroy Pingree"], ["primary_key" => 2]);

    //dump($update_count, true);

    // for($i = 1; $i <= 2; $i++)
    // {
    //     $json_database->insert([
    //         "name" => get_random_name(),
    //         "age" => mt_rand(5,60),
    //         "dob" => get_random_date_time()
    //     ]);
    // }

$records = $json_database->get();

dump($records);

//$records = $json_database->filter($records, [], ["name" => null]);

$records = $json_database->filter($records, [], ["age" => function($index, $record, $key)
{
    if ($record[$key] < 25)
    {
        return false;
    }
}], "age", "desc");

dump($records); 

$info_records = JsonDatabase::getInfo("./data_files");

dump($info_records);

$attr_info_records = JsonDatabase::getAttributeInfo("./data_files");

dump($attr_info_records);

exit;

/*

$json_database->update(["name" => "vicky", "age" => 28], ["name" => "vicky"]);

$records = $json_database->get();

dump($records);

$records = $json_database->filter($records, [], ["name" => function($index, $record, $key)
{
    if (is_null($record[$key]))
    {
        return false;
    }
}], "age", "asc");

dump($records);
*/
