<?php

namespace RIO;

class Logs {

	const INFO = 'info';

	const ERROR = 'error';

	public $base;

	public $logs = array();


	public function __construct(Base $base) {

		$this->base = $base;

	}


	public function log($message, $type = self::INFO) {

		$this->logs[$type][] = $message;

		$this->save();

	}


	public function info($message) {

		$this->log($message, self::INFO);

	}

	public function error($message) {

		$this->log($message, self::ERROR);

	}


	public function save() {

		$this->base->updateSetting('logs', $this->logs);

	}


	public function get() {

		$logs = $this->base->getSetting('logs');

		if (!is_array($logs)) {
			$logs = array();
		}

		return $logs;

	}

}