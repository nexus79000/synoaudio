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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function synoaudio_install() {
    $cron = cron::byClassAndFunction('synoaudio', 'pull');
    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('synoaudio');
        $cron->setFunction('pull');
        $cron->setEnable(1);
        $cron->setDeamon(1);
		$cron->setDeamonSleepTime(5);
        $cron->setSchedule('* * * * *');
		$cron->setTimeout(60);
        $cron->save();
    }
}

function synoaudio_update() {
    $cron = cron::byClassAndFunction('synoaudio', 'pull');
    if (!is_object($cron)) {
        $cron = new cron();
    }
	$cron->stop();
	$cron->setClass('synoaudio');
    $cron->setFunction('pull');
    $cron->setEnable(1);
    $cron->setDeamon(1);
	$cron->setDeamonSleepTime(5);
    $cron->setSchedule('* * * * *');
	$cron->setTimeout(60);
    $cron->save();
	
	synoaudio::updateSynoaudio();
	
}

function synoaudio_remove() {
	//Remise a plat raz sources.list et desinstall picotts
	log::remove('synoaudio_update');
	$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../ressources/remove.sh';
	$cmd .= ' >> ' . log::getPathToLog('synoaudio_update') . ' 2>&1 &';
	exec($cmd);
	
	// Deamon
    $cron = cron::byClassAndFunction('synoaudio', 'pull');
    if (is_object($cron)) {
		$cron->halt();
        $cron->remove();
    }
}
?>
