<?php

use Mosquitto\Client;
use Mosquitto\Message;
error_reporting(E_ALL);



class MqttToDb {

    /* Our Mosquitto\Client instance */
    /**
     * @var Client The MQTT client
     */
    private $mqtt;

    /**
     * @var PDO A PDO database connection
     */
    private $db;

    /**
     * @var array The list of topics to subscribe to
     */
    private $topics = [];

    /**
     * @var PDOStatement A prepared statement used when we record a message
     */
    private $insertMessage;

    /**
     * @var PDOStatement A prepared statement used when we record a device
     */
    private $updateDevice;

    /**
     * @var boolean Debug status
     */
    private $debug ;

    /**
     * @param Client $mqtt The Mosquitto\Client instance
     * @param PDO $db a PDO database connection
     */
    public function __construct(Client $mqtt, PDO $db)
    {
        $this->mqtt = $mqtt;
        $this->db = $db;
        


        

    

/*         $this->insertMessage = $this->db->prepare(
            'INSERT INTO mqtt_logs (id, topic, payload, received) VALUES (?, ?, ?, NOW());'
        );
 */

        /* Subscribe the Client to the topics when we connect */
        $this->mqtt->onConnect([$this, 'subscribeToTopics']);
        $this->mqtt->onMessage([$this, 'handleMessage']);

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
     * The internal callback used when the Client instance connects
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
        //$this->insertMessage->execute([$message->mid, $message->topic, $message->payload]);

        $arrayMessage = explode('/', $message->topic);

        if ($this->debug) {
            echo "Topic: {$message->topic} \n";
            print_r($arrayMessage);
            $aPayload = json_decode($message->payload,true);
            print_r($aPayload);
            echo "--- Message payload --- \n\n";
            echo $message->payload;
            echo "\n\n-------- \n\n";
        }
        
        if($arrayMessage[0] == 'rogerio') {

            $aPayload = json_decode($message->payload,true);
            
			$elements = 0;
            
			if (is_array($aPayload)) 
				$elements = count($aPayload) ;
            
            if ($this->debug)
                echo "count array: {$elements} \n";
            
			if ($elements > 1) {
                $sensorObj = $this->getSensorByUUID($aPayload['uuid_sensor']);
                
                try {
                    $this->updateDevice = $this->db->prepare( "INSERT INTO exd_sensor_data (sensor_id,collection_date,collected_value,publication_date)        
                                                                VALUES (?,?,?,NOW(6))" );     
                        
                    $ret = $this->updateDevice->execute([$sensorObj->sensor_id,$aPayload['date'],$aPayload['data']]);                                                                   
                                                                   
                } catch (\PDOException $e) {
                    echo $e->getMessage();
                }                                    
                                            
                if (!$ret) {
					$aError = $this->updateDevice->errorInfo();
					print $aError[2];
                }
                                            
            }            
        
        }
        


    }

    /**
     * Start recording messages
     */
    public function start()
    {
        $this->mqtt->loopForever();
    }

    public function getSensorByUUID($uuid)
    {
        $stmt = $this->db->prepare("SELECT * FROM exd_sensor WHERE `uuid` = ?");
        try {
            $stmt->execute([$uuid]); 
        } catch (\PDOException $e) {
            //$logger->error('Db error: ' . $e->getMessage() , ['linha' => __LINE__ ] );
            die($e->getMessage() . ' linha ' . __LINE__);
        }
        
        return $stmt->fetch();        

    }

    public function setDebug(string $debug)
    {
        $this->debug = $debug;
    }


}

/* Create a new DB connection */
try {
	$db = new PDO('mysql:host=10.42.44.200;dbname=exehda;charset=utf8', 'pipeadm', 'qpal10');
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ); 
}
catch(\PDOException $e) 
{
	die("Connection failed: " . $e->getMessage());
}

   

/* Configure our Client */
$mqtt = new Client();



$mqtt->setCredentials('middleware','exehda');
$mqtt->connect('brokermqtt1.exehda.org');





$listen = new MqttToDb($mqtt, $db);
$listen->setTopics([
    'rogerio/#' => 0
]);

$listen->setDebug(true);


$listen->start();
