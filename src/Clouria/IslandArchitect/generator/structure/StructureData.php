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

namespace Clouria\IslandArchitect\generator\structure;

use pocketmine\utils\Binary;
use function fread;
use function fseek;
use function is_resource;
use const SEEK_CUR;

class StructureData {

    public const FORMAT_VERSION = 0;

    public $stream;

    /**
     * @throws StructureParseException
     */
    public function decode() {
        if (!is_resource($this->stream)) return;
        fseek($this->stream, 0);
        $header = fread($this->stream, $offset = 3);
        // Format version (1), sub version (1), extended blocks (1)
        fseek($this->stream, $offset, SEEK_CUR);

        $ver = Binary::readByte($header[0]);

        if ($ver > self::FORMAT_VERSION) $this->panicParse("Unsupported structure format version " . $ver, false);
        $extendedbk = Binary::readByte($header[2]) === 1;
    }

    /**
     * @throws StructureParseException
     */
    protected function panicParse(string $err, bool $corrupted = true) : void {
        if ($corrupted) $err .= ", is the structure data file occupied?";
        throw new StructureParseException($this->stream, "", $err);
    }

}