<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-10-30
 */
namespace Uniondrug\Consul;

use Phalcon\Di;
use Uniondrug\Console\Command as ConsoleCommand;
use Uniondrug\Framework\Container;

/**
 * Class Consul
 * @package Uniondrug\Consul
 */
abstract class Command extends ConsoleCommand
{
    /**
     * Consul约定
     * @var string
     */
    protected $signature = "consul
                            {action=help : 操作类型, 可选register、deregister、view、health}
                            {--host= : Consul API/接口地址 }
                            {--port= : Consul API/接口端口}
                            {--service-host= : Consul Service/服务地址}
                            {--service-port= : Consul Service/服务端口}
                            {--template= : Consul Service/模板配置}";
    /**
     * @var string
     */
    protected $description = 'Consul管理器';

    /**
     * @return mixed
     */
    public function handle()
    {
        // 0. prepare
        $consul = new Consul();
        // 1. --host
        $host = $this->input->getOption('host');
        $host && $consul->setHost($host);
        // 2. --port
        $port = $this->input->getOption('port');
        $port && $consul->setPort($port);
        // 3. --service-host
        $serviceHost = $this->input->getOption('service-host');
        $serviceHost && $consul->setServiceHost($serviceHost);
        // 4. --service-port
        $servicePort = $this->input->getOption('service-port');
        $servicePort && $consul->setServicePort($servicePort);
        // 5. --template
        $template = $this->input->getOption('template');
        if ($template[0] !== '/') {
            /**
             * @var Container $di ;
             */
            $di = Di::getDefault();
            $template = $di->appPath().'/../'.$template;
        }
        $template && $consul->setTemplate($template);
        // 6. command
        $cmd = $this->input->getArgument('action');
        switch ($cmd) {
            case 'deregister' :
            case 'register' :
            case 'view' :
            case 'health' :
                $consul->run($cmd);
                break;
            default :
                $this->runError();
                break;
        }
    }

    private function runError()
    {
        echo "Usage: php console consul [OPTIONS] COMMAND\n";
        echo "Commands: \n";
        echo "    register   - add service to consul.\n";
        echo "    deregister - remove service from consul.\n";
        echo "    view       - view registed service detail.\n";
        echo "    health     - run health check for service.\n";
        echo "Options: \n";
        echo "    --host=<ip|domain>\n";
        echo "    --port=<port>\n";
        echo "    --service-host=<ip|domain>\n";
        echo "    --service-port=<port>\n";
        echo "    --template=<file>\n";
    }

    //    /**
    //     *
    //     */
    //    private function beforeRun()
    //    {
    //        $this->showCommands();
    //    }
    //
    //    /**
    //     * 删除注册Service
    //     */
    //    private function runDeregister()
    //    {
    //    }
    //
    //    /**
    //     * Service健康检查
    //     */
    //    private function runHealth()
    //    {
    //    }
    //
    //    private function runHelp()
    //    {
    //    }
    //
    //    /**
    //     * 添加注册Service
    //     */
    //    private function runRegister()
    //    {
    //    }
    //
    //    /**
    //     * 查看注册Service
    //     */
    //    private function runView()
    //    {
    //    }
    //
    //    private function showCommands()
    //    {
    //        $class = Command::class;
    //        $reflect = new \ReflectionClass($this);
    //        echo "命令: 支持如下命令列表\n";
    //        foreach ($reflect->getMethods() as $m) {
    //            if ($m->class === $class && $m->name !== 'runHelp' && preg_match("/^run([A-Z][a-zA-Z0-9]+)/", $m->name, $p)) {
    //                $x = '';
    //                if (preg_match("/^\/\*([^@]+)/", $m->getDocComment(), $d) > 0) {
    //                    foreach (explode("\n", $d[1]) as $line) {
    //                        $line = trim($line);
    //                        if ($line !== '') {
    //                            $line = preg_replace("/[\*]+[\/]*\s*/", '', $line);
    //                            $line = trim($line);
    //                            if ($line !== '') {
    //                                $x .= $line;
    //                            }
    //                        }
    //                    }
    //                }
    //                $x === '' || $x = ' - '.$x;
    //                $p[1] = sprintf('%-14s', lcfirst($p[1]));
    //                echo '      '.lcfirst($p[1]).$x."\n";
    //            }
    //        }
    //    }
}