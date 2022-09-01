# Listen Broker Mqtt

## Preparação do Servidor

* **Mosquitto Library**
   
   No servidor instalar a Mosquitto-PHP - MQTT Client Library.
   
   https://www.hivemq.com/blog/mqtt-client-library-encyclopedia-mosquitto-php/

## Criar o service no systemd.

* **Configuração**

    No linux AWS o diretório onde ficam os arquivos de configuração é em /lib/systemd/system.
    Criar o arquivo  php-subscribe-mqtt.service

    ```
    [Unit]
    Description=Php Subscribe Mqtt and write in Mysql
    After=php-fpm.service

    [Service]
    Type=idle
    User=broker
    ExecStart=/usr/bin/php /home/broker/listen.php
    Restart=on-failure

    [Install]
    WantedBy=multi-user.target
    ```

* **Comandos**

    ```bash
    systemctl start php-subscribe-mqtt.service
    ```
    ```bash
    systemctl stop php-subscribe-mqtt.service
    ```
    ``` bash
    systemctl status php-subscribe-mqtt.service
    ```
    ``` bash
    systemctl enable php-subscribe-mqtt.service
    ```
    
* **Referências**    
    https://www.shubhamdipt.com/blog/how-to-create-a-systemd-service-in-linux/
    
    https://medium.com/@benmorel/creating-a-linux-service-with-systemd-611b5c8b91d6

## Mysql

* **Store Procedures**

```sql
DELIMITER $$

USE `exehda`$$

DROP PROCEDURE IF EXISTS `exd_insertSensorDataByUuid`$$

CREATE PROCEDURE `exd_insertSensorDataByUuid`(IN sensor_uuid CHAR(36), IN collection_date DATETIME, IN collected_value DECIMAL(10,6), OUT insertId INT )
BEGIN
SET @id = ( SELECT exd_sensor.sensor_id FROM exd_sensor WHERE exd_sensor.uuid= sensor_uuid );
IF @id IS NULL  THEN
   SET insertId := @id;
ELSE
   INSERT INTO exd_sensor_data (sensor_id,collection_date,collected_value,publication_date) VALUES (@id,collection_date,collected_value,NOW());
   SET insertId := LAST_INSERT_ID();
END IF;
END$$

DELIMITER ;
```
