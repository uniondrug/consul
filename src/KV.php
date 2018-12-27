<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-10-30
 */
namespace Uniondrug\Consul;

use Composer\Script\Event;
use GuzzleHttp\Client;

/**
 * 操作Consul K/V
 * @package Uniondrug\Consul
 */
class KV
{
    private static $basePath;
    private static $branchesMapping = [
        'master' => 'production'
    ];
    /**
     * Consul服务地址
     * 例如: http://127.0.0.1:8500, 初始化时从
     * 环境变量CONSUL_CLIENT中提取
     * @var string
     */
    private static $consulHost = '';
    private static $uses = [];

    /**
     * 初始化配置文件
     * @param Event $e
     */
    public static function initConfig($e)
    {
        $client = shell_exec('echo $CONSUL_CLIENT');
        $client = trim($client);
        if ($client === '') {
            $e->getIO()->writeError("[ERROR] can not read environment variable 'CONSUL_CLIENT' of os");
            return;
        }
        self::$basePath = getcwd();
        self::$consulHost = $client;
        include(self::$basePath.'/vendor/autoload.php');
        // 1. 读取分支名称
        $branch = self::getBranchName();
        if ($branch === false) {
            $e->getIO()->writeError("[ERROR] can not read branch name");
            return;
        }
        // 2. 计算分支与环境名称
        $environment = isset(self::$branchesMapping[$branch]) ? self::$branchesMapping[$branch] : $branch;
        $e->getIO()->write("[INFO] current branch is '{$branch}' and environment is '{$environment}'");
        // 3. 扫描默认配置
        $path = getcwd().'/config';
        $e->getIO()->write("[INFO] scan config directory '{$path}'.");
        $config = self::scanConfig($e, $path, $environment);
        // 4. 状态设置
        $key = null;
        if (isset($config['app'], $config['app']['appName'])) {
            $key = "{$config['app']['appName']}/config";
        }
        if ($key === null) {
            $e->getIO()->writeError("[ERROR] can not recognize key name from config files.");
            return;
        }
        // 4. 合并Consul
        self::mergeConsul($e, $config, $key);
        // 5. 递归子项
        self::recursiveConsul($e, $config);
        // 6. 导出/Export
        self::exportConfigJson($config, $key);
        self::exportConfigFile($config);
    }

    /**
     * 导出到config.php
     * @param array $data
     */
    private static function exportConfigFile(array $data)
    {
        // 1. basic info
        $contents = "<?php\n";
        $contents .= "/**\n";
        $contents .= " * created by composer\n";
        $contents .= " * date: ".date('r')."\n";
        $contents .= " */\n";
        // 2. use info
        if (count(self::$uses) > 0) {
            $contents .= "\n";
            foreach (self::$uses as $key) {
                $contents .= "use {$key};\n";
            }
        }
        // 3. return array
        $contents .= "\n";
        $contents .= self::exportConfigFileData($data);
        self::replaceIpAddr($contents);
        file_put_contents(self::$basePath.'/tmp/config.php', $contents);
    }

    /**
     * @param      $data
     * @param bool $ret
     * @return string
     */
    public static function exportConfigFileData(& $data, $ret = true, $tbl = "")
    {
        $res = $ret === true ? 'return ' : '';
        $res .= '[';
        $comma = "\n";
        foreach ($data as $key => $value) {
            $res .= $comma.$tbl."\t";
            $comma = ", \n";
            if (!is_numeric($key)) {
                $res .= '"'.$key.'" => ';
            }
            if (is_array($value)) {
                $res .= self::exportConfigFileData($value, false, "{$tbl}\t");
            } else {
                $t = strtolower(gettype($value));
                switch ($t) {
                    case 'bool' :
                    case 'boolean' :
                        $res .= $value ? 'true' : 'false';
                        break;
                    case 'int' :
                    case 'integer' :
                    case 'float' :
                    case 'double' :
                        $res .= $value;
                        break;
                    case 'string' :
                        $res .= '"'.addslashes(($value)).'"';
                        break;
                    case 'null' :
                        $res .= 'null';
                        break;
                    default :
                        $res .= '""';
                        break;
                }
            }
        }
        $res .= "\n{$tbl}]";
        return $res.($ret === true ? ';' : '');
    }

    /**
     * 导出到config.json
     * @param array $data
     */
    private static function exportConfigJson(array $data, $key)
    {
        $contents = json_encode([$key => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        self::replaceIpAddr($contents);
        file_put_contents(self::$basePath.'/tmp/config.json', $contents);
    }

    /**
     * 替换IP
     * @param string $contents
     */
    private static function replaceIpAddr(& $contents)
    {
        echo "[INFO] replace ip addr\n";
        $contents = preg_replace_callback("/([a-z]+\d):(\d+)\"/", function($a){
            return KV::parseNetwork($a[1]).':'.$a[2].'"';
        }, $contents);
    }

    /**
     * 合并配置
     * @param Event $e
     * @param array $config
     */
    private static function mergeConsul($e, & $config, string $key)
    {
        $e->getIO()->write("[INFO] merge config with consul K/V by '{$key}'");
        $json = self::readKeyValue($e, $key);
        if ($json !== false) {
            $data = json_decode($json, true);
            $config = array_replace_recursive($config, $data);
        }
    }

    /**
     * 合并子项
     * @param Event $e
     * @param mixed $data
     */
    private static function recursiveConsul($e, & $data)
    {
        if (is_array($data)) {
            foreach ($data as & $value) {
                self::recursiveConsul($e, $value);
            }
        } else if (is_string($data) && preg_match("/kv:[\/]+(\S+)/", $data, $m)) {
            $temp = self::readKeyValue($e, $m[1]);
            if ($temp !== false) {
                $arrs = json_decode($temp, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $arrs;
                } else {
                    $data = $temp;
                }
            }
        }
    }

    /**
     * 请求Consul K/V获取配置处段
     * @param Event  $e
     * @param string $key
     * @return mixed
     */
    private static function readKeyValue($e, string $key)
    {
        $url = sprintf("%s/v1/key/%s", self::$consulHost, $key);
        try {
            $e->getIO()->write("[DEBUG] - send request to consul {$url}");
            $http = new Client();
            $content = $http->request("GET", $url)->getBody()->getContents();
            $data = json_decode($content, true);
            if (is_array($data) && isset($data[0], $data[0]['Value'])) {
                $buffer = base64_decode($data[0]['Value']);
                $e->getIO()->write("[DEBUG] - response from consul");
                return $buffer;
            }
            $e->getIO()->writeError("[ERROR] - unknown response contents");
        } catch(\Throwable $ex) {
            $e->getIO()->writeError("[ERROR] - {$ex->getMessage()}");
        }
        return false;
    }

    /**
     * 匹配IP地址
     * @param $host
     * @return string
     */
    public static function parseNetwork($host)
    {
        $ip = self::parseNetworkIpadd($host);
        $ip || $ip = self::parseNetworkIfconfig($host);
        return $ip ?: '127.0.0.1';
    }

    /**
     * @param string $host
     * @return false|string
     */
    public static function parseNetworkIfconfig(string $host)
    {
        // 1. read all
        $cmd = 'ifconfig';
        $str = shell_exec($cmd);
        $str = preg_replace("/\n\s+/", " ", $str);
        // 2. filter host
        if (preg_match("/({$host}[^\n]+)/", $str, $m) === 0) {
            return false;
        }
        // 3. filter ip addr
        //    inet addr:10.168.74.190
        //    inet 192.168.10.116
        if (preg_match("/inet\s+[a-z:]*(\d+\.\d+\.\d+\.\d+)/", $m[1], $z) > 0) {
            return $z[1];
        }
        return false;
    }

    /**
     * 解析IP地址
     * 以阿里云为例
     * 1. eth0, 内网IP
     * 2. eth1, 公网IP
     * @param string $host
     * @return false|string
     */
    public static function parseNetworkIpadd(string $host)
    {
        $cmd = "ip -o -4 addr list '{$host}' | head -n1 | awk '{print \$4}' | cut -d/ -f1";
        $addr = shell_exec($cmd);
        $addr = trim($addr);
        if ($addr !== "" && preg_match("/^\d+\.\d+\.\d+\.\d+$/", $addr) > 0) {
            return $addr;
        }
        return false;
    }

    /**
     * 扫描配置文件
     * @param Event  $e
     * @param string $path
     * @param string $environment
     * @return array
     */
    private static function scanConfig($e, $path, $environment)
    {
        $config = [];
        if (!is_dir($path)) {
            $e->getIO()->writeError("[ERROR] the path '{$path}' is not valid directory");
            return $config;
        }
        $d = dir($path);
        $e->getIO()->write("[INFO] scan config directory '{$path}'.");
        while (false !== ($f = $d->read())) {
            // 1. not php file
            if (!preg_match("/(\S+)\.php/i", $f, $m)) {
                continue;
            }
            // 2. include php file
            $data = include($path.'/'.$f);
            if (!is_array($data)) {
                $e->getIO()->writeError("[ERROR] the file '{$f}' is not valid config format");
                continue;
            }
            // 3. parse
            $e->getIO()->write("[DEBUG] load {$m[1]} configuration from '{$path}/{$f}'.");
            $defaultData = isset($data['default']) && is_array($data['default']) ? $data['default'] : [];
            $environmentData = isset($data[$environment]) && is_array($data[$environment]) ? $data[$environment] : [];
            $config[$m[1]] = array_replace_recursive($defaultData, $environmentData);
            // 4. uses
            $contents = file_get_contents($path.'/'.$f);
            if ($contents !== false) {
                if (preg_match_all("/\n\s*use\s+([^;]+);/", $contents, $u) > 0) {
                    foreach ($u[1] as $us) {
                        $us = preg_replace("/\s+/", " ", $us);
                        if (!in_array($us, self::$uses)) {
                            self::$uses[] = $us;
                        }
                    }
                }
            }
        }
        return $config;
    }

    /**
     * 读取分支名称
     * @return false|string
     */
    private static function getBranchName()
    {
        $branch = shell_exec("git branch -a | grep '\*'");
        if (preg_match("/\*\s+(\S+)/", $branch, $m) > 0) {
            return $m[1];
        }
        return false;
    }
}
