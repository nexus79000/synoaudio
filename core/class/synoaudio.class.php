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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../php/synoaudio.inc.php';


class synoaudio extends eqLogic {
	/*     * *************************Attributs****************************** */
	public static $_tts_encours = false;
	
	public static $_widgetPossibility = array(
		'custom' => true);
	
	/*     * ***********************Methode static*************************** */
	
	public static function updateSynoaudio() {
		log::remove('synoaudio_update');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh';
		$cmd .= ' >> ' . log::getPathToLog('synoaudio_update') . ' 2>&1 &';
		exec($cmd);
		
		$eqLogics = synoaudio::byType('synoaudio', true);
		foreach ($eqLogics as $eqLogic){		
				$eqLogic->save();
		}
	}
	
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'synoaudio_update';
		$return['progress_file'] = '/tmp/install_synoaudio_in_progress';
		$return['state'] = (self::dependancy_Ok()) ? 'ok' : 'nok';
		return $return;
	}

	public static function dependancy_install() {
		if (file_exists('/tmp/install_synoaudio_in_progress')) {
			return;
		}
		log::remove('synoaudio_update');
		shell_exec('sudo chmod +x' . dirname(__FILE__) . '/../../ressources/install.sh ' );
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh ';
		$cmd .= ' >> ' . log::getPathToLog('synoaudio_update') . ' 2>&1 &';
		exec($cmd);
	}
	
	public static function dependancy_Ok() {
		//Vérification du moteur pico
		if(config::byKey('ttsEngine','synoaudio') =='local'){
			if (shell_exec(' ls -l /usr/bin/pico2wave |wc -l') == 0 || shell_exec(' ls -l /usr/bin/lame |wc -l') == 0 ) {
				return false;
			}
		}
		return true;
	}
	
	public static function tache_deamon($_action) {

		switch($_action) {
			case 'Start' :
				self::deamon_start();
				self::deamon_changeAutoMode(1);
				break;
			case 'Stop':
				do {
					self::deamon_stop();
					sleep(10);
					$etat=self::deamon_info();
				} while ($etat['state'] != 'nok');
				self::deamon_changeAutoMode(0);
				break;
			default:
				log::add('synoaudio', 'debug', 'Tache_deamon : L\'action \'' . $_action .'\' n\'est pas reconnu.' );
		}
	}
   
   	public function deamon_changeAutoMode($_mode) {
		config::save('deamonAutoMode', $_mode, 'synoaudio');
	}
   
    public static function deamon_info() {
		$return = array();
		$return['log'] = '';
		$return['state'] = 'nok';
		$cron = cron::byClassAndFunction('synoaudio', 'pull');
		if (is_object($cron) && $cron->running()) {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		return $return;
	}

    public static function deamon_start() {

		self::deamon_stop();
        
        $sessionsid=config::byKey('SYNO.SID.Session','synoaudio');
        if ($sessionsid=='') {
            self::createURL();
			self::updateAPIs();
            self::getSid();
        }
        config::save('deamon','true','synoaudio');
        $deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		
		$cron = cron::byClassAndFunction('synoaudio', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->run(); 
        log::add('synoaudio', 'info', '### Démarrage du deamon ###');
	}

    public static function deamon_stop() {
        config::save('deamon','false','synoaudio');
		$cron = cron::byClassAndFunction('synoaudio', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->halt();
        log::add('synoaudio', 'info', '### Arrêt du deamon ###');
   }
	
	public static function pull($_eqLogic_id = null){ //Fini
		log::add('synoaudio', 'debug',' Récupération de l\'état des lecteurs - Début' );
		foreach (synoaudio::byType('synoaudio') as $eqLogic) {
			if($eqLogic->getIsEnable()){
				log::add('synoaudio', 'debug', ' Récupération de l\'état du lecteur ' .$eqLogic->getName());
				try {
					$player=$eqLogic->getLogicalId();
					$compl_URL='additional=song_tag%2Csong_audio%2Csubplayer_volume';
					$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayerStatus','getstatus',null,$player,null,$compl_URL );
					if (is_array($obj) || is_object($obj)){
						$changed = false;
						
						if($obj->success != "false" && $obj->error->code == "500"){
							
							$cmd_state = $eqLogic->getCmd(null, 'state');
							if (is_object($cmd_state)) {
								if ( 'Player hors ligne' != $cmd_state->execCmd()) {
									log::add('synoaudio', 'debug', ' Lecteur hors ligne ' .$eqLogic->getName());
									$cmd_state->setCollectDate('');
									$cmd_state->event('Player hors ligne');
									$changed = true;
								}
							}
						}else{
							$cmd_state = $eqLogic->getCmd(null, 'state');
							if (is_object($cmd_state)) {
								$state = self::convertState( $obj->data->state);
								if ($state != $cmd_state->execCmd()) {
									log::add('synoaudio', 'debug', ' Mise à jour état : ' . $state);
									$cmd_state->setCollectDate('');
									$cmd_state->event($state);
									$changed = true;
								}
							}
							
							$cmd_volume = $eqLogic->getCmd(null, 'volume');
							if (is_object($cmd_volume)) {
								$volume = intval($obj->data->volume);
								if ($volume != $cmd_volume->execCmd()) {
									log::add('synoaudio', 'debug', ' Mise à jour Volume : '. $volume );	
									$cmd_volume->setCollectDate('');
									$cmd_volume->event($volume);
									$changed = true;
								}
							}
							
							if ($eqLogic->getlogicalId() == '__SYNO_Multiple_AirPlay__' ){
								config::save('SYNO.subplayer', $obj->data->subplayer_volume , 'synoaudio');
							}
							
					
							
							$cmd_shuffle = $eqLogic->getCmd(null, 'shuffle_state');
							if (is_object($cmd_shuffle)) {
								$shuffle = $obj->data->play_mode->shuffle;
								if ($shuffle == '') {
									$shuffle = false;
								}
								if ($shuffle != $cmd_shuffle->execCmd()) {
									log::add('synoaudio', 'debug', ' Mise à jour shuffle : '. $shuffle );	
									$cmd_shuffle->setCollectDate('');
									$cmd_shuffle->event($shuffle);
									$changed = true;
								}
							}
						
							$cmd_repeat = $eqLogic->getCmd(null, 'repeat_state');
							if (is_object($cmd_repeat)) {
								$repeat = $obj->data->play_mode->repeat;
								if ($repeat == '') {
									$repeat = 'none';
								}
								if ($repeat != $cmd_repeat->execCmd()) {
									log::add('synoaudio', 'debug', ' Mise à jour repeat : '. $repeat );
									$cmd_repeat->setCollectDate('');
									$cmd_repeat->event($repeat);
									$changed = true;
								}
							}
							
							if($state='Lecture' OR $state='Pause'){
								
								$cmd_track_title = $eqLogic->getCmd(null, 'track_title');
								if ($obj->data->playlist_total != 0){
									$title = $obj->data->song->title;
								}else{
									$title = __('Aucun', __FILE__);
								}
								if (is_object($cmd_track_title)) {
									if ($title != $cmd_track_title->execCmd()) {
										log::add('synoaudio', 'debug', ' Mise à jour Titre : ' . $title);
										$cmd_track_title->setCollectDate('');
										$cmd_track_title->event($title);
										$changed = true;
									}
								}
								if(!strstr($title,'tts_')){			
									$cmd_track_album = $eqLogic->getCmd(null, 'track_album');
									if ($obj->data->playlist_total != 0){
										$album = $obj->data->song->additional->song_tag->album;
									}else{
										$album = __('Aucun', __FILE__);
									}
									if (is_object($cmd_track_album)) {
										if ($album != $cmd_track_album->execCmd()) {
											log::add('synoaudio', 'debug', ' Mise à jour Album : ' . $album);
											$cmd_track_album->setCollectDate('');
											$cmd_track_album->event($album);
											$changed = true;
										}
									}
							
									$cmd_track_artist = $eqLogic->getCmd(null, 'track_artist');
									if ($obj->data->playlist_total != 0){
										$artist = $obj->data->song->additional->song_tag->artist;
									}else{
										$artist = __('Aucun', __FILE__);
									}
									if (is_object($cmd_track_artist)) {
										if ($artist != $cmd_track_artist->execCmd()) {
											log::add('synoaudio', 'debug', ' Mise à jour Artist : ' . $artist);
											$cmd_track_artist->setCollectDate('');
											$cmd_track_artist->event($artist);
											$changed = true;
										}
									}
									if ($eqLogic->getDisplay('isLight') == 0) {
										$r_url = config::byKey('SYNO.conf.url','synoaudio');
										$sessionsid=config::byKey('SYNO.SID.Session','synoaudio');
										if ($obj->data->playlist_total != 0){
										$songid=$obj->data->song->id;
											$cover=$r_url . '/webapi/AudioStation/cover.cgi?api=SYNO.AudioStation.Cover&version=2&method=getsongcover&id=' . $songid . '&_sid=' . $sessionsid;
										}	
										$cmd_track_image = $eqLogic->getCmd(null, 'track_image');
										if (is_object($cmd_track_image)) {
											if ($obj->data->playlist_total != 0){
												if ($cover != $cmd_track_image->execCmd()) {
													$cmd_track_image->setCollectDate('');
													$cmd_track_image->event($cover);
													//if (!@file_get_contents($cover) === false ) {
													//if (!@synoaudio::getCurlPage($cover) === false ) {
													if (!stristr(synoaudio::getCurlPage($cover),'not found')){
														log::add('synoaudio', 'debug', ' Mise à jour Cover '. $cover );
														file_put_contents(dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $eqLogic->getId() . '.jpg', synoaudio::getCurlPage($cover));
														$changed = true;
													} else {
														log::add('synoaudio', 'debug', ' Pas de Cover ');
														if (file_exists(dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $eqLogic->getId() . '.jpg')) {
															unlink(dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $eqLogic->getId() . '.jpg');
														}
													}
												}
											}else{
												if (file_exists(dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $eqLogic->getId() . '.jpg')) {
													unlink(dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $eqLogic->getId() . '.jpg');
												}
											}
										}
									}
								}
								if ($changed) {
									$eqLogic->refreshWidget();
								}
								if ($eqLogic->getConfiguration('synoaudioNumberFailed', 0) > 0) {
									$eqLogic->setConfiguration('synoaudioNumberFailed', 0);
									$eqLogic->save();
								}
							}
						}
					}
				} catch (Exception $e) {
					if ($_eqLogic_id != null) {
						log::add('synoaudio', 'error', $e->getMessage());
					} else {
						if ($eqLogic->getConfiguration('synoaudioNumberFailed', 0) > 150) {
							log::add('synoaudio', 'error', __('Erreur sur ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $e->getMessage());
						} else {
							$eqLogic->setConfiguration('synoaudioNumberFailed', $eqLogic->getConfiguration('synoaudioNumberFailed', 0) + 1);
							$eqLogic->save();
						}
					}
				}
			}	
		}
		log::add('synoaudio', 'debug',' Récupération de l\'état des lecteurs - Fin' );
	}
	
	public function cronDaily($_eqLogic_id = null){
		if ( config::byKey('deamon','synoaudio')=='true' ){
			do {
				self::deamon_stop();
				sleep(10);
				$etat=self::deamon_info();
			} while ($etat['state'] != 'nok');
			
			self::createURL();
			self::updateAPIs();
            self::getSid();
			self::deamon_start();
		}
	}
	
    public function cron10(){
        if ( config::byKey('deamon','synoaudio')=='false' ){
            log::add('synoaudio', 'info',' Deamon désactivé, passage en cron10 pour éviter la déconnection' );
            self::pull();
		}
	}
    
	public static function convertState($_state) {
		switch ($_state) {
			case 'playing':
				return __('Lecture', __FILE__);
			case 'paused':
				return __('Pause', __FILE__);
			case 'stopped':
				return __('Arrêté', __FILE__);
			}
		return $_state;
	}
	
	public static function syncLecteur() {
		//Récupération de tous les players
        //self::deleteSid();
		self::createURL();
		self::updateAPIs();	
		self::getSid();
				
		$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','list',null,null,null,null);
		foreach ($obj->data->players as $player){
			$eqLogic = synoaudio::byLogicalId($player->id, 'synoaudio');
			if (!is_object($eqLogic)) {
				$eqLogic = new self();
				$eqLogic->setLogicalId($player->id);
				$pname=$player->name;
				$pname_ascii = iconv('UTF-8','ASCII',$pname);
				if ($pname_ascii != $pname || empty($pname) ){
					$pname='player_tmp_' . rand(10,99);
				}
				log::add('synoaudio', 'debug',' Ajout du player : ' . $pname );
				$eqLogic->setName($pname);
				$eqLogic->setConfiguration('is_multiple', $player->is_multiple);
				$eqLogic->setConfiguration('password_protected', $player->password_protected);
				$eqLogic->setConfiguration('support_seek', $player->support_seek);
				$eqLogic->setConfiguration('support_set_volume', $player->support_set_volume);
				$eqLogic->setConfiguration('type', $player->type);
				$eqLogic->setEqType_name('synoaudio');
				$eqLogic->setIsVisible(1);
				$eqLogic->setIsEnable(1);
				// Affectation des couleurs par défaut
				//$eqLogic->setDisplay('pgTextColor','#ffffff');
				//$eqLogic->setDisplay('pgBackColor','#83B700');
				//Sauvegarde
				$eqLogic->save();
		//	}else{
		//		$eqLogic->setConfiguration('is_multiple', $player->is_multiple);
		//		$eqLogic->setConfiguration('password_protected', $player->password_protected);
		//		$eqLogic->setConfiguration('support_seek', $player->support_seek);
		//		$eqLogic->setConfiguration('support_set_volume', $player->support_set_volume);
		//		$eqLogic->setConfiguration('type', $player->type);
		//		$eqLogic->save();
		//		
			}
		}
	}
	
	/*     * *********************Methode d'instance************************* */

	
	public function preSave() {
		$this->setCategory('multimedia', 1);
	}

	public function preUpdate() {
		// ajout generic type --> $Metar_infosCmd->setDisplay('generic_type','GENERIC_ACTION');
		//	'GENERIC_INFO' => array('name' => ' Générique', 'family' => 'Generic', 'type' => 'Info'),
		//	'GENERIC_ACTION' => array('name' => ' Générique', 'family' => 'Generic', 'type' => 'Action'),
		//	'DONT' => array('name' => 'Ne pas tenir compte de cette commande', 'family' => 'Generic', 'type' => 'All')
		
		$state = $this->getCmd(null, 'state');
		if (!is_object($state)) {
			$state = new synoaudioCmd();
			$state->setLogicalId('state');
			$state->setIsVisible(1);
			$state->setName(__('Statut', __FILE__));
		}
		$state->setType('info');
		$state->setSubType('string');
	//	$state->setDisplay('generic_type','GENERIC_INFO');
		//$state->setEventOnly(1);
		$state->setEqLogic_id($this->getId());
		$state->save();
		
		
		$prev = $this->getCmd(null, 'prev');
		if (!is_object($prev)) {
			$prev = new synoaudioCmd();
			$prev->setLogicalId('prev');
			$prev->setIsVisible(1);
			$prev->setName(__('Précédent', __FILE__));
		}
		$prev->setType('action');
	//	$prev->setDisplay('generic_type','GENERIC_ACTION');
		$prev->setSubType('other');
		$prev->setEqLogic_id($this->getId());
		$prev->save();
		
		$play = $this->getCmd(null, 'play');
		if (!is_object($play)) {
			$play = new synoaudioCmd();
			$play->setLogicalId('play');
			$play->setIsVisible(1);
			$play->setName(__('Play', __FILE__));
		}
		$play->setType('action');
	//	$play->setDisplay('generic_type','GENERIC_ACTION');
		$play->setSubType('other');
		$play->setEqLogic_id($this->getId());
		$play->save();
		
		$stop = $this->getCmd(null, 'stop');
		if (!is_object($stop)) {
			$stop = new synoaudioCmd();
			$stop->setLogicalId('stop');
			$stop->setIsVisible(1);
			$stop->setName(__('Stop', __FILE__));
		}
		$stop->setType('action');
	//	$stop->setDisplay('generic_type','GENERIC_ACTION');
		$stop->setSubType('other');
		$stop->setEqLogic_id($this->getId());
		$stop->save();
		
		$purge = $this->getCmd(null, 'purge');
		if (!is_object($purge)) {
			$purge = new synoaudioCmd();
			$purge->setLogicalId('purge');
			$purge->setIsVisible(1);
			$purge->setName(__('Vider la liste de lecture', __FILE__));
		}
		$purge->setType('action');
	//	$purge->setDisplay('generic_type','GENERIC_ACTION');
		$purge->setSubType('other');
		$purge->setEqLogic_id($this->getId());
		$purge->save();
			
		$pause = $this->getCmd(null, 'pause');
		if (!is_object($pause)) {
			$pause = new synoaudioCmd();
			$pause->setLogicalId('pause');
			$pause->setIsVisible(1);
			$pause->setName(__('Pause', __FILE__));
		}
		$pause->setType('action');
	//	$pause->setDisplay('generic_type','GENERIC_ACTION');
		$pause->setSubType('other');
		$pause->setEqLogic_id($this->getId());
		$pause->save();

		
		$next = $this->getCmd(null, 'next');
		if (!is_object($next)) {
			$next = new synoaudioCmd();
			$next->setLogicalId('next');
			$next->setIsVisible(1);
			$next->setName(__('Suivant', __FILE__));
		}
		$next->setType('action');
	//	$next->setDisplay('generic_type','GENERIC_ACTION');
		$next->setSubType('other');
		$next->setEqLogic_id($this->getId());
		$next->save();
		
		$mute = $this->getCmd(null, 'mute');
		if (!is_object($mute)) {
			$mute = new synoaudioCmd();
			$mute->setLogicalId('mute');
			$mute->setIsVisible(1);
			$mute->setName(__('Muet', __FILE__));
		}
		$mute->setType('action');
	//	$mute->setDisplay('generic_type','GENERIC_ACTION');
		$mute->setSubType('other');
		$mute->setEqLogic_id($this->getId());
		$mute->save();

		$unmute = $this->getCmd(null, 'unmute');
		if (!is_object($unmute)) {
			$unmute = new synoaudioCmd();
			$unmute->setLogicalId('unmute');
			$unmute->setIsVisible(1);
			$unmute->setName(__('Non muet', __FILE__));
		}
		$unmute->setType('action');
	//	$unmute->setDisplay('generic_type','GENERIC_ACTION');
		$unmute->setSubType('other');
		$unmute->setEqLogic_id($this->getId());
		$unmute->save();

		$repeat = $this->getCmd(null, 'repeat');
		if (!is_object($repeat)) {
			$repeat = new synoaudioCmd();
			$repeat->setLogicalId('repeat');
			$repeat->setIsVisible(1);
			$repeat->setName(__('Répéter', __FILE__));
		}
		$repeat->setType('action');
	//	$repeat->setDisplay('generic_type','GENERIC_ACTION');
		$repeat->setSubType('other');
		$repeat->setEqLogic_id($this->getId());
		$repeat->save();

		$repeat_state = $this->getCmd(null, 'repeat_state');
		if (!is_object($repeat_state)) {
			$repeat_state = new synoaudioCmd();
			$repeat_state->setLogicalId('repeat_state');
			$repeat_state->setIsVisible(1);
			$repeat_state->setName(__('Répéter status', __FILE__));
		}
		$repeat_state->setType('info');
	//	$repeat_state->setDisplay('generic_type','GENERIC_INFO');
		//$repeat_state->setEventOnly(1);
		$repeat_state->setSubType('string');
		$repeat_state->setEqLogic_id($this->getId());
		$repeat_state->save();

		$shuffle = $this->getCmd(null, 'shuffle');
		if (!is_object($shuffle)) {
			$shuffle = new synoaudioCmd();
			$shuffle->setLogicalId('shuffle');
			$shuffle->setIsVisible(1);
			$shuffle->setName(__('Aléatoire', __FILE__));
		}
		$shuffle->setType('action');
	//	$shuffle->setDisplay('generic_type','GENERIC_ACTION');
		$shuffle->setSubType('other');
		$shuffle->setEqLogic_id($this->getId());
		$shuffle->save();

		$shuffle_state = $this->getCmd(null, 'shuffle_state');
		if (!is_object($shuffle_state)) {
			$shuffle_state = new synoaudioCmd();
			$shuffle_state->setLogicalId('shuffle_state');
			$shuffle_state->setIsVisible(1);
			$shuffle_state->setName(__('Aléatoire status', __FILE__));
		}
		$shuffle_state->setType('info');
	//	$shuffle_state->setDisplay('generic_type','GENERIC_INFO');
		//$shuffle_state->setEventOnly(1); 
		$shuffle_state->setSubType('string');
		$shuffle_state->setEqLogic_id($this->getId());
		$shuffle_state->save();

		$volume = $this->getCmd(null, 'volume');
		if (!is_object($volume)) {
			$volume = new synoaudioCmd();
			$volume->setLogicalId('volume');
			$volume->setIsVisible(1);
			$volume->setName(__('Volume status', __FILE__));
		}
		$volume->setUnite('%');
		$volume->setType('info');
	//	$shuffle_state->setDisplay('generic_type','GENERIC_INFO');
		//$volume->setEventOnly(1);
		$volume->setSubType('numeric');
		$volume->setEqLogic_id($this->getId());
		$volume->save();

		$setVolume = $this->getCmd(null, 'setVolume');
		if (!is_object($setVolume)) {
			$setVolume = new synoaudioCmd();
			$setVolume->setLogicalId('setVolume');
			$setVolume->setIsVisible(1);
			$setVolume->setName(__('Volume', __FILE__));
		}
		$setVolume->setType('action');
	//	$setVolume->setDisplay('generic_type','GENERIC_ACTION');
		$setVolume->setSubType('slider');
		$setVolume->setValue($volume->getId());
		$setVolume->setEqLogic_id($this->getId());
		$setVolume->save();

		$track_title = $this->getCmd(null, 'track_title');
		if (!is_object($track_title)) {
			$track_title = new synoaudioCmd();
			$track_title->setLogicalId('track_title');
			$track_title->setIsVisible(1);
			$track_title->setName(__('Piste', __FILE__));
		}
		$track_title->setType('info');
		//$track_title->setEventOnly(1);
		$track_title->setSubType('string');
	//	$track_title->setDisplay('generic_type','GENERIC_INFO');
		$track_title->setEqLogic_id($this->getId());
		$track_title->save();

		$track_artist = $this->getCmd(null, 'track_artist');
		if (!is_object($track_artist)) {
			$track_artist = new synoaudioCmd();
			$track_artist->setLogicalId('track_artist');
			$track_artist->setIsVisible(1);
			$track_artist->setName(__('Artiste', __FILE__));
		}
		$track_artist->setType('info');
	//	$track_artist->setDisplay('generic_type','GENERIC_INFO');
		//$track_artist->setEventOnly(1);
		$track_artist->setSubType('string');
		$track_artist->setEqLogic_id($this->getId());
		$track_artist->save();

		$track_album = $this->getCmd(null, 'track_album');
		if (!is_object($track_album)) {
			$track_album = new synoaudioCmd();
			$track_album->setLogicalId('track_album');
			$track_album->setIsVisible(1);
			$track_album->setName(__('Album', __FILE__));
		}
		$track_album->setType('info');
	//	$track_album->setDisplay('generic_type','GENERIC_INFO');
		//$track_album->setEventOnly(1);
		$track_album->setSubType('string');
		$track_album->setEqLogic_id($this->getId());
		$track_album->save();

		$track_image = $this->getCmd(null, 'track_image');
		if (!is_object($track_image)) {
			$track_image = new synoaudioCmd();
			$track_image->setLogicalId('track_image');
			$track_image->setIsVisible(1);
			$track_image->setName(__('Image', __FILE__));
		}
		$track_image->setType('info');
	//	$track_image->setDisplay('generic_type','GENERIC_INFO');
		//$track_image->setEventOnly(1);
		$track_image->setSubType('string');
		$track_image->setEqLogic_id($this->getId());
		$track_image->save();

		$play_playlist = $this->getCmd(null, 'play_playlist');
		if (!is_object($play_playlist)) {
			$play_playlist = new synoaudioCmd();
			$play_playlist->setLogicalId('play_playlist');
			$play_playlist->setIsVisible(1);
			$play_playlist->setName(__('Jouer playlist', __FILE__));
		}
		$play_playlist->setType('action');
	//	$play_playlist->setDisplay('generic_type','GENERIC_ACTION');
		$play_playlist->setSubType('message');
		$play_playlist->setDisplay('message_disable', 1);
		$play_playlist->setDisplay('title_placeholder', __('Titre de la playlist', __FILE__));
		$play_playlist->setEqLogic_id($this->getId());
		$play_playlist->save();

		$play_radio = $this->getCmd(null, 'play_radio');
		if (!is_object($play_radio)) {
			$play_radio = new synoaudioCmd();
			$play_radio->setLogicalId('play_radio');
			$play_radio->setIsVisible(1);
			$play_radio->setName(__('Jouer une radio', __FILE__));
		}
		$play_radio->setType('action');
	//	$play_radio->setDisplay('generic_type','GENERIC_ACTION');
		$play_radio->setSubType('message');
		$play_radio->setDisplay('message_disable', 1);
		$play_radio->setDisplay('title_placeholder', __('Titre de la radio', __FILE__));
		$play_radio->setEqLogic_id($this->getId());
		$play_radio->save();

		
		if ($this->getlogicalId() == '__SYNO_Multiple_AirPlay__'){
			$player = $this->getCmd(null, 'player');
			if (!is_object($player)) {
				$player = new synoaudioCmd();
				$player->setLogicalId('player');
				$player->setIsVisible(1);
				$player->setName(__('Multiple Player', __FILE__));
			}
			$player->setEqLogic_id($this->getId());
			$player->setType('action');
		//	$player->setDisplay('generic_type','GENERIC_ACTION');
			$player->setSubType('message');
			$player->setDisplay('title_disable', 0);
			$player->setDisplay('title_placeholder', __('Nom des players', __FILE__));
			$player->setDisplay('message_placeholder', __('Volume des players', __FILE__));
			$player->save();
		}else{
			$player = $this->getCmd(null, 'player');
			if (is_object($player)) {
				$player->remove();
			}
		}
		
		//Modification pour ajout du TTS
		$tts = $this->getCmd(null, 'tts');
		if (!is_object($tts)) {
			$tts = new synoaudioCmd();
			$tts->setLogicalId('tts');
			$tts->setIsVisible(1);
			$tts->setName(__('Dire', __FILE__));
		}
		$tts->setType('action');
		//$tts->setDisplay('generic_type','GENERIC_ACTION');
		$tts->setSubType('message');
		//$tts->setDisplay('title_disable', 0);
		
		$tts->setDisplay('title_placeholder', __('Volume', __FILE__));
		$tts->setDisplay('message_placeholder', __('Message', __FILE__));
		$tts->setEqLogic_id($this->getId());
		$tts->save();
		
		//Modification pour Interaction
		$ordre = $this->getCmd(null, 'ordre');
		if (!is_object($ordre)) {
			$ordre = new synoaudioCmd();
			$ordre->setLogicalId('ordre');
			$ordre->setIsVisible(1);
			$ordre->setName(__('Ordre', __FILE__));
		}
		$ordre->setType('action');
		//$ordre->setDisplay('generic_type','GENERIC_ACTION');
		$ordre->setSubType('message');
		//$ordre->setDisplay('title_disable', 0);
		$ordre->setDisplay('title_placeholder', __('Type ( Album ou Artiste ) ', __FILE__));
		$ordre->setDisplay('message_placeholder', __('Nom à rechercher ', __FILE__));
		$ordre->setEqLogic_id($this->getId());
		$ordre->save();
		
		//Modification pour Interaction
		$tache = $this->getCmd(null, 'tache');
		if (!is_object($tache)) {
			$tache = new synoaudioCmd();
			$tache->setLogicalId('tache');
			$tache->setIsVisible(1);
			$tache->setName(__('Tache', __FILE__));
		}
		$tache->setType('action');
		//$tache->setDisplay('generic_type','GENERIC_ACTION');
		$tache->setSubType('message');
		$tache->setDisplay('title_disable', 1);
		$tache->setDisplay('message_placeholder', __('Tache cron (Start-Stop) ', __FILE__));
		$tache->setEqLogic_id($this->getId());
		$tache->save();
	}

	/*     * **********************Getteur Setteur*************************** */

	public function createURL(){  // Terminée pas touche!
    
    log::add('synoaudio', 'debug',' Appel createURL ' );
		//création de l'URL
		if (config::byKey('synoHttps','synoaudio') == true) {
			$racineURL='https://'. config::byKey('synoAddr','synoaudio').':'. config::byKey('synoPort','synoaudio');
		}else{
			$racineURL='http://'. config::byKey('synoAddr','synoaudio').':'. config::byKey('synoPort','synoaudio');
		}
		config::save('SYNO.conf.url', $racineURL , 'synoaudio');
	}

	public static function getCurlPage($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, false);
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_REFERER, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		
		if( ! $result = curl_exec($ch))
		{
			$erreur=curl_error($ch);
			log::add('synoaudio', 'error',' Appel de curl en erreur : ' . $erreur );
			curl_close($ch);
			return $erreur;
		} 
		curl_close($ch);
		return $result;
}
	
	public static function appelURL($API, $method=null, $action=null, $player=null, $value=null, $libre=null) {
		//Construit l'URL, l'appel et retourne 
		$url=config::byKey('SYNO.conf.url','synoaudio');
		$sessionsid=config::byKey('SYNO.SID.Session','synoaudio');
		$arrAPI=config::byKey($API,'synoaudio');
		
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
		
				
		$fURL = $url.'/webapi/'.$apiPath.'?api=' . $API . '&version='.$apiVersion;
			if($method !== null){
				$fURL = $fURL . '&method=' . $method;
			}
			if($action !== null){
				$fURL = $fURL . '&action=' . $action;
			}
			if($player !== null){
				$fURL = $fURL . '&id=' . $player;
			}
			if($value !== null){
				$fURL = $fURL . '&value=' . $value;
			}
			if($libre !== null){
				$libre = str_replace(' ', '%20', $libre); // -> ' ' par %20
				$libre = str_replace('/', '%2F', $libre); // -> / par %2F
				$libre = str_replace('"', '%22', $libre); // -> " par %22
				$libre = str_replace(':', '%3A', $libre); // -> : par %3A
				$libre = str_replace(',', '%2C', $libre); // -> , par %2C
				$fURL = $fURL . '&' . $libre;
			}
			$fURL = $fURL . '&_sid='. $sessionsid;
		
		log::add('synoaudio', 'debug',' Appel de l\'API : ' . $API . '  url : ' . $fURL );
		//Appel de l'URL
		//$json = file_get_contents($fURL);
		$json = synoaudio::getCurlPage($fURL);
		
		$obj = json_decode($json);
		if($obj->success != "true"){
			if( $obj->error->code != "500" ) {
				log::add('synoaudio', 'error',' Appel de l\'API : ' . $API . ' en erreur, url : ' . $fURL . ' code : ' . $obj->error->code );
			}
			if( $obj->error->code == "105"|| $obj->error->code=="119" ){ // || $obj->error->code=="106" || $obj->error->code=="107" ){
                self::deleteSid();
				if (config::byKey('syno2auth','synoaudio')=='') {
                    log::add('synoaudio', 'info',' Réinitialisation de la connection ' );
                    self::getSid();
                }else{
                    self::deamon_stop();
                    log::add('synoaudio', 'info',' Une nouvelle clé est nécéssaire pour l\'authentification ' );
                }
              
			}
		}
		return $obj;
	}
	
	public function updateAPIs(){  // Terminée pas touche!
		//Mise à jour des API version et chemin 
		//Get SYNO.API.Auth Path (recommended by Synology for further update)
		log::add('synoaudio', 'debug',' Mise à jour des API - Début' );

		$url=config::byKey('SYNO.conf.url','synoaudio');
		$list_API = array(
		'SYNO.API.Auth',
		'SYNO.AudioStation.Info',
		'SYNO.AudioStation.Album',
		'SYNO.AudioStation.Composer',
		'SYNO.AudioStation.Genre',
		'SYNO.AudioStation.Artist',
		'SYNO.AudioStation.Folder',
		'SYNO.AudioStation.Song',
		'SYNO.AudioStation.Stream',
		'SYNO.AudioStation.Radio',
		'SYNO.AudioStation.Playlist',
		'SYNO.AudioStation.RemotePlayer',
		'SYNO.AudioStation.RemotePlayerStatus',
		'SYNO.AudioStation.WebPlayer',
		'SYNO.AudioStation.Proxy',
		'SYNO.AudioStation.Lyrics',
		'SYNO.AudioStation.LyricsSearch',
		'SYNO.AudioStation.MediaServer',
		'SYNO.AudioStation.Cover',
		'SYNO.AudioStation.Download',
		'SYNO.AudioStation.Search',
		);
		
		$fURL=$url . '/webapi/query.cgi?api=SYNO.API.Info&method=Query&version=1&query=SYNO.API.Auth,SYNO.AudioStation.';
		//$json = file_get_contents($fURL);
		
		$json = synoaudio::getCurlPage($fURL);
		$obj = json_decode($json);
				
		if($obj->success != "true"){
			log::add('synoaudio', 'error', 'Mise à jour des API ' . $API . ' en erreur, url : ' . $fURL . ' , code : ' . $obj->error->code );
		}else{
			foreach ($list_API as $element){
				config::save($element, array (
											"path" => $obj->data->$element->path,
											"version" =>$obj->data->$element->maxVersion
										)
							, 'synoaudio');
			}
			log::add('synoaudio', 'debug',' Mise à jour des API - OK' );
		}
		log::add('synoaudio', 'debug',' Mise à jour des API - Fin' );
	}
	
	public function getSid(){ //fini
        if (config::byKey('SYNO.SID.Session', 'synoaudio') != '') {
            log::add('synoaudio', 'debug',' La session existe déjà ' );
			return true;
		}
		log::add('synoaudio', 'debug',' Création de la session - Début ' );
		
		$url=config::byKey('SYNO.conf.url','synoaudio');
		$login=urlencode(config::byKey('synoUser','synoaudio'));
		$pass=urlencode(config::byKey('synoPwd','synoaudio'));
        $auth=urlencode(config::byKey('syno2auth','synoaudio'));

		$arrAPI=config::byKey('SYNO.API.Auth','synoaudio');
			
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
		
		//Login and creating SID
		$fURL = $url.'/webapi/'. $apiPath .'?api=' . $apiName . '&method=login&version='. $apiVersion .'&account='.$login.'&passwd='.$pass.'&session=AudioStation&format=sid&otp_code=' . $auth . '&enable_device_token=yes';
		//$json = file_get_contents($fURL);
		$json = synoaudio::getCurlPage($fURL);
		$obj = json_decode($json);
		if($obj->success != "true"){
			log::add('synoaudio', 'error',' Création de la session ' . $apiName . ' en erreur, url : ' . $fURL . ', code : ' . $obj->error->code );
			exit();
		}else{
			//authentification successful
			$sid = $obj->data->sid;
			config::save('SYNO.SID.Session', $sid , 'synoaudio');
			log::add('synoaudio', 'debug',' Création de la session OK , $sid : ' . $sid);
		}
		log::add('synoaudio', 'debug',' Création de la session - Fin ' );
	}
	
	public function deleteSid(){ //fini
		//Logout and destroying SID
		log::add('synoaudio', 'debug',' Destruction de la session - Début ');
		$url=config::byKey('SYNO.conf.url','synoaudio');
		
		$sessionsid= config::byKey('SYNO.SID.Session','synoaudio');
		$arrAPI=config::byKey('SYNO.API.Auth','synoaudio');
			
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
						
		
		if($sessionsid==null){
			log::add('synoaudio', 'debug',' Pas de session à détruire ');
		}else{
			$fURL=$url.'/webapi/'.$apiPath.'?api=SYNO.API.Auth&method=Logout&version='.$apiVersion.'&session=AudioStation&_sid='.$sessionsid;
			//$json = file_get_contents($fURL);
			$json = synoaudio::getCurlPage($fURL);
			$obj = json_decode($json);
			if($obj->success != "true"){
				log::add('synoaudio', 'error',' Destruction de la session en erreur, code : ' . $obj->error->code );
				exit();
			}else{
				//authentification successful
				config::remove('SYNO.SID.Session','synoaudio');
				log::add('synoaudio', 'debug',' Destruction de la session - OK ');
			}
		}
		log::add('synoaudio', 'debug',' Destruction de la session - Fin ');
	}
	
	public function play($_player='__SYNO_WEB_PLAYER__', $_position=null ) { // A Fini
		
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','play',$_player,$_position,null);	
	}
	
	public function seek($_player='__SYNO_WEB_PLAYER__', $_position=null ) { // A Fini

	
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','seek',$_player,$_position,null);	
	}
	
	public function pause($player='__SYNO_WEB_PLAYER__') { //Fini
				
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','pause',$player,null,null);
	}
	
	public function stop($player='__SYNO_WEB_PLAYER__') { //Fini
	
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','stop',$player,null,null);
	}

	public function prev($player='__SYNO_WEB_PLAYER__') { //Fini
		
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','prev',$player,null,null);
	}
	
	public function next($player='__SYNO_WEB_PLAYER__') { //Fini
		
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','next',$player,null,null);
	}
	
	public function mute($_player='__SYNO_WEB_PLAYER__'){
		$eqLogic = synoaudio::byLogicalId($_player, 'synoaudio');
		$cmd_volume = $eqLogic->getCmd(null, 'volume');
		if (is_object($cmd_volume)) {
			if($_player == '__SYNO_Multiple_AirPlay__'){
    
				$subplayers='';
				$subvolumes='';
		        
				foreach (config::byKey('SYNO.subplayer', 'synoaudio') as $player => $volume){
					$eqLogic = synoaudio::byLogicalId($player, 'synoaudio');
					cache::set( 'SYNO.tmp.volume'. $eqLogic-> getId() , $volume, 240,null) ;
					
					if($subplayers==''){
						$subplayers=$player;
					}else{
						$subplayers=$subplayers . ','. $player;
					}
					
					if($subvolumes==''){
						$subvolumes= '0' ;
					}else{
						$subvolumes= $subvolumes . ','. '0';
					}
				}
                
				$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,null,'subplayer_id=' . $subplayers . '&value=' . $subvolumes );
			}else{
	
				cache::set( 'SYNO.tmp.volume'. $eqLogic-> getId() ,$cmd_volume->execCmd(), 240,null) ;
				self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,0,null);
			}	
		}
	}
	
	public function unmute($_player='__SYNO_WEB_PLAYER__'){
		$eqLogic=synoaudio::byLogicalId($_player,'synoaudio');
		
		If($_player == '__SYNO_Multiple_AirPlay__'){
			$subplayers='';
			$subvolumes=0;
		
			foreach (config::byKey('SYNO.subplayer', 'synoaudio') as $player => $vol){
				$eqLogic = synoaudio::byLogicalId($player, 'synoaudio');
				$volume=cache::byKey('SYNO.tmp.volume'. $eqLogic-> getId());

				if($subplayers==''){
					$subplayers=$player;
				}else{
					$subplayers=$subplayers . ','. $player;
				}
				
				if($subvolumes==0){
					$subvolumes= $volume->getvalue() ;
				}else{
					$subvolumes= $subvolumes . ','. $volume->getvalue();
				}
			}

			self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,null,'subplayer_id=' . $subplayers . '&value=' . $subvolumes );
		}else{
			
			$volume=cache::byKey('SYNO.tmp.volume'. $eqLogic-> getId() );

			if ($volume->getvalue()!= '') {
				self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,$volume->getvalue(),null);
			}
		}
		
		$volume->remove();
	}
	
	public function repeat($_player='__SYNO_WEB_PLAYER__',$_repeat=null){ //Fini
		$eqLogic = synoaudio::byLogicalId($_player, 'synoaudio');
		$cmd_repeat = $eqLogic->getCmd(null, 'repeat_state');
		if (is_object($cmd_repeat)) {
			if ($_repeat==null){
				if ($cmd_repeat->execCmd() == 'none') {
					$repeat='all';
				}
				if ($cmd_repeat->execCmd() == 'all') {
					$repeat='one';
	
				}
				if ($cmd_repeat->execCmd() == 'one') {
					$repeat='none';
				}
			}else{
				$repeat = $_repeat;
			}
			self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_repeat',$_player,$repeat,null);
			
		}
	}
	
	public function shuffle($_player='__SYNO_WEB_PLAYER__'){ //Fini
		$cmd_shuffle = $this->getCmd(null, 'shuffle_state');
		if (is_object($cmd_shuffle)) {
			if ($cmd_shuffle->execCmd() == true) {
				$shuffle='false';
			}
			if ($cmd_shuffle->execCmd() == false) {
				$shuffle='true';
			}
		self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_shuffle',$_player,$shuffle,null);
		}
	}
	
	public function setVolume( $_value,$_player='__SYNO_WEB_PLAYER__'){
		
		If($_player == '__SYNO_Multiple_AirPlay__'){
			$eqLogic=synoaudio::byLogicalId($_player,'synoaudio');
			$cmd_volume = $eqLogic->getCmd(null, 'volume');
				if (is_object($cmd_volume)) {
					$addvall= $_value - $cmd_volume->execCmd();
	
					$subplayers='';
					$subvolumes=0;
	
					foreach (config::byKey('SYNO.subplayer', 'synoaudio') as $player => $volume){
						if($subplayers==''){
							$subplayers=$player;
						}else{
							$subplayers=$subplayers . ','. $player;
						}
						$tmpvol=$volume + $addvall;
						
						if($subvolumes==0){
							$subvolumes= $tmpvol ;
						}else{
							$subvolumes= $subvolumes . ','. $tmpvol;
						}
					}
				}
			log::add('synoaudio', 'debug', 'subplayer_id=' . $subplayers . '&value=' . $subvolumes );
			$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,null,'subplayer_id=' . $subplayers . '&value=' . $subvolumes );
		}else{
			self::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,$_value,null);
		}
	}
	
	public static function getPlayLists() {
		// récupère la liste des playlist sur AudioStation
		
		$compl_URL='container=UserDefined&library=all';
		$obj=synoaudio::appelURL('SYNO.AudioStation.Playlist','list',null,null,null,$compl_URL);
/*		
		$playlists = array();
		$playlists[] = array('PlaylistAleatoire'=>'Liste de lecture aléatoire');
		foreach ($obj->data->playlists as $playlist) {
			if ($playlist->name != '__SYNO_AUDIO_SHARED_SONGS__'){
				$playlists[] = array($playlist->id => $playlist->name);
			}
		}
*/		log::add('synoaudio', 'debug', ' GetPlayList - Fin' );
		return $obj->data->playlists;
	}
	
	public static function play_playlist($_playlist='__SYNO_AUDIO_SHARED_SONGS__',$_player='__SYNO_WEB_PLAYER__', $_empty=true){
		if ($_empty){
			self::emptyQueue($_player);
		}
		
		if ( $_playlist=='PlaylistAleatoire' || $_playlist=='PlaylistRecente'){
			
			if ($_playlist=='PlaylistAleatoire'){
				$compl_URL='library=shared&limit=50&additional=song_tag"%"2Csong_audio"%"2Csong_rating&sort_by=random';
				$obj=self::appelURL('SYNO.AudioStation.Song','list',null,null,null,$compl_URL);
				foreach ($obj->data->songs as $song ){
					self::addTrack($song->id,'song',$_player);
				}
			}
			
			if ($_playlist=='PlaylistRecente'){
				$compteur=0;
				$compl_URL='library=shared&limit=50&sort_direction=desc&additional=avg_rating&sort_by=time';
				$obj=self::appelURL('SYNO.AudioStation.Album','list',null,null,null,$compl_URL);
				foreach ($obj->data->albums as $album ){
					$compl_URL='library=shared&limit=50&album='.$album->name .'&album_artist='.$album->album_artist.'&additional=song_tag"%"2Csong_audio"%"2Csong_rating&&sort_by=track&sort_direction=ASC';
					$obj=self::appelURL('SYNO.AudioStation.Song','list',null,null,null,$compl_URL);
					foreach ($obj->data->songs as $song ){
						self::addTrack($song->id,'song',$_player);
						$compteur++;
						if ($compteur == 50 ){
							break 2;
						}
					}
				}
			}
		}else{
			self::addTrack($_playlist,'playlist',$_player);
		}
		self::play($_player);
	}
	
	public static function getRadios() {
		// récupère la liste des radios sur AudioStation
		$compl_URL='container=UserDefined';
		
		$obj=synoaudio::appelURL('SYNO.AudioStation.Radio','list',null,null,null,$compl_URL);
		
		$radios=array();
		
		foreach($obj->data->radios as $radio){
			$radios= array($radio->id => $radio->title);
		}
		return $obj->data->radios;
	}
	
	public static function play_radio($_radio,$_player){
		
		self::emptyQueue($_player);
		self::addTrack($_radio,'radio',$_player);
		self::play($_player);
	}

	public static function addPlayers($_player='__SYNO_WEB_PLAYER__', $_subplayers=null) { 
		// récupère la liste des lecteurs DLNA et airplay
		$subplayer_id='';
		if ($listplayer = explode(',', $_subplayers)) {
			foreach ($listplayer as $player) { // each part
				foreach (synoaudio::byType('synoaudio') as $eqLogic) {
					if ($eqLogic->getName() == $player) {
						if ($subplayer_id == '') { // key/value delimiter
							$subplayer_id=$eqLogic->getLogicalId();
						}else{
							$subplayer_id=$subplayer_id. ',' . $eqLogic->getLogicalId();
						}
					}	
				}
			}		
		}
		// -> $_subplayers player séparer par virgule
		$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','setmultiple',null,$_player,null,'subplayer_id='.$subplayer_id );

		//maj du parametre subplayer_volume;
		config::save('SYNO.subplayer', $obj->data->subplayer_volume , 'synoaudio');
	}
	
	public static function addPlayersVol($_player='__SYNO_WEB_PLAYER__', $_subplayers=null, $_subvolume) { 
		// Met a jour le volume sur le subplayer airplay
		$subplayer_id='';
		if ($listplayer = explode(',', $_subplayers)) {
			foreach ($listplayer as $player) { // each part
				foreach (synoaudio::byType('synoaudio') as $eqLogic) {
					if ($eqLogic->getName() == $player) {
						if ($subplayer_id == '') { // key/value delimiter
							$subplayer_id=$eqLogic->getLogicalId();
						}else{
							$subplayer_id=$subplayer_id. ',' . $eqLogic->getLogicalId();
						}
					}	
				}
			}		
		}
		$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','control','set_volume',$_player,null,'subplayer_id=' . $subplayer_id . '&value=' . $_subvolume );
	}
	
	public static function getPlayers() { 
		// récupère la liste des lecteurs DLNA et airplay
		$obj=synoaudio::appelURL('SYNO.AudioStation.RemotePlayer','list',null,null,null,'type=all&additional=subplayer_list');

		return $obj->data->players;
	}
	
	public static function getServers() { 
		// récupère la liste des Serveurs DLNA
		$obj=synoaudio::appelURL('SYNO.AudioStation.MediaServer','list',null,null,null,null);
		
		return $obj->data->list;
	}
	
	public static function addTrack($_song,$_type,$_player,$_play='false',$_albumartist=null) {
		// Ajouter une piste à la liste de lecture
		switch($_type) {
			case 'play':
				$synoaudio->play($synoaudio->getLogicalId());
				break;
			
			case 'playlist':
				$compl_URL='library=shared&offset=-1&limit=0&play='. $_play .'&containers_json=[{"type":"'. $_type . '","id":"'. $_song .'"}]';
				break;
			
			case  'artist':
				$compl_URL='library=shared&offset=0&limit=0&play='. $_play .'&containers_json=[{"type":"'. $_type . '","sort_by":"title","sort_direction":"ASC","artist":"'. $_song .'"}]';
				break;
			
			case 'album':
				$compl_URL='library=shared&offset=0&limit=0&play='. $_play .'&containers_json=[{"type":"'. $_type . '","sort_by":"title","sort_direction":"ASC","album":"'. $_song .'","album_artist":"'. $_albumartist .'"}]';
				break;
	
			case 'radio':
				$compl_URL='library=shared&offset=-1&limit=0&play='. $_play .'&songs='. $_song .'&containers_json=[]';
				break;
			
			case 'song':
				$compl_URL='library=shared&offset=-1&limit=0&play='. $_play .'&songs='. $_song .'&containers_json=[]';
				break;
			
			default:
				log::add('synoaudio', 'debug', 'addTrack : Pas de type fourni');
				break;	
		}
		
		if (!empty($compl_URL)){
			self::appelURL('SYNO.AudioStation.RemotePlayer','updateplaylist',null,$_player,0,$compl_URL);
		}
	}

	public static function removeTrack($_position,$_player) {
		// Supprimer une piste à la liste de lecture a la position

		$compl_URL='library=shared&offset=' . $_position . '&limit=1&play=false&updated_index=-2';
		
		self::appelURL('SYNO.AudioStation.RemotePlayer','updateplaylist',null,$_player,null,$compl_URL);
		
	}

	public function emptyQueue($_player) { //ok 
        //Vider la liste de lecture
        
        // récupere le nombre de piste
        $songs=self::getQueue($_player);
        
        $i=0;
		if (is_countable($songs)){
			$i=count($songs);
		}
        $compl_URL='offset=0&limit='. $i .'&songs=&updated_index=-1';
    
        self::appelURL('SYNO.AudioStation.RemotePlayer','updateplaylist',null,$_player,0,$compl_URL);
    }

	public function getQueue( $_player) {
		// récupère la liste de lecture en cours sur le lecteur en parametre
		
		$compl_URL='additional=song_tag%2Csong_audio%2Csubplayer_volume';
		
		$obj=self::appelURL('SYNO.AudioStation.RemotePlayer','getplaylist',null,$_player,null,$compl_URL);
	
		return $obj->data->songs;
		
	}
	
	public function getSong($_album, $_artistalbum){
	
		$compl_URL='additional=song_tag%2Csong_audio%2Csong_rating&sort_by=title&sort_direction=ASC&limit=100000&library=shared';
		$compl_URL=$compl_URL . '&album='. $_album .'&album_artist='. $_artistalbum ;
		
		$obj=self::appelURL('SYNO.AudioStation.Song','list',null,null,null,$compl_URL);
	
		return $obj->data;

	}
	
	public static function searchSong($_keyword){
		// récupère la liste des chansons en fonction du mot cle
		
		$compl_URL='library=shared&additional=song_tag%2Csong_audio%2Csong_rating&keyword=' . $_keyword . '&sort_by=title&sort_direction=ASC';
		
		$obj=self::appelURL('SYNO.AudioStation.Search','list',null,null,null,$compl_URL);
		
		return $obj->data;
	}
	
	public static function playTTS($_tts,$_tts_nom,$_player,$_volume){
		//Lecture du fichier TTS
		log::add('synoaudio', 'debug',' Lecture fichier TTS - Début' );
		$chpos=strpos($_volume,'|');
		if ($chpos !== false) {
			$volume=substr($_volume,0,$chpos);
			$tempo=substr($_volume,$chpos+1);
		}else{
			$volume=$_volume;
			$tempo=1;
		}
		log::add('synoaudio', 'debug', ' TTS volume : ' . $volume . ', Temporisation : ' .$tempo );
		$obj_etat=synoaudio::appelURL('SYNO.AudioStation.RemotePlayerStatus','getstatus',null,$_player,null,null);
		log::add('synoaudio', 'debug', ' TTS Pause' );
		self::pause($_player);
		log::add('synoaudio', 'debug', ' TTS Volume' );
		self::setVolume($volume,$_player);
		log::add('synoaudio', 'debug', ' TTS Addtrack' );
		self::addTrack($_tts,'radio',$_player);
		self::repeat($_player,'none');
		$position=intval($obj_etat->data->playlist_total);
		log::add('synoaudio', 'debug', ' TTS Play' );
		self::play($_player, $position );
		
		do {
			sleep($tempo);
			$obj_duree=self::appelURL('SYNO.AudioStation.RemotePlayerStatus','getstatus',null,$_player,null,null );
			log::add('synoaudio', 'debug', ' TTS Boucle temporisation ' );
			log::add('synoaudio', 'debug', ' TTS Etat ' . $obj_duree->data->state  . ' Titre : '. $obj_duree->data->song->title);
		} while (($obj_duree->data->state == 'playing'|| $obj_duree->data->state == 'transitioning')&& $obj_duree->data->song->title == $_tts_nom);
		
		
		self::setVolume('1',$_player);
		if ($obj_etat->data->play_mode->repeat != 'none'){
			self::repeat($_player,$obj_etat->data->play_mode->repeat);
			log::add('synoaudio', 'debug', ' TTS Mode repeat ON ' );
		}
		if ( $obj_etat->data->playlist_total =='0'){ // $obj_etat->data->state != 'none' && 
			self::emptyQueue($_player);
		}else{
			self::removeTrack($position,$_player);
			log::add('synoaudio', 'debug', ' TTS Suppresion track ' );
			if ($obj_etat->data->state == 'playing'||$obj_etat->data->state == 'pause'){
				self::play($_player, $obj_etat->data->index);
				log::add('synoaudio', 'debug', ' TTS Play ' );
				self::seek($_player,$obj_etat->data->position);
				log::add('synoaudio', 'debug', ' TTS Position ' );
				if ($obj_etat->data->state == 'pause'){
					self::pause($_player);
				}
			}
			self::setVolume($obj_etat->data->volume,$_player);
			log::add('synoaudio', 'debug', ' TTS Volume ' );
			if ($obj_etat->data->state == 'stopped' || $obj_etat->data->state == 'none' ){
				self::stop($_player);
			}
		}
		
		log::add('synoaudio', 'debug',' Lecture fichier TTS - Fin' );

		}

    public function toHtml($_version = 'dashboard') { //Fini
        $replace = $this->preToHtml($_version, array('#synoid#' => $this->getlogicalId()), true);
        if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);
		//$replace['#text_color#'] = $this->getConfiguration('text_color');
		$replace['#version#'] = $_version;
        $replace['#synoid#'] = $this->getlogicalId();
        $replace['#hideThumbnail#'] = 0;
        $replace['#IsMultiple#'] = $this->getConfiguration('is_multiple');
        
        if ($this->getDisplay('isLight') == 1) {
			$replace['#hideThumbnail#'] = '1';
		}
        
		$cmd_state = $this->getCmd(null, 'state');
		if (is_object($cmd_state)) {
			$replace['#state#'] = $cmd_state->execCmd();
			if ($replace['#state#'] == __('Lecture', __FILE__)) {
				$replace['#state_nb#'] = 1;
			} else {
				$replace['#state_nb#'] = 0;
			}
		}
		$cmd_track_artist = $this->getCmd(null, 'track_artist');
		if (is_object($cmd_track_artist)) {
			$replace['#artiste#'] = $cmd_track_artist->execCmd();
		}else {
			$replace['#artiste#'] = __('Aucun', __FILE__);
		}
		$cmd_track_album = $this->getCmd(null, 'track_album');
		if (is_object($cmd_track_album)) {
			$replace['#album#'] = $cmd_track_album->execCmd();
		}else {
			$replace['#album#'] = __('Aucun', __FILE__);
		}
		$cmd_track_title = $this->getCmd(null, 'track_title');
		if (is_object($cmd_track_title)) {
			$replace['#title#'] = $cmd_track_title->execCmd();
		}
		if (strlen($replace['#title#']) > 15) {
			$replace['#title#'] = '<marquee behavior="scroll" direction="left" scrollamount="2">' . $replace['#title#'] . '</marquee>';
		}
		$cmd_track_image = $this->getCmd(null, 'track_image');
		if (is_object($cmd_track_image)) {
			$img = dirname(__FILE__) . '/../../../../plugins/synoaudio/docs/images/syno_cover_' . $this->getId() . '.jpg';
			if (file_exists($img) && filesize($img) > 100) {
				$replace['#thumbnail#'] = 'plugins/synoaudio/docs/images/syno_cover_' . $this->getId() . '.jpg?time=' .time();
			} else {
				$replace['#thumbnail#'] = 'plugins/synoaudio/docs/images/syno_cover_default.png?time=' .time();
			}
		}
		$replace['#blockVolume#'] = $this->getConfiguration('support_set_volume');
	
		$cmd_volume = $this->getCmd(null, 'volume');
		if (is_object($cmd_volume)) {
			$replace['#volume#'] = $cmd_volume->execCmd();
		}
		$cmd_setVolume = $this->getCmd(null, 'setVolume');
		if (is_object($cmd_setVolume)) {
			$replace['#volume_id#'] = $cmd_setVolume->getId();
		}
		$volume=cache::byKey('SYNO.tmp.volume');
		if (is_object($volume)) {
			$replace['#onmute#'] = true;
		} 
		$cmd_repeate = $this->getCmd(null, 'repeat_state');
		if (is_object($cmd_repeate)) {
			$replace['#repeat_state#'] = $cmd_repeate->execCmd();
		}
		$cmd_shuffle = $this->getCmd(null, 'shuffle_state');
		if (is_object($cmd_shuffle)) {
			$replace['#shuffle_state#'] = $cmd_shuffle->execCmd();
		}
        foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
		}


        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'synoaudio', 'synoaudio')));
    }
}

class synoaudioCmd extends cmd {
	/*     * *************************Attributs****************************** */
	public static $_widgetPossibility = array('custom' => true);
	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function execute($_options = array()) {
		
		$synoaudio = $this->getEqLogic();
		log::add('synoaudio', 'debug', $synoaudio->getHumanName().' Commande ['.$this->getName() . '] id '. $this->getLogicalId() );
		
		switch($this->getLogicalId()) {
			case 'play':
				$synoaudio->play($synoaudio->getLogicalId());
				break;
			case 'pause':
				$synoaudio->pause($synoaudio->getLogicalId());
				break;
			case 'stop':
				$synoaudio->stop($synoaudio->getLogicalId());
				break;
			case 'purge':
				$synoaudio->emptyQueue($synoaudio->getLogicalId());
				break;
			case 'prev':
				$synoaudio->prev($synoaudio->getLogicalId());
				break;
			case 'next':
				$synoaudio->next($synoaudio->getLogicalId());
				break;
			case 'mute' :
				$synoaudio->mute($synoaudio->getLogicalId());
				break;
			case 'unmute' :
				$synoaudio->unmute($synoaudio->getLogicalId());
				break;
			case 'repeat':
				$synoaudio->repeat($synoaudio->getLogicalId());
				break;
			case 'shuffle':
				$synoaudio->shuffle($synoaudio->getLogicalId());	
				break;
			case 'play_playlist':
				if (!empty($_options['title'])) {
					if (empty($_options['player'])){
						$player= $synoaudio->getLogicalId();
					}else{
						$player=$_options['player'];
					}
					if($_options['title']=='Liste de lecture aléatoire' || $_options['title']== 'Liste des musiques récentes'){
						if($_options['title']=='Liste de lecture aléatoire'){
							$playlist='PlaylistAleatoire';
						}
						if($_options['title']== 'Liste des musiques récentes'){
							$playlist='PlaylistRecente';
						}
					}else{
						foreach (synoaudio::getPlayLists() as $playlists) {
							if ($_options['title'] == $playlists->name) {
								$playlist=$playlists->id;
							}
						}
					}
					$synoaudio->play_playlist($playlist,$player);
				}else{
					log::add('synoaudio', 'error', $synoaudio->getHumanName().' Commande ['.$this->getName() . '] id '. $this->getLogicalId() . ' - Pas de playlist renseigné' );
				}
				break;
			case 'play_radio' :
				if (!empty($_options['title'])) {
					$player=$_options['player'];
					if (empty($player)){
						$player= $synoaudio->getLogicalId();
					}
					foreach (synoaudio::getRadios() as $radios) {
						if ($_options['title'] == $radios->title) {
							$radio=$radios->id;
						}
					}
					$synoaudio->play_radio($radio,$player);
				}else {
					log::add('synoaudio', 'error', $synoaudio->getHumanName().' Commande ['.$this->getName() . '] id '. $this->getLogicalId() . ' - Pas de radio renseigné' );
				}
				break;
			case 'setVolume':
				if ($_options['slider'] < 0) {
					$_options['slider'] = 0;
				}
				if ($_options['slider'] > 100) {
					$_options['slider'] = 100;
				}
				$synoaudio->setVolume($_options['slider'],$synoaudio->getLogicalId());
				break;
			case 'player':
				$synoaudio->addPlayers('__SYNO_Multiple_AirPlay__', $_options['title']);
				$synoaudio->addPlayersVol('__SYNO_Multiple_AirPlay__', $_options['title'],$_options['message']);
				break;
			case 'tts' :
				log::add('synoaudio', 'debug', ' TTS Debut' );
				
				$cmd_state = $synoaudio->getCmd(null, 'state');
				if (is_object($cmd_state)) {
					if ( 'Player hors ligne' == $cmd_state->execCmd()) {
						log::add('synoaudio', 'info', ' Le player ' .$synoaudio->getName() . ' est hors ligne ');
					}else{
						$etat=$synoaudio->deamon_info();
						if ($etat['state'] == 'ok'){
							$synoaudio->deamon_stop();
						}
					
						$tts_fic_wav=dirname(__FILE__) . '/../../voice_tts/tts_' . $synoaudio->getId() . '.wav';
						$tts_fic_mp3=dirname(__FILE__) . '/../../voice_tts/tts_' . $synoaudio->getId() . '.mp3';
						$tts_nom='tts_'. $synoaudio->getId();

		
						//Récupération du message
//debut modification temporaire				
						if (is_numeric(substr($_options['message'],0,1))){
							$message=$_options['title'];
							$volume=$_options['message'];
						}else {
			// A	garder
							$volume=$_options['title'];
							$message=$_options['message'];
						}
//fin modification temporaire
						if ($_options['title']=='[Jeedom] Message de test'){
							$volume='45';
						}
						
						log::add('synoaudio', 'debug', ' TTS message :'. $message . ' - Volume : ' . $volume );
						//Appel du moteur TTS
						//if (config::byKey('ttsEngine','synoaudio') == 'local'){
							$tts_extention='.wav';
							/*if (strlen($message) > 400) {
								$message = substr($message, 0, 400);
							}*/
							$cmd='pico2wave -l '. str_replace ( '_', '-', config::byKey('language','core')).' -w '. $tts_fic_wav .' "' . $message . '"';
							log::add('synoaudio', 'debug', ' TTS Commande Pico ' . $cmd );
							$tmp=shell_exec($cmd);
							//convertion Mp3
							if (config::byKey('ttsTransco','synoaudio') == true){
								$tts_extention='.mp3';
								$cmd='lame -b 128 '. $tts_fic_wav .' '. $tts_fic_mp3 .' && rm -f '.$tts_fic_wav ;
								log::add('synoaudio', 'debug', ' TTS Convertion Pico ' . $cmd );
								$tmp=shell_exec($cmd);
							}
					
						/*}else{
							//voxygen
							if (strlen($message) > 100) {
								$message = substr($message, 0, 100);
							}
							$tts_extention='.mp3';
							$provider = new VoxygenProvider(config::byKey('ttsVoxygenVoice','synoaudio'));
							$tts = new TextToSpeech($message, $provider);
							//Ecriture du fichier
							log::add('synoaudio', 'debug', ' TTS Mode online' );
							$handle = fopen($tts_fic_mp3, "w");
							fwrite ($handle , $tts->getAudioData());
						}
						*/
						//Lecture du fichier avec audioStation
						if ($volume < 0) {
							$volume = 0;
						}
						if ($volume > 100) {
							$volume = 100;
						}
						
						$serveur=config::byKey('internalAddr','core');
						if (config::byKey('synoAddrDocker','synoaudio')){
							$serveur=config::byKey('synoAddrDocker','synoaudio');
						}
						if (config::byKey('internalPort','core')){
							$porthttp=':' . config::byKey('internalPort','core');

						}
						if (config::byKey('synoPortDocker','synoaudio')){
							$porthttp=':' . config::byKey('synoPortDocker','synoaudio');
						}
			
						$serveur=$serveur . $porthttp;
						
						$complement=str_replace('/','',config::byKey('internalComplement','core'));
						
						$tts_url='radio_'. $tts_nom .'%20'.urlencode('http://'. $serveur .'/'. $complement .'/'.'plugins/synoaudio/voice_tts/'. $tts_nom . $tts_extention);
							
						log::add('synoaudio', 'debug', ' TTS PlayTTS : ' .$tts_url.','.$tts_nom.','.$synoaudio->getLogicalId().','.$volume );
						$synoaudio->playTTS($tts_url,$tts_nom,$synoaudio->getLogicalId(),$volume);
						if ($etat['state'] == 'ok'){
							$synoaudio->deamon_start();
						}
					}
				}
				break;
			case 'ordre':
				$cmd_state = $synoaudio->getCmd(null, 'state');
				if (is_object($cmd_state)) {
					if ( $cmd_state->execCmd() == 'Player hors ligne'){
						log::add('synoaudio', 'info', ' Le player ' .$synoaudio->getName() . ' est hors ligne ');
					}else{
						if (empty($_options['title'])) {
							$type='artists'; //Album ou Artiste
							log::add('synoaudio', 'debug', ' Ordre : Le type est positionné à \'artist\' par défaut.');
						}else {
							$input = $_options['title']; 	// mot a tester
							$words  = array('albums','artists');	// tableau de mots à vérifier
							$shortest = -1;  // aucune distance de trouvée pour le moment
		
							foreach ($words as $word) {		// boucle sur les mots pour trouver le plus près
								$lev = levenshtein($input, $word);	// calcule la distance avec le mot mis en entrée et le mot courant
								// cherche une correspondance exacte
								if ($lev == 0) {
									$closest = $word;
									$shortest = 0;
									break 1;
								}
								if ($lev <= $shortest || $shortest < 0) {
									// définition du mot le plus près ainsi que la distance
									$closest  = $word;
									$shortest = $lev;
								}
							}
							$type=$closest;

							log::add('synoaudio', 'debug', ' Ordre : Le type est positionné à ' . $type );
						}
						
						if (empty($_options['message'])) {
							log::add('synoaudio', 'debug', ' Ordre : Pas de champ de nom pour faire la recherche.');
							break;
						}else {
							$message=str_replace(' ', '%20', $_options['message']); // -> ' ' par %20
							//$message=urlencode($message);
						}
						
						//Recherche
						$result=synoaudio::searchSong($message);
						
						//Ajout liste lecture
						switch($type) {
							case 'albums':
								if	( $result->albumTotal!= '0'){
									foreach ( $result->albums as $album) {
										log::add('synoaudio', 'debug', ' Ordre : Album -> ' . $album->name );
										$synoaudio->addTrack($album->name,'album',$synoaudio->getLogicalId(),'false',$album->album_artist);
										$synoaudio->play($synoaudio->getLogicalId());
										break 1;
									}
								}else{
									log::add('synoaudio', 'info', ' Ordre : ' . $type . ' pas de résultat');
								}
								break;
							case 'artists':
								if ($result->artistTotal != '0'){
									foreach ( $result->artists as $artist) {
										log::add('synoaudio', 'debug', ' Ordre : Artiste -> ' . $artist->name);
										$synoaudio->addTrack( $artist->name,'artist',$synoaudio->getLogicalId());
										$synoaudio->play($synoaudio->getLogicalId());
										break 1;
									}
								}else {
									log::add('synoaudio', 'info', ' Ordre : ' . $type . ' pas de résultat');
								}
								break;
							default:
								break;
						}	
					}
				}
				break;
			case 'tache':
				if (empty($_options['message'])) {
					log::add('synoaudio', 'debug', ' la commande Tache n\'a pas de paramêtre.');
				}else {
					$synoaudio->tache_deamon($_options['message']);
				}
				break;
			default:
				throw new Exception(__('Commande non reconnu', __FILE__));
		}
		return false;
	}

	/*     * **********************Getteur Setteur*************************** */
}
