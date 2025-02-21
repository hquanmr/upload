<?php
namespace Upload\Services;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel\Client as ChannelClient;

class WsServer extends Worker
{
    protected $taskConnections = []; // 存储任务ID与连接的映射

    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->onWorkerStart = [$this, 'initChannel'];
        $this->onMessage = [$this, 'onWsMessage'];
        $this->onClose = [$this, 'onWsClose'];
    }

    public function initChannel()
    {
        // 初始化 Channel 客户端
        ChannelClient::connect('127.0.0.1', 2206);

        // 订阅任务进度频道
        ChannelClient::on('task.progress', function($data) {
            if (isset($this->taskConnections[$data['taskId']])) {
                $this->taskConnections[$data['taskId']]->send(json_encode($data));
            }
        });
    }

    public function onWsMessage(TcpConnection $connection, $data)
    {
        $message = json_decode($data, true);
        if (!isset($message['taskId'])) {
            return $connection->send(json_encode(['error' => 'Invalid message format']));
        }

        // 将任务ID与连接关联
        $this->taskConnections[$message['taskId']] = $connection;

        $connection->send(json_encode([
            'type' => 'subscribed',
            'taskId' => $message['taskId']
        ]));
    }

    public function onWsClose(TcpConnection $connection)
    {
        // 清理断开的连接
        $taskId = array_search($connection, $this->taskConnections);
        if ($taskId !== false) {
            unset($this->taskConnections[$taskId]);
        }
    }
}