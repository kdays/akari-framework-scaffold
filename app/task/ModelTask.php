<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 17/2/23
 * Time: 下午3:52
 */

namespace Builder\task;


use Akari\action\BaseTask;
use Akari\system\conn\DBConnection;
use Akari\system\ioc\DI;
use Akari\utility\FileHelper;
use Akari\utility\TextHelper;
use Builder\lib\DbConn;

class ModelTask extends BaseTask {
    
    private $appPath;
    
    private $appNs;

    public function initAction() {
        $this->output->write("<info>即将开始模型初始化</info>");
        
        $this->output->write("请输入命名空间");
        $ns = $this->input->getInput();
        if (empty($ns)) {
            die;
        }
        
        $this->appNs = $ns;
        
        $this->output->write("保存目录");
        $appPath = $this->input->getInput();
        if (empty($appPath)) $appPath = "../app/model/db/";
        
        if (!is_dir($appPath)) {
            die("没有找到目录");
        }
        
        $appPath = realpath($appPath);
        $this->output->write("<info>目标目录是: $appPath</info>");
        $this->appPath = $appPath;
        
        $this->output->write("请输入数据库前缀,设置后创建的模型文件名会忽略前缀创建.");
        $tablePrefix = $this->input->getInput();
        
        $dbResult = DbConn::get($this->input, $this->output);
        
        $connection = new DBConnection([
            'dsn' => 'mysql:host='. $dbResult['host'] .';port=3306;dbname='. $dbResult['name'],
            'username' => $dbResult['user'],
            'password' => $dbResult['pass'],
            'options' => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'set names "'. $dbResult['charset'] . '"'
            ]
        ]);
        
        $tables = $connection->fetch("show tables", [], \PDO::FETCH_COLUMN);
        $this->output->write("<info>共获得表: ". count($tables). "张，继续么?</info>");
        
        if ($this->input->getInput() != 'Y') {
            die;
        }
        
        foreach ($tables as $table) {
            $this->createModel($connection, $table, $tablePrefix, $dbResult['name']);
        }
        
        $this->output->write("是否生成SQLMap?");
        if ($this->input->getInput() == 'Y') {
            // 生成sqlMap
            $this->output->write("保存目录");
            $appPath = $this->input->getInput();
            if (empty($appPath)) $appPath = "../app/sql/";

            if (!is_dir($appPath)) {
                die("没有找到目录");
            }

            $appPath = realpath($appPath);
            $this->output->write("<info>目标目录是: $appPath</info>");
            $this->appPath = $appPath;

            foreach ($tables as $table) {
                $this->createSQLMap($connection, $table, $tablePrefix, $dbResult['name']);
            }
        }
    }
    
    protected function createSQLMap(DBConnection $connection, $tableName, $tablePrefix, $dbName) {
        // 按照基本方法生成 insert.record每个必有 其余的按照情况生成
        $this->output->write("<info>数据库: $tableName</info>");
        $modelName = TextHelper::camelCase( str_replace($tablePrefix, '', $tableName) );
        $modelName[0] = strtoupper($modelName[0]);

        $lists = $connection->fetch(
            "select * from information_schema.`columns` where `TABLE_SCHEMA`= :dbName and `TABLE_NAME` = :tableName",
            [
                'dbName' => $dbName,
                'tableName' => $tableName
            ]
        );

        $tablePK = false;
        $columnMap = [];

        foreach ($lists as $item) {
            $column = $item['COLUMN_NAME'];
            $columnMap[ $column ] = TextHelper::camelCase($column);

            if ($item['COLUMN_KEY'] == 'PRI')   $tablePK = $column;
        }
        
        $result = "[";
        if ($tablePK) {
            $result .= <<<'EOT'

        'row.by_%PK' => [
            'sql' => "SELECT * FROM `@TABLE_NAME` WHERE `%PK` = :id",
            'required' => ['id']
        ],
                
        'update.by_%PK' => [
            'sql' => "UPDATE `@TABLE_NAME` SET #keys WHERE `%PK` = :id",
            'required' => ['id']
        ],
                
        'delete.by_%PK' => [
             'sql' => "DELETE FROM `@TABLE_NAME` WHERE `%PK` = :id ",
             'required' => ['id']
        ],

EOT;
        }
        
        $result .= <<<'EOT'
        
        'insert.record' => [
            'sql' => "INSERT INTO `@TABLE_NAME` SET #keys"
        ],
            
        'count.all' => [
             'sql' => 'SELECT count(*) FROM `@TABLE_NAME`'
        ],

EOT;
        
        $result .= "]";
        
        $tpl = file_get_contents(BASE_DIR. "/app/tpl/db/SQLMap.tpl");
        $file = str_replace(
            ['%table', '%ns', '%data', '%name', '%PK'],
            [$tableName, $this->appNs, $result, $modelName, $tablePK],
            $tpl
        );

        FileHelper::write($this->appPath. DIRECTORY_SEPARATOR. $modelName. ".php", $file);
        
        $daoPath = str_replace("sql", "dao", $this->appPath);
        $this->createDAO($columnMap, $tablePK, $tableName, $daoPath, $modelName);
    }
    
    protected function createDAO($columnMap, $tablePK, $tableName, $saveAs, $modelName) {
        
        $result = '';
        
        if ($tablePK) {
            $fnPK = $tablePK;
            $fnPK[0] = strtoupper($fnPK[0]);
            
            $fn = <<<'EOT'
    public function getBy%fnPK($%PK) {
        return $this->builder->execute("row.by_%PK", ['id' => $%PK]);
    }
    
    public function deleteBy%fnPK($%PK) {
        return $this->builder->execute("delete.by_%PK", ['id' => $%PK]);
    }
    
    public function updateBy%fnPK(%model $model) {
        $r = $model->toArray(['%PK']);
        return $this->builder->execute("update.by_%PK", [
            '@keys' => array_keys($r),
            '%PK' => $model['%PK']
        ] + $r);
    }
EOT
            ; 
           
            $fn = str_replace(['%PK', '%fnPK', '%model'], [$tablePK, $fnPK, $modelName], $fn);
          
            $result .= $fn;
        }
        
        $fn = <<<'EOT'
        
    public function insertRecord(%model $model) {
        $r = $model->toArray(%except);
        return $this->builder->execute("insert.record", [
            '@keys' => array_keys($r)
        ] + $r);
    }
    
    public function countAll() {
        return $this->builder->execute("count.all", []);
    }

EOT
            ;
        
        $except = '[]';
        if ($tablePK) {
            $except = '["'. $tablePK . '"]';
        }

        $fn = str_replace(['%model', '%except'], [$modelName, $except], $fn);
        $result .= $fn;
        
        $p = file_get_contents(BASE_DIR. "/app/stub/db/DAO.stub");
        $p = str_replace(['%ns', '%name', '%func'], [$this->appNs, $modelName, $result], $p);
        
        FileHelper::write($saveAs. DIRECTORY_SEPARATOR. $modelName. "DAO.php", $p);
    }
    
    protected function createModel(DBConnection $connection, $tableName, $tablePrefix, $dbName) {
        $this->output->write("<info>数据库: $tableName</info>");
        $modelName = TextHelper::camelCase( str_replace($tablePrefix, '', $tableName) );
        $modelName[0] = strtoupper($modelName[0]);

        $lists = $connection->fetch(
            "select * from information_schema.`columns` where `TABLE_SCHEMA`= :dbName and `TABLE_NAME` = :tableName", 
            [
                'dbName' => $dbName,
                'tableName' => $tableName
            ]
        );
        
        $tablePK = false;
        $columnMap = [];
        $colDefaultValues = [];
        
        foreach ($lists as $item) {
            $column = $item['COLUMN_NAME'];
            $columnMap[ $column ] = TextHelper::camelCase($column);
            
            $defaultValue = $item['COLUMN_DEFAULT'];
            if ($defaultValue == 'NULL') $defaultValue = NULL;
            if (is_numeric($defaultValue))  $defaultValue = (int) $defaultValue;

            $colDefaultValues[ $column ] = $defaultValue;
            
            if ($item['COLUMN_KEY'] == 'PRI')   $tablePK = $column;
        }
        
        // 创建基础的SET & GET
        $modelFunc = '';
        $baseFunc = <<<'EOT'

public function get%fnCol() {
    return $this->%col;
}

public function set%fnCol($%col) {
    $this->%col = $%col;
    return $this;
}

EOT;
        
        foreach ($columnMap as $sourceName => $targetName) {
            $fnCol = $targetName;
            $fnCol[0] = strtoupper($fnCol[0]);
            
            $modelFunc .= str_replace(
                ['%fnCol', '%col'], 
                [$fnCol, $targetName], 
                $baseFunc
            );
        }
        
        // 创建column
        $colText = '';
        foreach ($columnMap as $targetName) {
            $colText .= "\t". 'protected $'. $targetName. ";\n\n";
        }
        
        // 创建columnMap
        $columnMapText = var_export($columnMap, TRUE);
        
        $tpl = file_get_contents(BASE_DIR. "/app/stub/db/Model.stub");
        $file = str_replace(
            ['%columns', '%table', '%map', '%name', '%ns', '%func'],
            [$colText, $tableName, $columnMapText, $modelName, $this->appNs, $modelFunc],
            $tpl
        );
        
        FileHelper::write($this->appPath. DIRECTORY_SEPARATOR. $modelName. ".php", $file);
    }
    
}