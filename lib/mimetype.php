<?php
namespace OCA\SwanViewer;

use \OC\Core\Command\Maintenance\Mimetype\UpdateDB;
use \OC\Core\Command\Maintenance\Mimetype\UpdateJS;
use \Symfony\Component\Console\Input\ArrayInput;
use \Symfony\Component\Console\Output\NullOutput;

class Mimetype {
	private $appName;
	private $updateJS;
	private $updateDB;
	private $aliases;
	private $mapping;
	private $config;
	private $aliasesFile;
	private $mappingFile;

	public function __construct($appName) {
		$this->appName = $appName;
		$this->aliasesFile = \OC::$SERVERROOT."/config/mimetypealiases.json";
		$this->mappingFile = \OC::$SERVERROOT."/config/mimetypemapping.json";
		$this->config = \OC::$server->getConfig();
		$this->aliases = Array();
		$this->mapping = Array();
		$detector = \OC::$server->getMimeTypeDetector();
		$loader = \OC::$server->getMimeTypeLoader();
		$this->updateJS = new UpdateJS($detector);
		$this->updateDB = new UpdateDB($detector, $loader);
	}

	public function addAlias($mimetype, $icon) {
		$this->aliases[$mimetype] = $icon;
	}

	public function addMapping($ext, $mimetype) {
		$this->mapping[$ext] = Array($mimetype);
	}

	public function update() {
		if ($this->config->getSystemValue('mimetypes.autoupdate.lock', '') === '') {
			try {
				$this->config->setSystemValue('mimetypes.autoupdate.lock', 'updating');
				$this->updateJS();
				$this->updateDB();
				$this->config->setSystemValue('mimetypes.autoupdate.lock', '');
			} catch (\Exception $e) {
				$this->config->setSystemValue('mimetypes.autoupdate.lock', '');
				throw $e;
			}
		}
	}

	/**
	 * Triggers an update of /core/js/mimetypes.json
	 */
	private function updateJS() {
		$lockKey = "mimetypealiases.autoupdate.{$this->appName}";
		if (count($this->aliases) > 0) {	
			if ($this->config->getSystemValue($lockKey, '') === '') {
				$aliases = $this->mergeJSON($this->aliasesFile, $this->aliases);
				if ($aliases !== false) {
					$this->updateJS->run(new ArrayInput(Array()), new NullOutput());
					$this->config->setSystemValue($lockKey, 'complete');
				}
			}
		}
	}

	/**
	 * Triggers an update of mimetypes table in databases
	 */
	private function updateDB() {
		// file has to be writeable by webserver, so using config directory
		$lockKey = "mimetypemapping.autoupdate.{$this->appName}";
		
		if (count($this->mapping) > 0) {
			if ($this->config->getSystemValue($lockKey, '') === '') {
				$mappings = $this->mergeJSON($this->mappingFile, $this->mapping);

				if ($mappings !== false) {
					$this->updateDB->run(new ArrayInput(Array()), new NullOutput());
					$this->config->setSystemValue($lockKey, 'complete');
				}
			}
		}
	}
	
	private function mergeJSON($file, $data=Array()) {
		$mergedData = null;
		if (file_exists($file)) {
			$mergedData = json_decode(file_get_contents($file),true);
		}
		if (is_null($mergedData)) {
			$mergedData = Array();
		}
		\OCP\Util::writeLog('swan', print_r($mergedData, true), \OCP\Util::DEBUG);
		$mergedData = array_merge($mergedData, $data);
		if (!is_null($mergedData) && !empty($mergedData)) {
			$result = file_put_contents($file, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
		} else {
			$result = false;
		}
		return $result;
	}
}
