<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 17/2/23
 * Time: 下午3:39
 */

namespace Builder\task;


use Akari\action\BaseTask;
use Akari\utility\FileHelper;
use Akari\utility\TextHelper;
use Builder\lib\DbConn;

class InitTask extends BaseTask {
    
    private $tarDir = null;

    public function runAction() {
        $this->output->write("<info>即将开始Akari Framework项目的初始化工作</info>");
        $this->output->write("<question>请输入你要创建的应用名字:</question>");
        $appName = $this->input->getInput();
        if (empty($appName)) {
            $this->output->write("你输入的名字不合法");
        }

        $this->output->write("<question>请输入你要创建的应用的Namespace的名字(如Rin, nico):</question>");

        $ns = $this->input->getInput();
        if (empty($ns) || $ns == 'Akari') {
            $this->output->write("你输入的命名空间不合法");
        }

        $this->output->write("<question>请输入你要创建的项目应用文件夹路径 (默认: ../app/)</question>");
        $appPath = $this->input->getInput();
        if (empty($appPath)) $appPath = "../app/";

        if (!is_dir($appPath)) {
            mkdir($appPath);
        }
        $appPath = realpath($appPath);
        $this->tarDir = $appPath;
        
        $this->output->write("<info>你选择的目录是:" . $appPath . "\n确定的话请输入Y继续</info>");
        $next = $this->input->getInput();

        if ($next != 'Y') {
            die("用户终止了");
        }

        $baseArr = ['ns' => $ns, 'appName' => $appName];
        
        $appDirNames = [
            'action',
            'config',
            'dao',
            'exception',
            'language',
            'lib',
            'model',
            'model/db',
            'model/req',
            'service',
            'sql',
            'task',
            'template',
            'template/layout',
            'template/widget',
            'template/block',
            'template/view',
            'trigger',
            'widget'
        ];

        foreach ($appDirNames as $name) {
            $fDir = $appPath . DIRECTORY_SEPARATOR . $name;

            if (!is_dir($fDir)) {
                $this->output->write("<success>创建文件夹: $name</success>");
                mkdir($appPath . DIRECTORY_SEPARATOR . $name);
            }
        }

        $this->output->write("<info>开始配置文件创建</info>");
        $this->output->write("<question>应用是否要使用MYSQL数据库? (默认=Y / 否=N)</question>");
    
        $allowUseDb = $this->input->getInput();
        if ($allowUseDb != 'N') {
            $dbResult = DbConn::get($this->input, $this->output);
    
            $databaseCfg = <<<'EOT'
    public $database = [
            'dsn' => 'mysql:host=%host;port=3306;dbname=%name',
            'username' => "%user",
            'password' => "%pass",
            'options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'set names "%charset"'
            ]
        ];
EOT;
    
            foreach ($dbResult as $k => $v) {
                $databaseCfg = str_replace("%" . $k, $v, $databaseCfg);
            }
        } else {
            $databaseCfg = '';
        }
        
        // 创建基础Cookie的秘钥和通用秘钥
        $commonEncryptStr = TextHelper::randomStr(16);
        $cookieEncryptStr = TextHelper::randomStr(16);
        
        $this->output->write("<info>加密已自动配置: 公共 - $commonEncryptStr</info>");
        $this->output->write("<info>加密已自动配置: Cookie加密 - $cookieEncryptStr</info>");

        // 配置处理
        $cfgOpts = [
            'defaultKey' => $commonEncryptStr,
            'cookieKey' => $cookieEncryptStr,
            'database' => $databaseCfg
        ];
        $this->output->write("<success>配置文件完成</success>");
        $this->copyTpl("BaseConfig", $baseArr + $cfgOpts, 'config/Config.php');    
        
        $this->copyTpl('BaseAction', $baseArr, 'action/BaseFrontAction.php');
        $this->copyTpl('BaseDatabaseModel', $baseArr, 'model/db/BaseModel.php');
        $this->copyTpl('BaseDAO', $baseArr, 'dao/BaseDAO.php');
        $this->copyTpl('BaseService', $baseArr, 'service/BaseService.php');
        $this->copyTpl('DefaultCtl', $baseArr, 'action/IndexAction.php');   
        
        $this->output->write("<question>请输入Web目录地址 (默认:../web/)</question>");
        $webPath = $this->input->getInput();
        if (empty($webPath)) $webPath = "../web/";

        if (!is_dir($webPath)) {
            mkdir($webPath);
        }
        $webPath = realpath($webPath);
        $this->tarDir = $webPath;
        
        $this->copyTpl("Boot", $baseArr, "index.php");
    }

    
    private function copyTpl($tpl, $arr, $target) {
        $source = BASE_DIR . "/app/stub/init/". $tpl . ".stub";
        if (!file_exists($source)) {
            die("STUB ". $source . " NOT FOUND");
        }
        
        $tpl = file_get_contents($source);
        foreach ($arr as $k => $v) {
            $tpl = str_replace("%". $k, $v, $tpl);
        }
        FileHelper::write($this->tarDir . DIRECTORY_SEPARATOR. $target, $tpl);
        
        $this->output->write("<success>创建: $target</success>");
    }
}
