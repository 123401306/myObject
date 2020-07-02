<?php 
require __DIR__ . '/../../../../vendor/autoload.php';

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * 延迟队列测试
 * 消费死信队列 queue.delay
 */

// todo 更改配置
$connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest', '/');
$channel = $connection->channel();

$channel->exchange_declare('exchange.delay', AMQPExchangeType::DIRECT, false, true);
$channel->queue_declare('queue.delay', false, true, false, false);

$channel->queue_bind('queue.delay', 'exchange.delay', 'routingkey.delay');

function process_message($message)
{
    echo "考试时间到，自动提交试卷:" . $message->body . PHP_EOL;
    // todo 获取订单的状态，如果未支付，则进行取消订单操作
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    echo "交卷成功！" . PHP_EOL;
}

$channel->basic_consume('queue.delay', 'cancelOrder', false, false, false, false, 'process_message');

function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}
register_shutdown_function('shutdown', $channel, $connection);

while ($channel ->is_consuming()) {
    $channel->wait();
}
