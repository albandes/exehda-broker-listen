<?php
error_reporting(E_ALL);

use Albandes\mqtt;
use Albandes\db;
use vlucas\phpdotenv;

require("vendor/autoload.php");

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('DEBUG')->isBoolean();

date_default_timezone_set($_ENV['TIME_ZONE']);

// Use Mosquitto-PHP library
$client = new Mosquitto\Client('exehda-client');
$client->setCredentials($_ENV['BROKER_USERNAME'],$_ENV['BROKER_PASSWORD']);
$client->connect($_ENV['BROKER_URL']);


// Connect database
try{
    $dsn = "mysql:dbname={$_ENV['DB_NAME']};port={$_ENV['DB_PORT']};host={$_ENV['DB_HOSTNAME']}";
    $db = new DB($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);   
}catch(\PDOException $e){
    die("<br>Error connecting to database: " . $e->getMessage() . " File: " . __FILE__ . " Line: " . __LINE__ );
}


$debug = filter_var($_ENV['DEBUG'], FILTER_VALIDATE_BOOLEAN) ;

// run
$listen = new mqtt($client, $db);
$listen->set_debug($debug);
$listen->setTopics(['rogerio/#' => 0, 'teste' => 0]);
$listen->run();




