<?php
require_once './vendor/autoload.php';
require_once './src/includes/functions.php';

use HardeepVicky\Json\JsonDatabase;

JsonDatabase::showErrorAsHtml(true);

$json_database = new JsonDatabase("./data_files", "test");

$json_database->setRequiredAttr(["name"]);

for($i = 1; $i <= 10; $i++)
{
    $json_database->insert([
        "name" => get_random_name(),
        "date_time" => get_random_date_time()
    ]);
}

$records = JsonDatabase::getInfo("./data_files");

dump($records);


$json_database = new JsonDatabase("./data_files", "users");

$json_database->setRequiredAttr(["name"]);

$json_database->setUniqueAttr(["name"]);

$json_database->insert(["name" => "vicky"]);

$records = $json_database->get();
