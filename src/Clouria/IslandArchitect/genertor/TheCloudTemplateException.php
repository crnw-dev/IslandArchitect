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

class CloudGenertorException extends \RuntimeException {

	protected const ERROR_MESSAGE_MAPPING = [
		self::ISLAND_DATA_VERSION_MISMATCH => 'Island data version mistmatch, is it from a newer version of this plugin?'
	]
	protected const DEFAULT_ERROR_MESSAGE = 'Unknown error occurred';

	/**
	 * @var mixed[]
	 */
	private $data = null;

	/**
	 * @var int
	 */
	private $reason = null;

	public function __construct(array $data, ?int $reason) {
		$this->data = $data;
		$this->reason = $reason;
		parent::__construct(self::ISLAND_DATA_VERSION_MISMATCH[$reason] ?? self::DEFAULT_ERROR_MESSAGE);
	}

	/**
	 * Set the island data passed into the cloud genertor
	 * @return mixed[]
	 */
	public function getIslandData() : ?array {
		return $this->data;
	}

	/**
	 * @return The ID of reason why this error is thrown
	 */
	public function getReason() : ?int {
		return $this->reason;
	}
}
