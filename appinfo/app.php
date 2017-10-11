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

namespace OCA\SwanViewer\AppInfo;

use OCP\AppFramework\App;
use \OCA\SwanViewer\Mimetype;

require_once __DIR__ . '/autoload.php';

$app = new App('swanviewer');
$container = $app->getContainer();

if (\OCP\Util::getVersion()[0] >= 10) {
	$domains = \OC::$server->getConfig()->getSystemValue("cbox.swan.cspdomains", ['cdnjs.cloudflare.com', 'root.cern.ch']);
	$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
	foreach($domains as $domain) {
	        $policy->addAllowedScriptDomain($domain);
	        $policy->addAllowedFrameDomain($domain);
	        $policy->addAllowedStyleDomain($domain);
	}
	\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
}

\OCP\Util::addScript('swanviewer', 'script');
\OCP\Util::addStyle('swanviewer', 'style');

$mime = new Mimetype('swanviewer');
$mime->addAlias("application/pynb", "text/code");
$mime->addMapping("ipynb", "application/pynb");
$mime->update();
