<?php
class config{

    public $config = [];
    protected static $ins=null;
    protected function __construct(){
        if(is_file(ROOT_PATH.'/.env')){
            $file = ROOT_PATH.'/.env';
            $config = file_get_contents($file);
            if(preg_match_all('/([A-z][A-z0-9_]+)\s*\=\s*([^\n]*)\n/is', $config, $matches, PREG_SET_ORDER)){
                foreach($matches as $item){
                    if(isset($item[1])){
                        $this->config[$item[1]] = isset($item[2])?$item[2]:'';
                    }
                }
            }
        }
    }

    public static function getInstance(){
        if(self::$ins instanceof self){
            return self::$ins;
        }
        self::$ins = new self();
        return self::$ins;
    }

    public static function get($name, $default = null){
        if(isset(self::$ins->config[$name])){
            return self::$ins->config[$name];
        }
        return $default;
    }
}


config::getInstance();