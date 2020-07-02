<?php
require_once "lib\RabbitMQ.php";

class mq
{
    public function push()
    {
        $exchange = 'test_exchange';
        $queue = 'test_queue';
        $mq = new RabbitMQ();
        $mq->exchange($exchange)
            ->exchange('dead_exchange')
            ->queue($queue, false)
            ->queue('dead_queue')
            ->dead('dead_exchange', $queue, 'dead_queue_key')
            ->bind('dead_exchange', 'dead_queue', 'dead_queue_key')
            ->bind($exchange, $queue, 'queue_key')
            ->qttl($queue, 10000, 1);

        for ($i = 1; $i <= 10; $i++) {
            $id = uniqid();
            $mq->message($i)
                ->setCustom('msg_id', $id)
                ->push($exchange, 'queue_key');
            echo $id . "\n";
        }
        $mq->publish();
    }

    public function consumer1()
    {
        $mq = new RabbitMQ();
        $mq->queue('dead_queue');
        var_dump($mq->getQueue('dead_queue'));
        $mq->callback($this, 'msg', ['a' => '234234']);
        $mq->pull('dead_queue');
    }

    public function consumer2()
    {
        $mq = new RabbitMQ();
        $mq->queue('test_queue');
//        var_dump($mq->getQueue('test_queue'));die;
        $mq->callback($this, 'msg', ['a' => '234234']);
        $mq->pull('test_queue');
    }


    public static function msg($message, $param)
    {
        $msg_headers = $message->get('application_headers')->getNativeData();
        echo $msg_headers['msg_id'] . ":ok \n";
        if ($message->body === 'quit') {
            //踢出消费者
            $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
        }
    }
}

if (!empty($argv[1])) {
    $res = new mq();
    if ($argv[1] == 'push') {
        $res->push();
    }
    if ($argv[1] == 'dead') {
        $mq = new RabbitMQ();
        $res->consumer1();
    }
    if ($argv[1] == 'pull') {
        $res->consumer2();
    }
}