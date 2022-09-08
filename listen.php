<?php
error_reporting(E_ALL);

use Albandes\db;
use Albandes\exehda;
use Albandes\services;

use Mosquitto\Client;

require("vendor/autoload.php");

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('DEBUG')->isBoolean();

date_default_timezone_set($_ENV['TIME_ZONE']);
$debug = filter_var($_ENV['DEBUG'], FILTER_VALIDATE_BOOLEAN) ;
$storeProcedure = filter_var($_ENV['STORE_PROCEDURE'], FILTER_VALIDATE_BOOLEAN) ;

// Connect database
try{
    $dsn = "mysql:dbname={$_ENV['DB_NAME']};port={$_ENV['DB_PORT']};host={$_ENV['DB_HOSTNAME']}";
    $db = new DB($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);   
}catch(\PDOException $e){
    die("<br>Error connecting to database: " . $e->getMessage() . " File: " . __FILE__ . " Line: " . __LINE__ );
}

$exehda = new exehda($db);
$exehda->set_debug($debug);
$exehda->set_storeProcedure($storeProcedure);

$aTopics = $exehda->makeTopics($_ENV);

$services = new services();

/* Construct a new client instance, passing a client ID of “MyClient” */
$client = new Client('exehdaClient-'.mt_rand());

/* Set the callback fired when the connection is complete */
$client->onConnect(function($code, $message) use ($client, $services, $aTopics) {
    
    /* Subscribe to the broker's $SYS namespace, which shows debugging info */
    $logger = $services->get_applogger();
    if ($code == 0)
        $logger->info('Connect to broker: ' . $services->errorConnection($code));
    else
        $logger->error('Connect to broker: ' . $services->errorConnection($code));    

    foreach ($aTopics as $topic => $qos) {
            $client->subscribe($topic, $qos);
            $logger->info("Subscribe topic: {$topic}"); 
    }    
    
});

/* Set the callback fired when we receive a message */
$client->onMessage(function($message) use ($exehda,$db) {
    $exehda->saveMessage($message);
});

/* Connect, supplying the host and port. */
/* If not supplied, they default to localhost and port 1883 */
$client->setCredentials($_ENV['BROKER_USERNAME'],$_ENV['BROKER_PASSWORD']);
$client->connect($_ENV['BROKER_URL'], 1883, 60);

/* Enter the event loop */
$client->loopForever();
