<?php

namespace Albandes;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use vlucas\phpdotenv;

/**
 * mqtt
 *
 * PHP class to subscribe mqtt broker
 *
 * @author  Rogério Albandes <rogerio.albandes@gmail.com>
 * @version 0.1
 * @package mqtt
 * @example example.php
 * @link    https://github.com/albandes/mqtt
 * @license GNU License
 *
 */

class mqtt
{

 
    /**
     * mqtt
     *
     * @var Client The MQTT client
     */
    private $mqtt;
         
    /**
     * db
     *
     * @var PDO A PDO database connection
     */
    private $db;
         
    /**
     * topics
     *
     * @var array The list of topics to subscribe to
     */
    private $topics = [];
      
    /**
     * insertMessage
     *
     * @var PDOStatement A prepared statement used when we record a message
     */
    private $insertMessage;

    /**
     * updateDevice
     *
     * @var PDOStatement A prepared statement used when we record a device
     */
    private $updateDevice;

    /**
     * debug
     *
     * @var boolean Debug status
     */
    private $_debug ;

        
        
    /**
     * _storeProcedure
     *
     * @var mixed
     */
    private $_storeProcedure;
  
    /**
     * applogger
     *
     * @var object
     */
    protected $_applogger;

    /**
     * @param Client $mqtt The Mosquitto\Client instance
     * @param PDO $db a PDO database connection
     */
   

    /**
     * __construct
     *
     * @param  mixed $mqtt
     * @param  mixed $db
     * @return void
     */
    
    public function __construct(\Mosquitto\Client $client, \Albandes\DB $db)
    {

        $this->makeLogger();
        
        $this->mqtt = $client;
        $this->db = $db;

        /* Subscribe the Client to the topics when we connect */
        $this->mqtt->onConnect([$this, 'subscribeToTopics']);
        $this->mqtt->onMessage([$this, 'handleMessage']);     
        
        $this->_applogger->info('Run script ');   
        
    }
    
    /**
     * makeLogger
     *
     * @return void
     */
    public function makeLogger()
    {
        // create a log channel
        $formatter = new LineFormatter(null, "d/m/Y H:i:s");
        $stream = new StreamHandler( $_ENV['LOG_FILE'], Logger::DEBUG);
        $stream->setFormatter($formatter);
        $logger = new Logger('exehda');
        $logger->pushHandler($stream);
        $this->set_applogger($logger);
    }
    
    /**
     * @param array $topics
     *
     * An associative array of topics and their QoS values
     */
    public function setTopics(array $topics)
    {
        $this->topics = $topics;
    }
       
    /**
     * subscribeToTopics
     * 
     * The internal callback used when the Client instance connects
     *
     * @return void
     */
    public function subscribeToTopics() {
        foreach ($this->topics as $topic => $qos) {
            $this->mqtt->subscribe($topic, $qos);
        }
    }

    /**
     * @param Message $message
     * The internal callback used when a new message is received
     */
    public function handleMessage($message)
    {
        $debug = $this->get_debug();
        $storeProcedure = $this->get_storeProcedure();

        $arrayMessage = explode('/', $message->topic);
        
        if($debug == true) 
            $this->echoPayload($message);


        if($arrayMessage[0] != 'rogerio') {
            return;
        }    

        if($arrayMessage[0] == 'rogerio') {
            
            $this->_applogger->reset();

            $elements = 0;
            $aPayload = json_decode($message->payload,true);
         
            if ($aPayload['type'] == 'collect' ) {
                if (is_array($aPayload)) 
				    $elements = count($aPayload) ;
                else 
                    return;    

                if($storeProcedure == true) {
                    
                    $sql = "CALL exd_insertSensorDataByUuid(?,?, ?, @out)";
                    $param = array($aPayload['uuid_sensor'],$aPayload['date'],$aPayload['data']); 
                    $this->db->insert($sql, $param);    
    
                    $query = "SELECT @out as lastInsertId";
                    $query = $this->db->query($query);
                    $rs = $query->fetch();
                    
                    if(is_null($rs['lastInsertId'])) {
                        $this->_applogger->error('UUID not exists in sensor table! ',['uuid'=>$aPayload['uuid_sensor']]);    
                        return;                     
                    }

                    $this->_applogger->info('Saved in the database ',['table_id'=>$rs['lastInsertId'],'topic' => $arrayMessage[0],'json' => $aPayload]); 

                } else {
                    
                    if ($elements > 1) 
                        $sensorObj = $this->getSensorByUUID($aPayload['uuid_sensor']);
                    else
                        return;

                    if(!$sensorObj ) {
                        $this->_applogger->error('UUID not exists in sensor table! ',['uuid'=>$aPayload['uuid_sensor']]);    
                        return; 
                    }

                    $sql = "INSERT INTO exd_sensor_data (sensor_id,collection_date,collected_value,publication_date) VALUES (?,?,?,NOW(6))";
                    $param = array($sensorObj->sensor_id,$aPayload['date'],$aPayload['data']);
                    $lastInsertId = $this->db->insert($sql, $param);
                    $this->_applogger->info('Saved in the database ',['table_id'=>$lastInsertId,'topic' => $arrayMessage[0],'json' => $aPayload]); 

                }


            }  

        } elseif($aPayload['type'] == 'log' ) {
                // {"date": "2022-8-31 11:30:3", "type": "log", "data": "Except thread_sub: -1", "uuid_gateway": "5aa027bd-4afc-461c-b353-c2535008f4ce"}
                
        }
                

    }
    
    /**
     * getSensorByUUID
     *
     * @param  mixed $uuid
     * @return object PDO fetch
     */
    public function getSensorByUUID($uuid)
    {

        $query = "SELECT * FROM exd_sensor WHERE `uuid`=:uuid";
        $queryObj = $this->db->query($query, ['uuid'=> $uuid]);
        $arraySensor = $queryObj->fetch();
        
        if(!$arraySensor)
            return false;
        
        $object = (object) $arraySensor;
        return $object;        

    }
    
    /**
     * echoPayload
     *
     * @param  mixed $messageObj
     * @return void
     */
    public function echoPayload($messageObj)
    {
        
        echo PHP_EOL;
        echo "Topic: {$messageObj->topic} \n";
        $aPayload = json_decode($messageObj->payload,true);
        echo "Message payload: {$messageObj->payload}";
        echo PHP_EOL;
        //$arrayMessage = explode('/', $message->topic);
        //print_r($arrayMessage);
        //print_r($aPayload);
    }

    /**
     * Start recording messages
     */
    public function run()
    {
        $this->mqtt->loopForever();
    }
             

    /**
     * Get debug status
     *
     * @return  boolean
     */ 
    public function get_debug()
    {
        return $this->_debug;
    }

    /**
     * Set debug status
     *
     * @param  boolean  $_debug  Debug status
     *
     * @return  self
     */ 
    public function set_debug($_debug)
    {
        $this->_debug = $_debug;

        return $this;
    }

    /**
     * Get applogger
     *
     * @return  object
     */ 
    public function get_applogger()
    {
        return $this->_applogger;
    }

    /**
     * Set applogger
     *
     * @param  object  $_applogger  applogger
     *
     * @return  self
     */ 
    public function set_applogger(object $_applogger)
    {
        $this->_applogger = $_applogger;

        return $this;
    }

    /**
     * Get _storeProcedure
     *
     * @return  mixed
     */ 
    public function get_storeProcedure()
    {
        return $this->_storeProcedure;
    }

    /**
     * Set _storeProcedure
     *
     * @param  mixed  $_storeProcedure  _storeProcedure
     *
     * @return  self
     */ 
    public function set_storeProcedure($_storeProcedure)
    {
        $this->_storeProcedure = $_storeProcedure;

        return $this;
    }
}    