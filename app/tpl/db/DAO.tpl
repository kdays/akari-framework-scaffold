<?php
namespace %ns\dao;

use %ns\model\db\%name;
        
class %name extends BaseDAO {
    
    public function __construct() {
        $this->initConnection();
        $this->initBuilder(%ns\sql\%name::given());
    }

%func

}