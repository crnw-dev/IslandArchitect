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

class TheCloudTemplate extends IslandGenerator {

	public function generateChunk(int $chunkX, int $chunkZ) : void {
		$chunk = $this->level->getChunk($chunkX, $chunkZ);
        $chunk->setGenerated();
		$data = new IslandData($this->getSettings()['preset']);
		$data->locateChunk($chunk);
		foreach ($data->getBlockData() as $blockdata) $chunk->setBlock($blockdata->getX(), $blockdata->getY(), $blockdata->getZ(), $blockdata->getBlock(), $blockdata->getMeta());
	}

}
