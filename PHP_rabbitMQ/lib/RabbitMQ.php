<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQ
{
    private $config = [];
    private $channel;
    public $exchanges = [];
    public $queues = [];
    public $binds = [];
    public $vhost;
    private $expiration;
    private $arguments = [];
    public $doSomething = [];
    public $param;
    public $queue_count = [];
    private $build = false;
    private $content_type = 'text/plain';

    /**
     * RabbitMQ constructor.
     * @param array $config
     * @param string $vhost
     * @author kongfanlong
     */
    public function __construct($vhost = '/', $config = [])
    {
        if ($config) {
            $this->config = $config;
        } else {
            $this->config['host'] = '127.0.0.1';
            $this->config['port'] = 5672;
            $this->config['user'] = 'guest';
            $this->config['password'] = 'guest';
        }
        $this->vhost = $vhost;
        $connection = new AMQPStreamConnection($this->config['host'], $this->config['port'], $this->config['user'], $this->config['password'], $this->vhost);
        $this->channel = $connection->channel();
        register_shutdown_function([$this, 'close'], $this->channel, $connection);
    }

    /**
     * 创建交换机
     * @param $exchange
     * @return $this
     * @author kongfanlong
     */
    public function exchange($exchange, $durable = false, $auto_delete = false, $type = AMQPExchangeType::DIRECT)
    {
        $this->param['exchange'][$exchange] = [
            'exchange' => $exchange,
            'type' => $type,
            'passive' => false,
            'durable' => $durable,
            'auto_delete' => $auto_delete
        ];
        $this->exchanges[] = $exchange;
        return $this;
    }

    /**
     * 创建队列
     * @param string $exchange
     * @param string $queue
     * @param bool $time
     * @param int $type 默认1消息过期时间,1消息，2队列，3交换机
     * @param bool $delay
     * @return $this
     * @author kongfanlong
     */
    public function queue($queue, $durable = false, $auto_delete = false, $passive = false, $exclusive = false, $nowwait = false)
    {
        $this->param['queue'][$queue] = [
            'queue' => $queue,
            'passive' => $passive,
            'durable' => $durable,
            'exclusive' => $exclusive,
            'auto_delete' => $auto_delete,
            'nowait' => $nowwait
        ];
        $this->queues[] = $queue;
        return $this;
    }

    /**
     * 设置过期时间
     * @param int $time
     * @param int $type 默认1消息过期时间
     * @return $this
     * @author kongfanlong
     */
    public function qttl($queue, $time, $type = 1)
    {
        $mod = "";
        switch ($type) {
            case 1:
                $mod = "x-message-ttl";  //通过队列属性设置消息过期时间
                break;
            case 2:
                $mod = "x-expires"; //设置队列过期时间
                break;
        }
        if ($mod) {
            $this->param['qttl'][$queue] = [
                'queue' => $queue,
                'value' => $time,
                'mod' => $mod
            ];
        }
        return $this;
    }

    /**
     * 进入队列的信息
     * @param $msg
     * @param bool $time
     * @param int $delivery_mode
     * @return $this
     * @author kongfanlong
     */
    public function message($msg, $time = false, $delivery_mode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        $array = [
            'content_type' => 'text/plain',
            'delivery_mode' => $delivery_mode,
        ];
        if ($time) {
            $array['expiration'] = $time;
        }
        $this->param['msg']['message'] = $msg;
        $this->param['msg']['param'] = $array;
        return $this;
    }

    /**
     * 设置死信队列
     * @param $exchange
     * @param $queue
     * @return $this
     * @author kongfanlong
     */
    public function dead($exchange, $queue, $route_key = '')
    {
        $this->param['dead'][$queue] = [
            'exchange' => $exchange,
            'queue' => $queue,
            'mods' => [
                [
                    'mod' => 'x-dead-letter-exchange',
                    'value' => $exchange
                ]
            ]
        ];
        if ($route_key) {
            $this->param['dead'][$queue]['mods'][] = [
                'mod' => 'x-dead-letter-routing-key',
                'value' => $route_key
            ];
        }
        return $this;
    }

    /**
     * 队列绑定交换机
     * @param string $exchange
     * @param string $queue
     * @param string $route_key
     * @return $this
     * @author kongfanlong
     */
    public function bind($exchange, $queue, $route_key = "")
    {
        $this->param['bind'][$queue] = [
            'queue' => $queue,
            'exchange' => $exchange,
            'route_key' => $route_key
        ];
        $this->binds[$queue] = $exchange;
        return $this;
    }

    /**
     * 设置队列最大长度
     * @param $queue
     * @param $length
     * @return  $this
     * @author kongfanlong
     */
    public function queueLength($queue, $length)
    {
        $this->param['queue_length'][$queue] = [
            'queue' => $queue,
            'mod' => 'x-max-length',
            'value' => $length
        ];
        return $this;
    }


    /**
     * 设置队列最大占用空间
     * @param $queue
     * @param $length
     * @return $this
     * @author kongfanlong
     */
    public function queueLengthBytes($queue, $length)
    {
        $this->param['length_bytes'][$queue] = [
            'queue' => $queue,
            'mod' => 'x-max-length-bytes',
            'value' => $length
        ];
        return $this;
    }

    /**
     * 清空队列
     * @param $queue
     * @return int  返回执行成功数量
     */
    public function queueDurge($queue)
    {
        return $this->channel->queue_purge($queue);
    }

    /**
     * 删除队列
     * @param $queue
     * @return mixed|null
     */
    public function queueDelete($queue)
    {
        return $this->channel->queue_delete($queue);
    }

    /**
     * 设置消息content_type
     * @param $content_type
     * @return $this
     */
    public function setContentType($content_type)
    {
        $this->content_type = $content_type;
        return $this;
    }

    /**
     * 推送信息入队列 批处理方式
     * @param $msg
     * @param $exchange
     * @param string $route_key
     * @author kongfanlong
     */
    public function push($exchange, $route_key = "")
    {
        $message = $this->buildMsg();
        if ($message == []) {
            return ['code' => '0', 'message' => 'message is empty'];
        }
        if ($res = $this->check()) {
            return $res;
        }

        if ($this->build === false) {
            $this->buildMq();
        }
        return $this->channel->basic_publish($message, $exchange, $route_key);
    }

    /**
     * 批处理
     * @param $msg
     * @param $exchange
     * @param $route_key
     * @param bool $time
     * @param int $delivery_mode
     */
    public function batchPublish($msg, $exchange, $route_key, $time = false, $delivery_mode = AMQPMessage::DELIVERY_MODE_NON_PERSISTENT)
    {
        if ($this->build === false) {
            $this->buildMq();
        }
        $message = $this->buildMsg();
        return $this;
    }

    /**
     * 批处理入队方法
     */
    public function publish()
    {
        return $this->channel->publish_batch();
    }


    /**
     * mq构造
     */
    private function buildMq()
    {
        $this->buildExchange();
        $this->buildQueue();
        $this->buildBind();
        $this->buildBasicQos();
        $this->build = true;
    }

    /**
     * 设置自定义信息
     * @param $message_id
     */
    public function setCustom($key, $value)
    {
        $this->param['header'][$key] = $value;
        return $this;
    }

    /**
     * 构建交换机
     * @author kongfanlong
     */
    public function buildExchange()
    {
        if (!empty($this->param['exchange'])) {
            foreach ($this->param['exchange'] as $key => $value) {
                $this->channel->exchange_declare($value['exchange'], $value['type'], $value['passive'], $value['durable'], $value['auto_delete']);
            }
        }
    }

    /**
     * 构建队列
     * @author kongfanlong
     */
    private function buildQueue()
    {
        if (!empty($this->param['queue'])) {
            foreach ($this->param['queue'] as $key => $value) {
                $arguments = $this->buildArguments($value['queue']);
                $this->queue_count[$value['queue']] = $this->channel->queue_declare($value['queue'], $value['passive'], $value['durable'], $value['exclusive'], $value['auto_delete'], $value['nowait'], $arguments);
            }
        }
    }

    /**
     * 获取队列信息 长度、消费者数
     * @param $queue
     * @return array|int|mixed|null
     */
    public function getQueue($queue)
    {
        try {
            $res = $this->channel->queue_declare($queue, true);
        } catch (Exception $e) {
            $res = $e->getCode();
        }
        return $res;
    }

    /**
     * 构建队列参数属性
     * @param $queue
     * @return array AMQPTable
     * @author kongfanlong
     */
    private function buildArguments($queue)
    {
        $arguments = new AMQPTable();
        //队列过期时间
        if (!empty($this->param['qttl'][$queue])) {
            $arguments->set($this->param['qttl'][$queue]['mod'], $this->param['qttl'][$queue]['value']);
        }
        //死信交换机关联队列
        if (!empty($this->param['dead'][$queue])) {
            foreach ($this->param['dead'][$queue]['mods'] as $value) {
                $arguments->set($value['mod'], $value['value']);
            }
        }
        //队列最大长度
        if (!empty($this->param['queue_length'][$queue])) {
            $arguments->set($this->param['queue_length'][$queue]['mod'], $this->param['queue_length'][$queue]['value']);
        }
        //队列最大占用空间
        if (!empty($this->param['length_bytes'][$queue])) {
            $arguments->set($this->param['length_bytes'][$queue]['mod'], $this->param['length_bytes'][$queue]['value']);
        }
        return $arguments;
    }

    /**
     * 构建队列与交换机绑定关系
     * @author kongfanlong
     */
    private function buildBind()
    {
        if (!empty($this->param['bind'])) {
            foreach ($this->param['bind'] as $value) {
                $this->channel->queue_bind($value['queue'], $value['exchange'], $value['route_key']);
            }
        }
    }

    /**
     * 构建入队信息
     * @return array|AMQPMessage
     * @author kongfanlong
     */
    private function buildMsg()
    {
        $message = [];
        if (!empty($this->param['msg'])) {
            $message = new AMQPMessage($this->param['msg']['message'], $this->param['msg']['param']);
            if (!empty($this->param['header'])) {
                $headers = new AMQPTable($this->param['header']);
                $message->set('application_headers', $headers);
            }
        }
        return $message;
    }

    /**
     * 消费者处理数量限制
     * @return mixed
     */
    private function buildBasicQos()
    {
        if (!empty($this->param['basic_qos'])) {
            return $this->channel->basic_qos($this->param['basic_qos']['prefetch_size'], $this->param['basic_qos']['prefetch_count'], $this->param['basic_qos']['a_global']);
        }
    }

    /**
     * 查找是否有未使用的交换机或队列
     * @author kongfanlong
     */
    private function check()
    {
        $array = [];
        $bind = $this->binds;
        $exchange = $this->exchanges;
        $queue = $this->queues;
        if (!$bind) {
            return ['code' => '0', 'message' => '未创建绑定关系'];
        }
        if (!$exchange) {
            return ['code' => '0', 'message' => '没有交换机'];
        }
        if (!$queue) {
            return ['code' => '0', 'message' => '没有队列'];
        }
        foreach ($bind as $key => $value) {
            $array['queue'][$key] = $key;
            $array['exchange'][$value] = $value;
        }
        $result_e = array_diff($exchange, $array['exchange']);
        $result_q = array_diff($queue, $array['queue']);
        if ($result_e != []) {
            return ['code' => '0', 'message' => '交换机未被使用', 'data' => $result_e];
        }
        if ($result_q != []) {
            return ['code' => '0', 'message' => '队列未被使用', 'data' => $result_q];
        }
    }

    /**
     * 消费者获取信息
     * @param string $queue
     * @param string $consumer_tag
     * @author kongfanlong
     */
    public function pull($queue, $consumer_tag = '', $no_ack = false, $nowait = false)
    {
        $this->channel->basic_consume($queue, $consumer_tag, false, $no_ack, false, $nowait, [$this, 'processMessage']);
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * 消费者消费数量限制，自动应答模式下无效
     * @param $prefetch_count
     * @return $this
     */
    public function basic_qos($prefetch_count, $a_global = null)
    {
        $this->param['basic_qos'] = [
            'prefetch_size' => null,
            'prefetch_count' => $prefetch_count,
            'a_global' => $a_global
        ];
        return $this;
    }

    /**
     * 消息确认
     * @param $message
     * @author kongfanlong
     */
    public function processMessage($message)
    {
        $this->doSomething['message'] = $message;
        if ($this->doSomething) {
            if (!empty($this->doSomething['class'])) {
                call_user_func_array([$this->doSomething['class'], $this->doSomething['function']], ['message' => $message, 'param' => $this->doSomething['param']]);
            } else {
                call_user_func_array($this->doSomething['function'], ['message' => $message, 'param' => $this->doSomething['param']]);
            }
        }
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * 注入方法
     * @param $class
     * @param $function
     * @param $param
     * @author kongfanlong
     * @return $this
     */
    public function callback($class, $function, $param)
    {
        $this->doSomething['class'] = $class;
        $this->doSomething['function'] = $function;
        $this->doSomething['param'] = $param;
        return $this;
    }

    /**
     * 关闭连接
     * @param $channel
     * @param $connection
     * @author kongfanlong
     */
    public function close($channel, $connection)
    {
        $channel->close();
        $connection->close();
    }
}