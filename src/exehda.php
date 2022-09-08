<?php

namespace Albandes;

use Albandes\services;


/**
 * exehda
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

class exehda
{
    /**
     * applogger
     *
     * @var object
     */
    public $_applogger;

    /**
     * db
     *
     * @var PDO A PDO database connection
     */
    private $_db;    

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
     * __construct
     *
     * @param  object $db
     * @return void
     */

    public function __construct( \Albandes\DB $db)
    {
        
        $this->_db = $db;
                
        $services = new services();
        $this->_applogger = $services->get_applogger();
        
    }
        
    /**
     * makeTopics
     *
     * @param  mixed $env
     * @return void
     */
    public function makeTopics($env)
    {
        
        $arrayTemp = explode(',',$env['BROKER_TOPIC']);
        $arrayTopics = array();
        foreach ($arrayTemp as $i => $topic) {
            $arrayTopics = array_merge( $arrayTopics , [trim($topic)=>$env['BROKER_QOS']] );
        }

        return $arrayTopics;
    }
    
    /**
     * saveMessage
     *
     * @param  mixed $message
     * @return void
     */
    public function saveMessage($message)
    {
       /* Display the message's topic and payload */
       echo $message->topic, "\n", $message->payload, "\n\n"; 

       $debug = $this->get_debug();
       $storeProcedure = $this->get_storeProcedure();

       $arrayMessage = explode('/', $message->topic);
       
       if($debug == true) 
           $this->echoPayload($message);

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
                    $this->_db->insert($sql, $param);    

                    $query = "SELECT @out as lastInsertId";
                    $query = $this->_db->query($query);
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
                    $lastInsertId = $this->_db->insert($sql, $param);
                    $this->_applogger->info('Saved in the database ',['table_id'=>$lastInsertId,'topic' => $arrayMessage[0],'json' => $aPayload]); 

                }
            
            } elseif($aPayload['type'] == 'log' ) {

                $sql = "INSERT INTO exd_log (message,datetime_log,gateway_uuid) VALUES (?,?,?)";
                // {"date": "2022-8-31 11:30:3", "type": "log", "data": "Except thread_sub: -1", "uuid_gateway": "5aa027bd-4afc-461c-b353-c2535008f4ce"}
                $param = array($aPayload['data'],$aPayload['date'], $aPayload['uuid_gateway']);
                $lastInsertId = $this->_db->insert($sql, $param);
                $this->_applogger->info('Saved in the database ',['table_id'=>$lastInsertId,'topic' => $arrayMessage[0],'json' => $aPayload]); 
                
            }     


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
        $queryObj = $this->_db->query($query, ['uuid'=> $uuid]);
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
