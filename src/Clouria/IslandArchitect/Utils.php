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
		
        ██╗  ██╗    ██╗  ██╗    
        ██║  ██║    ██║ ██╔╝    光   時   LIBERATE   
        ███████║    █████╔╝     復   代   HONG
        ██╔══██║    ██╔═██╗     香   革   KONG
        ██║  ██║    ██║  ██╗    港   命   
        ╚═╝  ╚═╝    ╚═╝  ╚═╝
                    
														*/
declare(strict_types=1);

namespace Clouria\IslandArchitect;

use pocketmine\utils\Binary;
use function fread;
use function fseek;
use function strlen;
use const SEEK_CUR;

final class Utils {

    private function __construct() { }

    public static function readAndSeek($stream, int $length) : string {
        $data = fread($stream, $length);
        fseek($stream, $length, SEEK_CUR);
        return $data;
    }

    public static function overflowBytes(string $data) : int {
        switch (strlen($data)) {
            case 1:
                $data = Binary::readSignedByte($data);
                $max = 127;
                break;
            case 2:
                $data = Binary::readSignedLShort($data);
                $max = 32767;
                break;
            case 4:
                $data = Binary::readLInt($data);
                $max = 2147483647;
                break;
            case 8:
                throw new \InvalidArgumentException("Cannot overflow longs");
            default:
                throw new \InvalidArgumentException("Cannot overflow bytes with the length of " . strlen($data));
        }
        return $data < 0 ? $data + $max + 1 - $data : $data;
    }

}