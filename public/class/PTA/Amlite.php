<?php

# FIXME: Excessive - getNotifications() would do 

namespace PTA;

use DateTime;
use DateTimeZone;
use PDO;
use Am_Lite;
use \Michelf\Markdown;

class Amlite extends Am_Lite {    
    
    protected static $_instance = null;
    
    protected function __construct() {
        return parent::__construct();
    }
    
    static public function getInstance()
    {
        if (is_null(self::$_instance))
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    public function select($sql) {
        $result =  $this->query($sql);
        $return = array();
        foreach($result as $row){
            $return[] = $row;
        }
        return $return;
    }
}
