<?php
namespace %ns\dao;

use Akari\system\conn\BaseSQLMap;
use Akari\system\conn\DBConnection;
use Akari\system\conn\DBConnFactory;
use Akari\system\conn\SQLMapBuilder;
use Akari\system\Plugin;

abstract class BaseDAO extends Plugin {

    /** @var  SQLMapBuilder $builder */
    protected $builder;
    
    /** @var  DBConnection $connection */
    protected $connection;
    protected static $m;

    protected function initConnection($cfg = 'default') {
        if ($this->connection) {
return $this->connection;
}

$conn = DBConnFactory::get($cfg);
return $this->connection = $conn;
}

public function initBuilder(BaseSQLMap $SQLMap) {
if (!$this->builder) {
$this->builder = new SQLMapBuilder($SQLMap, $this->connection);
}

return $this->builder;
}

/**
* 单例公共调用，不应在action中调用本方法
* @return static
*/
public static function getInstance() {
$class = get_called_class();
if (!isset(self::$m[$class])) {
self::$m[ $class ] = new $class;
}

return self::$m[ $class ];
}

}