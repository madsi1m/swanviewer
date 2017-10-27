<?php
/**
 * ownCloud - swanviewer
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Gonzalez Labrador (CERN) <hugo.gonzalez.labrador@cern.ch>
 * @copyright Hugo Gonzalez Labrador (CERN) 2017
 */

namespace OCA\SwanViewer\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OC\Files\ObjectStore\EosProxy;
use OC\Files\ObjectStore\EosUtil;

class PageController extends Controller {


	private $userId;
	private $swanUrl;
	private $pythonLib;
	private $pythonBin;
	private $inputHack;
	private $eosUtil;
	private $redisHost;
	private $redisPort;
	private $redisPassword;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->swanUrl= \OC::$server->getConfig()->getSystemValue("cbox.swan.url", "https://cern.ch/swanserver/cgi-bin/go"); 
		$this->pythonLib = \OC::$server->getConfig()->getSystemValue("cbox.swan.pythonlib", "/opt/rh/python28/root/usr/lib64");
		$this->pythonBin = \OC::$server->getConfig()->getSystemValue("cbox.swan.pythonbin", "/opt/rh/python27/root/usr/bin/python"); 
		$this->inputHack = \OC::$server->getConfig()->getSystemValue("cbox.swan.inputhack", "./input_hack.py"); 
		$this->redisHost = \OC::$server->getConfig()->getSystemValue("cbox.swan.redis.host", "127.0.0.1"); 
		$this->redisPort = \OC::$server->getConfig()->getSystemValue("cbox.swan.redis.port", "6379"); 
		$this->redisPassword = \OC::$server->getConfig()->getSystemValue("cbox.swan.redis.password", "password"); 
		$this->userId = $UserId;

		if (method_exists(\OC::$server,'getCernBoxEosUtil')) {
			$this->eosUtil = \OC::$server->getCernBoxEosUtil();
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function doConfig() {
		return new DataResponse(['swanurl' => $this->swanUrl]);
	}
	
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function doEosinfo($filename) {
		if(!$this->userId) {
			return new DataResponse(['error' => 'user is not logged in']);
		}

		if ($this->eosUtil) {
			list($uid, $gid) = $this->eosUtil->getUidAndGidForUsername($this->userId);
			if(!$uid || !$gid) {
				return new DataResponse(['error' => 'user does not have valid uid/gid']);
			}
		}

		$node = \OC::$server->getUserFolder($this->userId)->get($filename);
		if(!$node) {
			return new DataResponse(['error' => 'file does not exists']);
		}

		//set up tokens
		$redis = new Redis();
		$redis->connect($this->redisHost, $this->redisPort);
		$redis->auth($this->redisPassword);

		if (!isSet($_SESSION['swan.login.'.$UserId])) {
			$_SESSION['swan.login.'.$this->userId] = bin2hex(random_bytes(32));
		}
		if ($redis->get($this->userId) === false) {
			$redis->set($this->userId, $_SESSION['swan.login.'.$this->userId]);
			$redis->setEx($this->userId, 3600, 'value');
		}
		
		$info = $node->stat();
		$login = array(
				'token' => $_SESSION['swan.login.'.$this->userId],
				'user' => $this->userId
			);
		return new DataResponse(array(
						'eosinfo' => $info,
						'login' => $login
					));
	} 

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function doLoad($filename) {
		if(!$this->userId) {
			return new DataResponse(['error' => 'user is not logged in']);
		}

		if ($this->eosUtil) {
			list($uid, $gid) = $this->eosUtil->getUidAndGidForUsername($this->userId);
			if(!$uid || !$gid) {
				return new DataResponse(['error' => 'user does not have valid uid/gid']);
			}
		}

		$node = \OC::$server->getUserFolder($this->userId)->get($filename);
		if(!$node) {
			return new DataResponse(['error' => 'file does not exists']);
		}

		$info = $node->stat();
		// TODO(labkode): check for file size limit maybe?
		
		$content = $node->getContent();

		// Convert notebook
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w") // error
		);

		$pipes = [];
		$returnValue = 0;

		$process = proc_open(sprintf("%s %s", $this->pythonBin, $this->inputHack), $descriptorspec, $pipes, NULL, ['LD_LIBRARY_PATH' => $this->pythonLib]);
		fwrite($pipes[0], $content);
		fclose($pipes[0]);

		$result = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		if ($error) {
			\OCP\Util::writeLog('files_nbviewer', 'Python ERROR: ' .$error, \OCP\Util::ERROR);
		}

		$returnValue = proc_close($process);
		if($returnValue === 0) {
			return new DataResponse(['data' => ["content" => $result]]);
		} else {
			\OCP\Util::writeLog('files_nbviewer', 'Error while converting notebook. Return code: ' .$returnValue, \OCP\Util::ERROR);
			return new DataResponse(['error' => 'error converting the notebook']);
		}
		return;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function doPublicLoad($token, $filename) {
		$share = \OC::$server->getShareManager()->getShareByToken($token);
		if(!$share) {
			return new DataResponse(['error' => 'invalid token']);
		}

		if ($this->eosUtil) {
			$owner = $share->getShareOwner();
			list($uid, $gid) = $this->eosUtil->getUidAndGidForUsername($owner);
			if(!$uid || !$gid) {
				return new DataResponse(['error' => 'user does not have valid uid/gid']);
			}
		}

		$node = $share->getNode();
		if($node->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
			$node = $share->getNode()->get($filename);
		}
		if(!$node) {
			return new DataResponse(['error' => 'file does not exists']);
		}

		$info = $node->stat();
		// TODO(labkode): check for file size limit maybe?

		$content = $node->getContent();

		// Convert notebook
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "w") // error
		);

		$pipes = [];
		$returnValue = 0;

		$process = proc_open(sprintf("%s %s", $this->pythonBin, $this->inputHack), $descriptorspec, $pipes, NULL, ['LD_LIBRARY_PATH' => $this->pythonLib]);
		fwrite($pipes[0], $content);
		fclose($pipes[0]);

		$result = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                if ($error) {
                        \OCP\Util::writeLog('files_nbviewer', 'Python ERROR: ' .$error, \OCP\Util::ERROR);
                }

		$returnValue = proc_close($process);
		if($returnValue === 0) {
			return new DataResponse(['data' => ["content" => $result]]);
		} else {
			\OCP\Util::writeLog('files_nbviewer', 'Error while converting notebook. Return code: ' .$returnValue, \OCP\Util::ERROR);
			return new DataResponse(['error' => 'error converting the notebook']);
		}
		return;
	}
}
