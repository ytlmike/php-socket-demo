<?php
class Server
{
    static $readers = [];
    static $sockets = [];
    static $handlers = [];
    static $server;

    /**
     * 创建socket服务器
     */
    public static function startServer()
    {
        static::$server = stream_socket_server('tcp://0.0.0.0:8888', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN); //创建socket服务器
        if (!static::$server || !is_resource(static::$server)){
            die("failed to start server.".PHP_EOL);
        }
        stream_set_blocking(static::$server, 0); //设置为非阻塞模式
        static::bindReader(static::$server, 'main_server', ['Server', 'acceptConnection']); //有连接请求时调用static::acceptConnection()
    }

    /**
     * 保存socket句柄 并绑定事件处理方法
     * @param $socket //socket句柄
     * @param $name //socket名
     * @param $handler //事件处理方法
     */
    public static function bindReader($socket, $name, $handler)
    {
        static::$sockets[$name] = $socket;
        static::$handlers[$name] = $handler;
    }

    /**
     * 开始事件轮询
     */
    public static function startLoop()
    {
        $w = null;
        $e = null;
        while (1) {
            $read = static::$sockets;
            if (stream_select($read, $w, $e, 2)) {
                foreach ($read as $name => $socket) {
                    call_user_func(static::$handlers[$name], $name);
                }
            }
        }
    }

    /**
     * 接受连接
     */
    public static function acceptConnection()
    {
        $client = @stream_socket_accept(static::$server, empty(static::$sockets) ? -1 : 0, $peer); //客户端连接socket句柄
        $response = "success!!".PHP_EOL;
        fwrite($client, $response, strlen($response));
        if (!$client) return;
        echo "$peer has connected to server.".PHP_EOL;
        static::bindReader($client, $peer, ['Server', 'readMessage']); //保存、绑定
    }

    /**
     * 读取数据
     * @param $socketName
     */
    public static function readMessage($socketName)
    {
        $socket = static::$sockets[$socketName];
        $message = @fread($socket, 8192);

        if (!is_resource($socket) || !$message) {
            static::closeConnection($socketName);
        }else{
            self::handleMessage($socketName, $message);
        }
    }

    /**
     * 数据处理
     * @param $socketName
     * @param $message
     */
    public static function handleMessage($socketName, $message)
    {
        echo "$socketName send a message: $message".PHP_EOL;
        foreach (static::$sockets as $socketName => $socket) {
            $response = "$socketName said: $message".PHP_EOL;
            if ($socketName != 'main_server') fwrite($socket, $response);
        }
    }

    public static function closeConnection($socketName)
    {
        echo "$socketName disconnected from server".PHP_EOL;
        unset(static::$sockets[$socketName]);
        unset(static::$handlers[$socketName]);
    }
}

Server::startServer();
Server::startLoop();
