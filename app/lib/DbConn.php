<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 17/2/23
 * Time: 下午3:42
 */

namespace Builder\lib;


use Akari\system\console\Input;
use Akari\system\console\Output;

class DbConn {
    
    public static function get(Input $input, Output $output) {
        
        if (file_exists(BASE_DIR. "/common.php")) {
            $commonCfg = include(BASE_DIR. "/common.php");
            if (!empty($commonCfg['user']) && !empty($commonCfg['host']) && !empty($commonCfg['name'])) {
                $output->write("<question>发现存在惯例配置(common.php)被配置, 是否按照惯例配置运行? (N=否 Y=是)</question>");
                if ($input->getInput() != 'N') {
                    return $commonCfg;
                }
            }
        }

        $output->write("<question>MySQL host ip (默认: 127.0.0.1)</question>");
        $databaseHost = $input->getInput();
        if (empty($databaseHost))   $databaseHost = '127.0.0.1';

        $output->write("<question>MySQL 数据库名 (默认: app)</question>");
        $databaseName = $input->getInput();
        if (empty($databaseName))   $databaseName = 'app';

        $output->write("<question>MySQL 用户名 (默认: root)</question>");
        $databaseUser = $input->getInput();
        if (empty($databaseUser))   $databaseUser = 'root';

        $output->write("<question>MySQL 密码</question>");
        $databasePass = $input->getInput();

        $output->write("<question>MySQL 数据库编码 (默认 utf8mb4)</question>");
        $databaseCharset = $input->getInput();
        if (empty($databaseCharset))   $databaseCharset = 'utf8mb4';
        
        return [
            'host' => $databaseHost,
            'user' => $databaseUser,
            'pass' => $databasePass,
            'name' => $databaseName,
            'charset' => $databaseCharset
        ];
    }

}