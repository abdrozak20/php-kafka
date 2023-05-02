<?php

// Run multiple instances of amqp_consumer_fanout_1.php and
// amqp_consumer_fanout_2.php to test

require './vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel = consumeCreateQueue($channel);
$channel = consumeLogQueue($channel);

function consumeCreateQueue($channel)
{
    $exchange = 'amq.topic';
    $queue = 'create_pdf_queue';
    $bindingKeys = 'pdf.#';

    /*
        name: $exchange
        type: direct
        passive: false // don't check if a exchange with the same name exists
        durable: false // the exchange will not survive server restarts
        auto_delete: true //the exchange will be deleted once the channel is closed.
    */
    $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
    
    /*
        name: $queue    // should be unique in fanout exchange.
        passive: false  // don't check if a queue with the same name exists
        durable: false // the queue will not survive server restarts
        exclusive: false // the queue might be accessed by other channels
        auto_delete: true //the queue will be deleted once the channel is closed.
    */
    list($queueName, ,) = $channel->queue_declare($queue, false, true, false, false);
    
    $channel->queue_bind($queueName, $exchange, $bindingKeys);

    function processMessageCreate($message)
    {
        echo "\n---- create ----\n";
        echo $message->body;
        echo "\n---- create ----\n";

        $message->ack();

        // Send a message with the string "quit" to cancel the consumer.
        if ($message->body === 'quit') {
            $message->getChannel()->basic_cancel('create_pdf');
        }
    }

    /*
        queue: Queue from where to get the messages
        consumer_tag: Consumer identifier
        no_local: Don't receive messages published by this consumer.
        no_ack: If set to true, automatic acknowledgement mode will be used by this consumer. See https://www.rabbitmq.com/confirms.html for details.
        exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
        nowait: don't wait for a server response. In case of error the server will raise a channel
                exception
        callback: A PHP Callback
    */

    $channel->basic_consume($queue, $exchange.'create', false, false, false, false, 'processMessageCreate');

    return $channel;
}

function consumeLogQueue($channel)
{
    $exchange = 'amq.topic';
    $queue = 'log_pdf_queue';
    $bindingKeys = 'pdf.#';
    $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
    list($queueName, ,) = $channel->queue_declare($queue, false, true, false, false);
    
    $channel->queue_bind($queueName, $exchange, $bindingKeys);

    function processMessageLog($message)
    {
        echo "\n---- log ----\n";
        echo $message->body;
        echo "\n---- log ----\n";

        $message->ack();

        if ($message->body === 'quit') {
            $message->getChannel()->basic_cancel('log_pdf');
        }
    }
    $channel->basic_consume($queue, $exchange.'log', false, false, false, false, 'processMessageLog');

    return $channel;
}

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();