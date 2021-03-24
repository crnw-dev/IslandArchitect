<?php
/*
		
		  _____     _                 _          
		  \_   \___| | __ _ _ __   __| |         
		   / /\/ __| |/ _` | '_ \ / _` |         
		/\/ /_ \__ \ | (_| | | | | (_| |         
		\____/ |___/_|\__,_|_| |_|\__,_|         
		                                         
		   _            _     _ _            _   
		  /_\  _ __ ___| |__ (_) |_ ___  ___| |_ 
		 //_\\| '__/ __| '_ \| | __/ _ \/ __| __|
		/  _  \ | | (__| | | | | ||  __/ (__| |_ 
		\_/ \_/_|  \___|_| |_|_|\__\___|\___|\__|
		                                         
		@ClouriaNetwork | Apache License 2.0
														*/

namespace Clouria\IslandArchitect\customized;

trait CustomizableClassTrait {

    /**
     * @var string
     */
    private static $class;

    final public static function setClass(string $class) : bool {
        if (!is_a($class, self::class, true)) throw new \InvalidArgumentException('The input class must be a subclass of ' . self::class . '');
        if (isset(self::$class) and is_a($class, self::$class, true)) return false;
        self::$class = $class;
        return true;
    }

    /**
     * @return class-string<self>
     */
    final public static function getClass() : string {
        return self::$class ?? self::class;
    }

}