<?php
namespace %ns\action;

class IndexAction extends BaseFrontAction {

    public function indexAction() {
        return self::_genTEXTResult('Hello, Akari Framework');
    }

}