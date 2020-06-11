<?php


class RIO_Logs {

	const INFO = 'info';

	const ERROR = 'error';

	public $base;

	public $logs = array();


	public function __construct(RIO_Base $base) {

		$this->base = $base;

	}


	public function log($message, $type = self::INFO) {

		$this->logs[$type][] = $message;

	}


	public function info($message) {

		$this->logs[self::INFO][] = $message;

	}

	public function error($message) {

		$this->logs[self::ERROR][] = $message;

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