<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}
	ajax::init();
	
	if (init('action') == 'syncLecteur') {
		synoaudio::syncLecteur();
		ajax::success();
	}

	if (init('action') == 'getQueue') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->getQueue($syno->getLogicalId()));
	}

	if (init('action') == 'playTrack') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->play($syno->getLogicalId(), init('position')));
	}

	if (init('action') == 'removeTrack') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->removeTrack(init('position'),$syno->getLogicalId()));
	}

	if (init('action') == 'emptyQueue') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->emptyQueue($syno->getLogicalId()));
	}

	if (init('action') == 'playplaylist') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		$cmd = $syno->getCmd(null, 'play_playlist');
		$cmd->execCmd(array('title' => init('playlist'),'player' => $syno->getLogicalId()));
		ajax::success();
	}

	if (init('action') == 'playRadio') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		$cmd = $syno->getCmd(null, 'play_radio');
		$cmd->execCmd(array('title' => init('radio'),'player' => $syno->getLogicalId()));
		ajax::success();
	}

	if (init('action') == 'player') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		$cmd = $syno->getCmd(null, 'player');
		$cmd->execCmd(array('title' => init('players'),'message' => init('volume')) );
		ajax::success();
	}
	
	if (init('action') == 'tts') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		$cmd = $syno->getCmd(null, 'player');
		$cmd->execCmd(array('title' => init('message'),'message' => init('volume')) );
		ajax::success();
	}
	
	

	if (init('action') == 'getSyno') {
		if (init('object_id') == '') {
			$object = object::byId($_SESSION['user']->getOptions('defaultDashboardObject'));
		} else {
			$object = object::byId(init('object_id'));
		}
		if (!is_object($object)) {
			$object = object::rootObject();
		}
		$return = array();
		$return['eqLogics'] = array();
		if (init('object_id') == '') {
			foreach (object::all() as $object) {
				foreach ($object->getEqLogic(true, false, 'synoaudio') as $syno) {
					$return['eqLogics'][] = $syno->toHtml(init('version'));
				}
			}
		} else {
			foreach ($object->getEqLogic(true, false, 'synoaudio') as $syno) {
				$return['eqLogics'][] = $syno->toHtml(init('version'));
			}
			foreach (object::buildTree($object) as $child) {
				$synos = $child->getEqLogic(true, false, 'synoaudio');
				if (count($synos) > 0) {
					foreach ($synos as $syno) {
						$return['eqLogics'][] = $syno->toHtml(init('version'));
					}
				}
			}
		}
		ajax::success($return);
	}
	
	if (init('action') == 'updateSyno') {
		synoaudio::updateSynoaudio();
		ajax::success();
	}
	
	if (init('action') == 'playsearchartist') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->addTrack(init('artist'),'artist',$syno->getLogicalId()));
	}
	
	if (init('action') == 'playsearchalbum') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->addTrack(init('album'),'album',$syno->getLogicalId(),'false',init('artistalbum')));
	}
	
	if (init('action') == 'playsearchsong') {
		$syno = synoaudio::byId(init('id'));
		if (!is_object($syno)) {
			ajax::success();
		}
		ajax::success($syno->addTrack(init('song'),'song',$syno->getLogicalId()));
	}
	
	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
