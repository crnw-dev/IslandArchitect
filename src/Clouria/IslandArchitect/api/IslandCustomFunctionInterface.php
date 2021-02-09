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

interface IslandCustomFunctionInterfacr {

	public static function getName() : string;

	public static function prepare() : IslandCustomFunction;

	/**
	 * @param mixed[] $parameters
	 */
	public function setParameters(array $parameters);

	/**
	 * @param string[] $previous_functions
	 */
	public function setPreviousFunctions(array $previous_functions);

	/**
	 * @return array<int, int>|mixed[]|null
	 */
	public function handle() : ?array;

}