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
	private $aliasesFile;
	private $mappingFile;

	public function __construct($appName) {
		$this->appName = $appName;
		$this->aliasesFile = \OC::$SERVERROOT."/config/mimetypealiases.json";
		$this->mappingFile = \OC::$SERVERROOT."/config/mimetypemapping.json";
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
		$lockFile = \OC::$SERVERROOT."/config/mimetypes.updating";
		try {
			touch($lockFile);
			$this->updateJS();
			$this->updateDB();
			unlink($lockFile);
		} catch (\Exception $e) {
			if (file_exists($lockFile)) {
				unlink($lockfile);
			}
			throw $e;
		}
	}

	/**
	 * Triggers an update of /core/js/mimetypes.json
	 */
	private function updateJS() {
		// file has to be writeable by webserver, so using config directory
		$lockFile = \OC::$SERVERROOT."/config/autoupdate.mimetypealiases.{$this->appName}.done";
		if (count($this->aliases) > 0) {	
			if (!file_exists($lockFile)) {
				$aliases = $this->mergeJSON($this->aliasesFile, $this->aliases);
				if ($aliases === false) {
					$this->updateJS->run(new ArrayInput(Array()), new NullOutput());
					touch($lockFile);
				}
			}
		}
	}

	/**
	 * Triggers an update of mimetypes table in databases
	 */
	private function updateDB() {
		// file has to be writeable by webserver, so using config directory
		$lockFile = \OC::$SERVERROOT."/config/autoupdate.mimetypemapping.{$this->appName}.done"; 
		
		if (count($this->mapping) > 0) {
			if (!file_exists($lockFile)) {
				$mappings = $this->mergeJSON($this->mappingFile, $this->mapping);
				if ($mappings === false) {
					$this->updateDB->run(new ArrayInput(Array()), new NullOutput());
					touch($lockFile);
				}
			}
		}
	}
	
	private function mergeJSON($file, $data=Array()) {
		$mergedData = null;
		if (file_exists($file)) {
			$mergedData = json_decode(file_get_contents($file));
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
