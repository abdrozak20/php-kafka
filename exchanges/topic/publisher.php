<?php

require './vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;


$exchange = 'amq.topic';

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

/*
    name: $exchange
    type: fanout
    passive: false // don't check is an exchange with the same name exists
    durable: false // the exchange won't survive server restarts
    auto_delete: true //the exchange will be deleted once the channel is closed.
*/

$channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);

$messageBody =  $argv[1];;
$routingKey = 'pdf.#';
$message = new AMQPMessage($messageBody, array('content_type' => 'text/plain'));
$channel->basic_publish($message, $exchange, $routingKey);
echo $argv[1];

$channel->close();
$connection->close();