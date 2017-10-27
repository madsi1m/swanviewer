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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\SwanViewer\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
	   ['name' => 'page#do_config', 'url' => '/config', 'verb' => 'GET'],
	   ['name' => 'page#do_eosinfo', 'url' => '/eosinfo', 'verb' => 'GET'],
	   ['name' => 'page#do_load', 'url' => '/load', 'verb' => 'GET'],
	   ['name' => 'page#do_public_load', 'url' => '/publicload', 'verb' => 'GET'],
    ]
];
