<?php
require_once './vendor/autoload.php';
require_once './src/includes/functions.php';

use HardeepVicky\Json\JsonDatabase;

JsonDatabase::showErrorAsHtml(true);

$json_database = new JsonDatabase("./dist", "users");
$json_database->setRequiredAttr(["name"]);

$json_database->setUniqueAttr(["name"]);

$json_database->insert(["name" => "vicky"]);

$records = $json_database->get();

dump($records);
