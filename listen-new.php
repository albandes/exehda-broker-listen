<?php
error_reporting(E_ALL);

use Albandes\mqtt;
use Albandes\db;

require("vendor/autoload.php");

// Use Mosquitto-PHP library
$client = new Mosquitto\Client('myclient');
$client->setCredentials('middleware','exehda');
$client->connect('brokermqtt1.exehda.org');

// Connect database
$db = new DB('mysql:dbname=exehda;host=10.42.44.200', 'pipeadm', 'qpal10');


$listen = new mqtt($client, $db);
$listen->set_debug(true);
$listen->setTopics(['rogerio/#' => 0]);
$listen->run();



// $db, $username = NULL, $password = NULL, $host = '127.0.0.1', $port = 3306, $options = []



echo "\n";





