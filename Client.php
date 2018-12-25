<?php
/**
 * Created by PhpStorm.
 * User: ytlmi
 * Date: 2018/12/20
 * Time: 10:31
 */

class Client
{
    public static $client;

    public static $server = '127.0.0.1';

    public static $port = 8888;

    public static $pid_master = '';

    public static $pid_loop = '';

    public static $pid_input = '';

    /**
     * 运行
     */
    public static function run()
    {
        Client::connect();
        Client::startLoop();
        self::startInput();
    }

    /**
     * 监控子进程
     */
    public static function monitor()
    {
        while(true){
            $pid_loop = pcntl_waitpid(self::$pid_loop, $status, WNOHANG);
            if ($pid_loop > 0 && !posix_kill($pid_loop, 0)){
                posix_kill(self::$pid_input, SIGKILL);
                self::run();
            }
        }
    }

    /**
     * 建立连接
     */
    public static function connect()
    {
        static::$pid_master = posix_getpid();
        if (static::$client = @stream_socket_client('tcp://' . static::$server . ':' . static::$port)) {
            echo "connect success!" . PHP_EOL;
        }else{
            echo "server not available!" . PHP_EOL;
            sleep(1);
            echo "retrying..." . PHP_EOL;
            static::connect();
        }
    }

    /**
     * 开始事件循环
     * @return bool
     */
    public static function startLoop()
    {
        $pid = pcntl_fork();
        if ($pid < 0){
            echo "fork failed\n";
            return false;
        }elseif ($pid > 0){
            echo "loop pid:".posix_getpid(). PHP_EOL;
            self::$pid_loop = $pid;
        }else{
            $w = null;
            $e = null;
            while (1) {
                $read = [static::$client];
                if (stream_select($read, $w, $e, 2)) {
                    foreach ($read as $name => $socket) {
                        static::readMessage($socket);
                    }
                }
            }
        }
        return true;
    }

    /**
     * 读取数据
     * @param $socket
     */
    public static function readMessage($socket)
    {
        $message = @fread($socket, 8192);
        if (!is_resource($socket) || empty($message)) {
            echo "disconnected from server.".PHP_EOL;
            echo "reconnecting...".PHP_EOL;
            exit();
        }
        if(!empty($message)){
            echo "got message: $message" . PHP_EOL;
        }
    }

    /**
     * 处理用户输入
     * @return bool
     */
    public static function startInput()
    {
        $pid = pcntl_fork();
        if ($pid < 0){
            echo "fork failed\n";
            return false;
        }elseif ($pid > 0){
            echo "input pid:".posix_getpid(). PHP_EOL;
            self::$pid_input = $pid;
        }else{
            self::readInput();
        }
        return true;
    }

    /**
     * 接收用户输入
     */
    public static function readInput()
    {
        pcntl_signal_dispatch();
        sleep(0.5);
        //提示输入
        fwrite(STDOUT, "请输入:");
        //获取用户输入数据
        $result = trim(fgets(STDIN));
        $pid = posix_getpid();
        echo "And I'm process $pid!\n";
        static::sendMessage($result);
        sleep(0.5);
        static::readInput();
    }

    /**
     * 发送消息
     * @param $message
     */
    public static function sendMessage($message)
    {
        fwrite(static::$client, $message);
    }
}
Client::run();
Client::monitor();

