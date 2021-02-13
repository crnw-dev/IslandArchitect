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

declare(strict_types=1);
namespace Clouria\IslandArchitect\api;

use pocketmine\{
	math\Vector3
};

use room17\SkyBlock\island\generator\IslandGenerator;

use function unserialize;
use function set_exception_handler;
use function restore_exception_handler;
use function is_file;
use function file_get_contents;
use function json_decode;

class TemplateIslandGenerator extends IslandGenerator {

	public function generateChunk(int $chunkX, int $chunkZ) : void {
		set_exception_handler(function(\Throwable $err) : void {
			throw new TheCloudTemplateException($err->getMessage());
		});

		$chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
        static $data = null;
        if (!isset($data)) {
	        $path = unserialize($this->getSettings()['preset'][0]);
			if (!is_file($path)) throw new \RuntimeException('Island data file (' . $path . ') is missing');
			$data = json_decode(file_get_contents($path), true);
			if ($path === false) throw new \RuntimeException('Failed to parse island data file');
		}
		$data->locateChunk($chunk);
		foreach ($data->getBlockData() as $blockdata) $chunk->setBlock($blockdata[0], $blockdata[1], $blockdata[2], $blockdata[3], $blockdata[4]);
		restore_exception_handler();
	}

	public function getName() : string {
		return 'TemplateIslandGenerator';
	}

	public static function getWorldSpawn() : Vector3 {
    	return new Vector3(0, 0, 0);
	}

    public static function getChestPosition() : Vector3 {
    	return new Vector3(0, 0, 0);
    }

}
