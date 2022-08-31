# Listen Broker Mqtt

## Classe MQTT usada na API

* **Links**

    * [How to use mqtt in php](https://www.emqx.com/en/blog/how-to-use-mqtt-in-php)

No servidor instalar a Mosquitto-PHP - MQTT Client Library 
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

