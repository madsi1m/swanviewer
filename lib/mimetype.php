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
		$this->updateJS();
		$this->updateDB();
	}

	/**
	 * Triggers an update of /core/js/mimetypes.json
	 */
	public function updateJS() {
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
	public function updateDB() {
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
		if (file_exists($file) && count($data) > 0) {
			$mergedData = json_decode(file_get_contents($file));
		} else {
			$mergedData = Array();
		}
		$mergedData = array_merge($mergedData, $data);
		return file_put_contents($file, json_encode($mergedData, JSON_FORCE_OBJECT), LOCK_EX);
	}
}
