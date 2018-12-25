<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-10-30
 */
namespace Uniondrug\Consul;

use GuzzleHttp\Client;

/**
 * Class Consul
 * @package Uniondrug\Consul
 */
class Consul
{
    /**
     * 默认ConsulAPI地址, 按优先级
     * 1. 命令行参数/--host=<ip|domain>
     * 2. 环境变量/export CONSUL_HOST=<ip|domain>
     * @var string
     */
    private $host = null;
    /**
     * 默认ConsulAPI端口, 按优先级
     * 1. 命令行参数/--port=<ip|domain>
     * 2. 环境变量/export CONSUL_HOST=<ip|domain>
     * 3. 默认8500端口
     * @var int
     */
    private $port = 0;
    private static $defaultPort = 8500;
    /**
     * Service地址, 按优先级
     * 1. 命令行选项/--service-host=<ip|domain>
     * 2. 模板配置/Address字段
     * 3. 环境变量/export CONSUL_SERVICE_HOST=<ip|domain>
     * 4. 网卡IP
     * @var string
     */
    private $serviceHost = null;
    /**
     * Service端口, 按优先级
     * 1. 命令行选项/--service-port=<port>
     * 2. 模板配置/Port字段
     * 4. 环境变量/export CONSUL_SERVICE_PORT=<port>
     * @var int
     */
    private $servicePort = 0;
    private static $defaultServicePort = 80;
    /**
     * 默认模板文件, 按优先级
     * 当使用容器时, 源码全部加入到/uniondrug/app目录下, 即
     * 读取其下的默认文件consul.json
     * 1. 命令行选项/--template=<file>
     * 2. 本属性默认值
     * @var string
     */
    private $template = '/uniondrug/app/consul.json';
    /**
     * Service模板配置
     * @var array
     */
    private $data = [];
    /**
     * @var Shell
     */
    private $shell;

    public function __construct()
    {
        $this->shell = new Shell();
    }

    public function run(string $cmd)
    {
        // 1. initialize
        $this->initTemplate();
        $this->initApiHost();
        $this->initApiPort();
        $this->initServiceHost();
        $this->initServicePort();
        $this->initCompleted();
        // 2. prepared
        $uri = null;
        $mtd = null;
        $data = null;
        // 2. methods
        switch ($cmd) {
            case 'register' :
                $mtd = 'PUT';
                $uri = 'agent/service/register';
                $data = $this->data;
                break;
            case 'deregister' :
                $mtd = 'PUT';
                $uri = "agent/service/deregister/{$this->data['ID']}";
                break;
            case 'view' :
                $mtd = 'GET';
                $uri = "agent/service/{$this->data['ID']}";
                break;
            case 'health' :
                $this->sendHealth();
                break;
        }
        if ($mtd && $uri) {
            $this->sendRequest($mtd, $uri, $data);
        }
    }

    /**
     * 设置ConsulAPI地址
     * @param string $host
     * @return $this
     */
    public function setHost(string $host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * 设置ConsulAPI端口
     * @param int $port
     * @return $this
     */
    public function setPort(int $port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * 设置Service地址
     * @param string $host
     * @return $this
     */
    public function setServiceHost(string $host)
    {
        $this->serviceHost = $host;
        return $this;
    }

    /**
     * 设置Service端口
     * @param int $port
     * @return $this
     */
    public function setServicePort(int $port)
    {
        $this->servicePort = $port;
        return $this;
    }

    /**
     * 设置Service模板
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * 计算API地址
     * @throws \Exception
     */
    private function initApiHost()
    {
        // 1. command line
        if ($this->host) {
            return;
        }
        // 2. environment
        $host = $this->shell->getApiHost();
        if ($host !== false) {
            $this->host = $host;
            return;
        }
        // 3. not setted
        throw new \Exception("Consul API地址未设置");
    }

    /**
     * 计算API端口
     */
    private function initApiPort()
    {
        // 1. command line
        if ($this->port) {
            return;
        }
        // 2. environment or default
        $port = $this->shell->getApiPort();
        $port || $port = self::$defaultPort;
        $this->port = $port;
    }

    /**
     * 完在初始化
     */
    private function initCompleted()
    {
        $this->data['ID'] = "consul";
            //"{$this->data['Name']}-{$this->serviceHost}-{$this->servicePort}";
    }

    /**
     * 初始化Service地址
     * @throws \Exception
     */
    private function initServiceHost()
    {
        // 1. command line
        if ($this->serviceHost) {
            return;
        }
        // 2. template
        if (isset($this->data['Address']) && $this->data['Address'] !== '') {
            $this->serviceHost = $this->data['Address'];
            return;
        }
        // 3. environment
        $host = $this->shell->getServiceHost();
        if ($host) {
            $this->serviceHost = $host;
            $this->data['Address'] = $host;
            return;
        }
        // 4. machine ip
        $host = $this->shell->getMachineIp();
        if ($host) {
            $this->serviceHost = $host;
            $this->data['Address'] = $host;
            return;
        }
        // 5. error
        throw new \Exception("计算Service地址失败");
    }

    /**
     * 初始化Service端口
     */
    private function initServicePort()
    {
        // 1. command line
        if ($this->servicePort) {
            return;
        }
        // 2. template
        if (isset($this->data['Port']) && is_numeric($this->data['Port']) && $this->data['Port'] > 0) {
            $this->servicePort = $this->data['Port'];
            return;
        }
        // 3. environment
        $port = $this->shell->getServicePort();
        if ($port && $port > 0) {
            $this->servicePort = (int) $port;
            $this->data['Port'] = $this->servicePort;
            return;
        }
        // 4. default
        $this->data['Port'] = self::$defaultServicePort;
        $this->servicePort = $this->data['Port'];
    }

    /**
     * 计算模板
     */
    private function initTemplate()
    {
        // 1. exists or not
        if (!$this->template || !file_exists($this->template)) {
            throw new \Exception("Service模板文件'{$this->template}'不存在或已被删除.");
        }
        // 2. contents
        $text = file_get_contents($this->template);
        $text = trim($text);
        if ($text === '') {
            throw new \Exception("Service模板内容为空.");
        }
        // 3. parse contents
        $data = json_decode($text, true);
        if (!is_array($data) || !isset($data['Name'])) {
            throw new \Exception("Service模板不是有效的JSON格式.");
        }
        // 4. update
        $this->data = $data;
    }

    /**
     * 健康检查
     */
    private function sendHealth()
    {
    }

    /**
     * 发送HTTP请求
     * @param string     $method
     * @param string     $uri
     * @param array|null $data
     * @throws \Throwable
     */
    private function sendRequest(string $method, string $uri, array $data = null)
    {
        // 1. URL
        $url = "http://{$this->host}:{$this->port}/v1/{$uri}";
        echo "HTTP/1.1 {$method} {$url}\n";
        // 2. Data
        $opts = [];
        if (is_array($data)) {
            $opts['json'] = $data;
            echo "         Data - ".json_encode($data, true)."\n";
        }
        try {
            // 3. Send
            $client = new Client();
            $stream = $client->request($method, $url, $opts);
            echo "         Status Code - {$stream->getStatusCode()}\n";
            echo "         Status Text - {$stream->getReasonPhrase()}\n";
            echo "         Responsed - {$stream->getBody()->getContents()}\n";
        } catch(\Throwable $e) {
            echo "         Error Code - {$e->getCode()}\n";
            echo "         Error Reason - {$e->getMessage()}\n";
        }
    }
}
