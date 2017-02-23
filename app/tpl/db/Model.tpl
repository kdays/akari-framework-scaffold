<?php
namespace %ns\model\db;
        
class %name extends BaseModel {
    
%columns

%func

    public function tableName() {
        return '%table';
    }
    
    public function columnMap() {
        return %map;
    }
    
}