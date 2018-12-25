<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-10-30
 */
namespace Uniondrug\Consul;

/**
 * Shell Commands
 * @package Uniondrug\Consul
 */
class Shell
{
    const CONSUL_API_HOST = "CONSUL_API_HOST";
    const CONSUL_API_PORT = "CONSUL_API_PORT";
    const CONSUL_SERVICE_HOST = "CONSUL_SERVICE_HOST";
    const CONSUL_SERVICE_PORT = "CONSUL_SERVICE_PORT";

    /**
     * 从环境变量读取API地址
     * @return false|string
     */
    public function getApiHost()
    {
        $host = $this->runShell('echo $'.self::CONSUL_API_HOST);
        if ($host !== '') {
            return $host;
        }
        return false;
    }

    /**
     * 从环境变量读取API端口
     * @return false|string
     */
    public function getApiPort()
    {
        $host = $this->runShell('echo $'.self::CONSUL_API_PORT);
        if ($host !== '') {
            return $host;
        }
        return false;
    }

    /**
     * 从环境变量读取Service地址
     * @return false|string
     */
    public function getServiceHost()
    {
        $host = $this->runShell('echo $'.self::CONSUL_SERVICE_HOST);
        if ($host !== '') {
            return $host;
        }
        return false;
    }

    /**
     * 从环境变量读取Service端口
     * @return false|string
     */
    public function getServicePort()
    {
        $host = $this->runShell('echo $'.self::CONSUL_SERVICE_PORT);
        if ($host !== '') {
            return $host;
        }
        return false;
    }

    /**
     * 从网卡读取IP地址
     * @return false|string
     */
    public function getMachineIp()
    {
        $text = $this->runShell("ifconfig");
        $text = "\n".preg_replace("/\n\s+/", "\t", $text)."\n";
        if (preg_match("/\n(e[a-zA-Z0-9]+0)\s*([^\n]+)/", $text, $eth)) {
            if (preg_match("/inet\s+[^\d]*(\d+\.\d+\.\d+\.\d+)/", $eth[2], $m)) {
                return $m[1];
            }
        }
        return false;
    }

    /**
     * 执行Shell脚本
     * @param string $command
     * @return string
     */
    private function runShell(string $command)
    {
        if (function_exists('shell_exec')) {
            $response = shell_exec($command);
            return trim($response);
        }
        return '';
    }
}
