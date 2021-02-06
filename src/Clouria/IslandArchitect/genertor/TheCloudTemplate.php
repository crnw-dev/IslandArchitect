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
namespace Clouria\IslandArchitect\genertor;

use room17\SkyBlock\island\generator\IslandGenerator;

use function unserialize;
use function set_exception_handler;
use function restore_exception_handler;

class TheCloudTemplate extends IslandGenerator {

	public function generateChunk(int $chunkX, int $chunkZ) : void {
		set_exception_handler(function(\Throwable $err) : void {
			throw new TheCloudTemplateException($err->getMessage());
		});

		$chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
		$data = new IslandData(unserialize($this->getSettings()['preset']));
		$data->locateChunk($chunk);
		foreach ($data->getBlockData() as $blockdata) $chunk->setBlock($blockdata[0], $blockdata[1], $blockdata[2], $blockdata[3], $blockdata[4]);
		restore_exception_handler();
	}

}
