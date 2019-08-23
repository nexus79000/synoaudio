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
require_once dirname(__FILE__) . '/../php/synovideo.inc.php';

class synovideo extends eqLogic {
	/*     * *************************Attributs****************************** */
	public static $_widgetPossibility = array(
		'custom' => true);
	
	/*     * ***********************Methode static*************************** */
	
	
	public static function dependancy_info() {
	}

	public static function dependancy_install() {
	}
	
	public static function dependancy_Ok() {
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
				log::add('synovideo', 'debug', 'Tache_deamon : L\'action \'' . $_action .'\' n\'est pas reconnu.' );
		}
	}
   
   	public function deamon_changeAutoMode($_mode) {
		config::save('deamonAutoMode', $_mode, 'synovideo');
	}
   
    public static function deamon_info() {
		$return = array();
		$return['log'] = '';
		$return['state'] = 'nok';
		$cron = cron::byClassAndFunction('synovideo', 'pull');
		if (is_object($cron) && $cron->running()) {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		return $return;
	}

    public static function deamon_start() {
		
		self::deamon_stop();
        
        $sessionsid=config::byKey('SYNO.SID.Session','synovideo');
        if ($sessionsid=='') {
            self::getSid();
        }
        config::save('deamon','true','synovideo');
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		
		$cron = cron::byClassAndFunction('synovideo', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->run();  
        log::add('synovideo', 'info', '### Démarrage du deamon ###');
	}

    public static function deamon_stop() {
        config::save('deamon','false','synovideo');
		$cron = cron::byClassAndFunction('synovideo', 'pull');
		if (!is_object($cron)) {
			throw new Exception(__('Tache cron introuvable', __FILE__));
		}
		$cron->halt();
        log::add('synovideo', 'info', '### Arrêt du deamon ###');
   }
	
	public static function pull($_eqLogic_id = null){ 
		log::add('synovideo', 'debug',' Récupération de l\'état des lecteurs - Début' );
		try {
			$compl_URL='limit=500000';
			$objd=synovideo::appelURL('SYNO.VideoStation2.Controller.Device','list',null,null,null,$compl_URL);
			if (is_array($objd) || is_object($objd)){
				foreach ($objd->data->device as $device) {
					$devices=$devices .'_'. $device->id;
					$volumeadjust=array($device->id => $device->volume_adjustable);
				}
				foreach (synovideo::byType('synovideo') as $eqLogic) {
					if($eqLogic->getIsEnable()){
						if ( preg_match( '/'.$eqLogic->getLogicalId().'/i', $devices)){
							log::add('synovideo', 'debug', ' Récupération de l\'état du lecteur ' .$eqLogic->getName());
							$player=$eqLogic->getLogicalId();
							$obj=synovideo::appelURL('SYNO.VideoStation2.Controller.Playback','status',null,$player,null,null);
					
							$changed = false;
	
							if($obj->success != false ){
								
								if ($volumeadjust[$player]=='true'){
									$obj_volume=synovideo::appelURL('SYNO.VideoStation2.Controller.Volume','get',null,$player,null,null );
								
									$volume = intval($obj_volume->data->volume);
									$changed = $eqLogic->checkAndUpdateCmd('volume', $volume) || $changed;
								}
								$state = self::convertState( $obj->data->state);
								$changed = $eqLogic->checkAndUpdateCmd('state', $state) || $changed;
								
								//"duration":5739.0,"file_id":35,"playback_target":{"target":"file_id"},"position":437.0,
								$changed = $eqLogic->checkAndUpdateCmd('position', intval($obj->data->position)) || $changed;
								
								//"duration":5739.0,"file_id":35,"playback_target":{"target":"file_id"},"position":437.0,
								$changed = $eqLogic->checkAndUpdateCmd('duration', intval($obj->data->duration)) || $changed;
								
								$client_id = $obj->data->client_id;
								$changed = $eqLogic->checkAndUpdateCmd('client_id', $client_id) || $changed;
									
								
								//	{"data":{"volume":0},"success":true}
								
	
								
								if ($obj->data->title !=""){
									$title = $obj->data->title;
								}else{
									$title = __('Aucun', __FILE__);
								}
								
								$changed = $eqLogic->checkAndUpdateCmd('movie_title', $title) || $changed;
								
								
								$video_type=$obj->data->type;
								$video_id=$obj->data->video_id;
			
								$img_poster=synovideo::getPoster($video_type,$video_id);
								$changed = $eqLogic->checkAndUpdateCmd('movie_image', $img_poster) || $changed;
								
								if($obj->data->state!='STOPPED'){
									switch($video_type) {
										case 'movie':
											$obj_movie=synovideo::movie_info($video_id);
											foreach ($obj_movie->movie as $movie){
												$genres = "";
												foreach ($movie->additional->genre as $genre){
													$genres = $genres . ' ' . $genre;
												}
											}
											break;
										case 'tvshow_episode':
											$obj_episode=synovideo::tvshowepisode_info($video_id);
											foreach ($obj_episode->episode as $episode){
												$genres = "";
												foreach ($episode->additional->genre as $genre){
													$genres = $genres . ' ' . $genre;
												}
											}
											break;
										case 'home_video':
											$genres = __('Aucun', __FILE__);
											break;
										case 'tv_record':
											$genres = __('Aucun', __FILE__);
											log::add('synovideo', 'debug', 'Pas d\'information sur le type '. $video_type);
											break;
										default:
											throw new Exception(__('Type non reconnu', __FILE__));
									}
								}
								
								$changed = $eqLogic->checkAndUpdateCmd('movie_genres', $genres) || $changed;
								$cmd_movie_genres = $eqLogic->getCmd(null, 'movie_genres');
							}
	
						}else{
							log::add('synovideo', 'debug', ' Lecteur ' .$eqLogic->getName() .' hors ligne ');
							
							$state = __('Player hors ligne', __FILE__);
							$changed = $eqLogic->checkAndUpdateCmd('state', $state) || $changed;
							
							$title = __('Aucun', __FILE__);
							$changed = $eqLogic->checkAndUpdateCmd('movie_title', $title) || $changed;
							
							
							$video_type=$obj->data->type;
							$video_id=$obj->data->video_id;
							
							$img_poster='plugins/synovideo/docs/images/syno_poster_default.png';
							$changed = $eqLogic->checkAndUpdateCmd('movie_image', $img_poster) || $changed;
						}
					}
				}
				if ($changed) {
					$eqLogic->refreshWidget();
				}
			}
			
		} catch (Exception $e) {
			if ($_eqLogic_id != null) {
				log::add('synovideo', 'error', $e->getMessage());
			} else {
				log::add('synovideo', 'error', __('Erreur sur ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $e->getMessage());
			}
		}	
		log::add('synovideo', 'debug',' Récupération de l\'état des lecteurs - Fin' );
	}
	
	public function cronDaily($_eqLogic_id = null){
		if ( config::byKey('deamon','synovideo')=='true'){
			do {
				self::deamon_stop();
				sleep(10);
				$etat=self::deamon_info();
			} while ($etat['state'] != 'nok');
			
			self::createURL();
			self::updateAPIs();
            self::getSid();
			self::deamon_start();
			self::listLibrary();
		}
	}
	
    public function cron10(){
        if ( config::byKey('deamon','synovideo')=='false' ){
            log::add('synovideo', 'info',' Deamon désactivé, passage en cron10 pour éviter la déconnection' );
            self::pull();
		}
	}
    
    public static function convertOctet($_octets) {
	   $resultat = $_octets;
		for ($i=0; $i < 8 && $resultat >= 1024; $i++) {
			$resultat = $resultat / 1024;
		}
		if ($i > 0) {
			return preg_replace('/,00$/', '', number_format($resultat, 2, ',', '')) . ' ' . substr('KMGTPEZY',$i-1,1) . 'o';
		} else {
			return $resultat . ' o';
		}
	}
	
	public static function convertState($_state) {
		switch ($_state) {
			case 'PLAYING':
				return __('Lecture', __FILE__);
			case 'PAUSED':
				return __('Pause', __FILE__);
			case 'STOPPED':
				return __('Arrêté', __FILE__);
			case 'ERROR':
				return __('En erreur', __FILE__);
			}
		return $_state;
	}
	
	public static function syncLecteur() {
		//Récupération de tous les players
        self::deleteSid();
		self::createURL();
		self::updateAPIs();	
		self::getSid();
		self::listLibrary();
		$compl_URL='limit=500000';
		$obj=synovideo::appelURL('SYNO.VideoStation2.Controller.Device','list',null,null,null,$compl_URL);
		foreach ($obj->data->device as $player){
			$eqLogic = synovideo::byLogicalId($player->id, 'synovideo');
			if (!is_object($eqLogic)) {
				$eqLogic = new self();
				$eqLogic->setLogicalId($player->id);
				$pname=$player->title;
				$pname_ascii = iconv('UTF-8','ASCII',$pname);
				if ($pname_ascii != $pname || empty($pname) ){
					$pname='player_tmp_' . rand(10,99);
				}
				log::add('synovideo', 'debug',' Ajout du player : ' . $pname );
				$eqLogic->setName($pname);
				$eqLogic->setConfiguration('password_protected', $player->password_protected);
				$eqLogic->setConfiguration('seekable', $player->seekable);
				$eqLogic->setConfiguration('volume_adjustable', $player->volume_adjustable);
				$eqLogic->setConfiguration('type', $player->type);
				$eqLogic->setEqType_name('synovideo');
				$eqLogic->setIsVisible(1);
				$eqLogic->setIsEnable(1);
				// Affectation des couleurs par défaut
				//$eqLogic->setDisplay('pgTextColor','#ffffff');
				//$eqLogic->setDisplay('pgBackColor','#83B700');
				// Affectation d'un piece (Objet) si présent dans le nombre du Player
				foreach (jeeObject::all() as $object){
					if (stristr($player->name ,$object->getName())) {
						$eqLogic->setObject_id($object->getId());
					}
				}
				//Sauvegarde
				$eqLogic->save();
			}else{
				$eqLogic->setConfiguration('password_protected', $player->password_protected);
				$eqLogic->setConfiguration('seekable', $player->seekable);
				$eqLogic->setConfiguration('volume_adjustable', $player->volume_adjustable);
				$eqLogic->setConfiguration('type', $player->type);
				foreach (jeeObject::all() as $object){
					if (stristr($player->name ,$object->getName())) {
						$eqLogic->setObject_id($object->getId());
					}
				}
				$eqLogic->save();
			}
		}
		//refresh de la page
		echo "<script>";
		echo "function myFunction() {";
		echo "	location.reload();";
		echo "}";
		echo "myFunction()";
		echo "</script>";
		
	}
	
	public static function purgePlugin() {
		$file_mask=dirname(__FILE__) . '/../../docs/images/syno_poster_*_*.jpg';
		shell_exec('rm -rf ' . $file_mask);
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
	
	// Commande Info
	
		$state = $this->getCmd(null, 'state');
		if (!is_object($state)) {
			$state = new synovideoCmd();
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

		$position = $this->getCmd(null, 'position');
		if (!is_object($position)) {
			$position = new synovideoCmd();
			$position->setLogicalId('position');
			$position->setIsVisible(1);
			$position->setName(__('Position Statut', __FILE__));
		}
		$position->setType('info');
		$position->setSubType('%');
	//	$state->setDisplay('generic_type','GENERIC_INFO');
		//$state->setEventOnly(1);
		$position->setEqLogic_id($this->getId());
		$position->save();
		
		$duration = $this->getCmd(null, 'duration');
		if (!is_object($duration)) {
			$duration = new synovideoCmd();
			$duration->setLogicalId('duration');
			$duration->setIsVisible(1);
			$duration->setName(__('Durée', __FILE__));
		}
		$duration->setType('info');
		$duration->setSubType('%');
	//	$state->setDisplay('generic_type','GENERIC_INFO');
		//$state->setEventOnly(1);
		$duration->setEqLogic_id($this->getId());
		$duration->save();
		
		$client_id = $this->getCmd(null, 'client_id');
		if (!is_object($client_id)) {
			$client_id = new synovideoCmd();
			$client_id->setLogicalId('client_id');
			$client_id->setIsVisible(1);
			$client_id->setName(__('ID client', __FILE__));
		}
		$client_id->setType('info');
		$client_id->setSubType('string');
	//	$client_id->setDisplay('generic_type','GENERIC_INFO');
		//$client_id->setEventOnly(1);
		$client_id->setEqLogic_id($this->getId());
		$client_id->save();
		
		$volume = $this->getCmd(null, 'volume');
		if (!is_object($volume)) {
			$volume = new synovideoCmd();
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

		
		$movie_title = $this->getCmd(null, 'movie_title');
		if (!is_object($movie_title)) {
			$movie_title = new synovideoCmd();
			$movie_title->setLogicalId('movie_title');
			$movie_title->setIsVisible(1);
		}
		$movie_title->setName(__('Titre', __FILE__));
		$movie_title->setType('info');
		//$movie_title->setEventOnly(1);
		$movie_title->setSubType('string');
	//	$movie_title->setDisplay('generic_type','GENERIC_INFO');
		$movie_title->setEqLogic_id($this->getId());
		$movie_title->save();
		
		$movie_image = $this->getCmd(null, 'movie_image');
		if (!is_object($movie_image)) {
			$movie_image = new synovideoCmd();
			$movie_image->setLogicalId('movie_image');
			$movie_image->setIsVisible(1);
			$movie_image->setName(__('Image', __FILE__));
		}
		$movie_image->setType('info');
	//	$movie_image->setDisplay('generic_type','GENERIC_INFO');
		//$movie_image->setEventOnly(1);
		$movie_image->setSubType('string');
		$movie_image->setEqLogic_id($this->getId());
		$movie_image->save();

		$movie_genres = $this->getCmd(null, 'movie_genres');
		if (!is_object($movie_genres)) {
			$movie_genres = new synovideoCmd();
			$movie_genres->setLogicalId('movie_genres');
			$movie_genres->setIsVisible(1);
			$movie_genres->setName(__('Genres', __FILE__));
		}
		$movie_genres->setType('info');
	//	$movie_genres->setDisplay('generic_type','GENERIC_INFO');
		//$movie_genres->setEventOnly(1);
		$movie_genres->setSubType('string');
		$movie_genres->setEqLogic_id($this->getId());
		$movie_genres->save();

		
/*		$repeat_state = $this->getCmd(null, 'repeat_state');
		if (!is_object($repeat_state)) {
			$repeat_state = new synovideoCmd();
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

		
		
		$shuffle_state = $this->getCmd(null, 'shuffle_state');
		if (!is_object($shuffle_state)) {
			$shuffle_state = new synovideoCmd();
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
		

		
				$track_artist = $this->getCmd(null, 'track_artist');
		if (!is_object($track_artist)) {
			$track_artist = new synovideoCmd();
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
			$track_album = new synovideoCmd();
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

*/		
		
		
		
		
	// Commande Action	
	
		$play = $this->getCmd(null, 'play');
		if (!is_object($play)) {
			$play = new synovideoCmd();
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
			$stop = new synovideoCmd();
			$stop->setLogicalId('stop');
			$stop->setIsVisible(1);
			$stop->setName(__('Stop', __FILE__));
		}
		$stop->setType('action');
	//	$stop->setDisplay('generic_type','GENERIC_ACTION');
		$stop->setSubType('other');
		$stop->setEqLogic_id($this->getId());
		$stop->save();
	
		$pause = $this->getCmd(null, 'pause');
		if (!is_object($pause)) {
			$pause = new synovideoCmd();
			$pause->setLogicalId('pause');
			$pause->setIsVisible(1);
			$pause->setName(__('Pause', __FILE__));
		}
		$pause->setType('action');
	//	$pause->setDisplay('generic_type','GENERIC_ACTION');
		$pause->setSubType('other');
		$pause->setEqLogic_id($this->getId());
		$pause->save();

		$mute = $this->getCmd(null, 'mute');
		if (!is_object($mute)) {
			$mute = new synovideoCmd();
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
			$unmute = new synovideoCmd();
			$unmute->setLogicalId('unmute');
			$unmute->setIsVisible(1);
			$unmute->setName(__('Non muet', __FILE__));
		}
		$unmute->setType('action');
	//	$unmute->setDisplay('generic_type','GENERIC_ACTION');
		$unmute->setSubType('other');
		$unmute->setEqLogic_id($this->getId());
		$unmute->save();

		$setVolume = $this->getCmd(null, 'setVolume');
		if (!is_object($setVolume)) {
			$setVolume = new synovideoCmd();
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
		
		$setPosition = $this->getCmd(null, 'setPosition');
		if (!is_object($setPosition)) {
			$setPosition = new synovideoCmd();
			$setPosition->setLogicalId('setPosition');
			$setPosition->setIsVisible(1);
			$setPosition->setName(__('Position', __FILE__));
		}
		$setPosition->setType('action');
	//	$setPosition->setDisplay('generic_type','GENERIC_ACTION');
		$setPosition->setSubType('slider');
		$setPosition->setValue($volume->getId());
		$setPosition->setEqLogic_id($this->getId());
		$setPosition->save();

		$clean = $this->getCmd(null, 'clean');
		if (!is_object($clean)) {
			$clean = new synovideoCmd();
			$clean->setLogicalId('clean');
			$clean->setIsVisible(1);
			$clean->setName(__('Nettoyage plugin', __FILE__));
		}
		$clean->setType('action');
	//	$clean->setDisplay('generic_type','GENERIC_ACTION');
		$clean->setSubType('other');
		$clean->setEqLogic_id($this->getId());
		$clean->save();
		
		//Modification pour Interaction
		$tache = $this->getCmd(null, 'tache');
		if (!is_object($tache)) {
			$tache = new synovideoCmd();
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
			
		$ordre = $this->getCmd(null, 'ordre');
		if (!is_object($ordre)) {
			$ordre = new synovideoCmd();
			$ordre->setLogicalId('ordre');
			$ordre->setIsVisible(1);
			$ordre->setName(__('Ordre', __FILE__));
		}
		$ordre->setType('action');
		//$ordre->setDisplay('generic_type','GENERIC_ACTION');
		$ordre->setSubType('message');
		//$ordre->setDisplay('title_disable', 0);
		$ordre->setDisplay('title_placeholder', __('Type:Film, Serie ou Perso', __FILE__));
		$ordre->setDisplay('message_placeholder', __('Nom à rechercher ', __FILE__));
		$ordre->setEqLogic_id($this->getId());
		$ordre->save();
		
		
/*	
		$prev = $this->getCmd(null, 'prev');
		if (!is_object($prev)) {
			$prev = new synovideoCmd();
			$prev->setLogicalId('prev');
			$prev->setIsVisible(1);
			$prev->setName(__('Précédent', __FILE__));
		}
		$prev->setType('action');
	//	$prev->setDisplay('generic_type','GENERIC_ACTION');
		$prev->setSubType('other');
		$prev->setEqLogic_id($this->getId());
		$prev->save();
		
		
		$purge = $this->getCmd(null, 'purge');
		if (!is_object($purge)) {
			$purge = new synovideoCmd();
			$purge->setLogicalId('purge');
			$purge->setIsVisible(1);
			$purge->setName(__('Vider la liste de lecture', __FILE__));
		}
		$purge->setType('action');
	//	$purge->setDisplay('generic_type','GENERIC_ACTION');
		$purge->setSubType('other');
		$purge->setEqLogic_id($this->getId());
		$purge->save();
			
		
		$next = $this->getCmd(null, 'next');
		if (!is_object($next)) {
			$next = new synovideoCmd();
			$next->setLogicalId('next');
			$next->setIsVisible(1);
			$next->setName(__('Suivant', __FILE__));
		}
		$next->setType('action');
	//	$next->setDisplay('generic_type','GENERIC_ACTION');
		$next->setSubType('other');
		$next->setEqLogic_id($this->getId());
		$next->save();
		
		$repeat = $this->getCmd(null, 'repeat');
		if (!is_object($repeat)) {
			$repeat = new synovideoCmd();
			$repeat->setLogicalId('repeat');
			$repeat->setIsVisible(1);
			$repeat->setName(__('Répéter', __FILE__));
		}
		$repeat->setType('action');
	//	$repeat->setDisplay('generic_type','GENERIC_ACTION');
		$repeat->setSubType('other');
		$repeat->setEqLogic_id($this->getId());
		$repeat->save();



		$shuffle = $this->getCmd(null, 'shuffle');
		if (!is_object($shuffle)) {
			$shuffle = new synovideoCmd();
			$shuffle->setLogicalId('shuffle');
			$shuffle->setIsVisible(1);
			$shuffle->setName(__('Aléatoire', __FILE__));
		}
		$shuffle->setType('action');
	//	$shuffle->setDisplay('generic_type','GENERIC_ACTION');
		$shuffle->setSubType('other');
		$shuffle->setEqLogic_id($this->getId());
		$shuffle->save();

		$play_playlist = $this->getCmd(null, 'play_playlist');
		if (!is_object($play_playlist)) {
			$play_playlist = new synovideoCmd();
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
			$play_radio = new synovideoCmd();
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
				$player = new synovideoCmd();
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
		
		//Modification pour Interaction
		$ordre = $this->getCmd(null, 'ordre');
		if (!is_object($ordre)) {
			$ordre = new synovideoCmd();
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
			$tache = new synovideoCmd();
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
	*/
	}

	/*     * **********************Getteur Setteur*************************** */

	public function createURL(){  // Terminée pas touche!
		//création de l'URL
		if (config::byKey('synoHttps','synovideo') == true) {
			$racineURL='https://'. config::byKey('synoAddr','synovideo').':'. config::byKey('synoPort','synovideo');
		}else{
			$racineURL='http://'. config::byKey('synoAddr','synovideo').':'. config::byKey('synoPort','synovideo');
		}
		config::save('SYNO.conf.url', $racineURL , 'synovideo');
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
			log::add('synovideo', 'error',' Appel de curl en erreur : ' . $erreur );
			curl_close($ch);
			return $erreur;
		} 
		curl_close($ch);
		return $result;
}
	
	public static function appelURL($API, $method=null, $action=null, $player=null, $value=null, $libre=null,$version=null) {
		//Construit l'URL, l'appel et retourne 
		$url=config::byKey('SYNO.conf.url','synovideo');
		$sessionsid=config::byKey('SYNO.SID.Session','synovideo');
		$arrAPI=config::byKey($API,'synovideo');
		
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
		if($version !== null){
			$apiVersion = $version;
		}
				
		$fURL = $url.'/webapi/'.$apiPath.'?api=' . $API . '&version='.$apiVersion;
			if($method !== null){
				$fURL = $fURL . '&method=' . $method;
			}
			if($action !== null){
				$fURL = $fURL . '&action=' . $action;
			}
			if($player !== null){
				$fURL = $fURL . '&device_id=' . $player;
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
		
		log::add('synovideo', 'debug',' Appel de l\'API : ' . $API . '  url : ' . $fURL );
		//Appel de l'URL
		//$json = file_get_contents($fURL);
		$json = synovideo::getCurlPage($fURL);
		
		$obj = json_decode($json);
		if($obj->success != "true"){
			if( $obj->error->code != "500" ) {
				log::add('synovideo', 'error',' Appel de l\'API : ' . $API . ' en erreur, url : ' . $fURL . ' code : ' . $obj->error->code );
			}
			if( $obj->error->code == "105" || $obj->error->code=="119" ){ // || $obj->error->code=="106" || $obj->error->code=="107" ){
                self::deleteSid();
				if (config::byKey('syno2auth','synovideo')=='') {
                    log::add('synovideo', 'info',' Réinitialisation de la connection ' );
                    self::getSid();
                }else{
                    self::deamon_stop();
                    log::add('synovideo', 'info',' Une nouvelle clé est nécéssaire pour l\'authentification ' );
                }
			}
		}
		return $obj;
	}
	
	public function updateAPIs(){  // Terminée pas touche!
		//Mise à jour des API version et chemin 
		//Get SYNO.API.Auth Path (recommended by Synology for further update)
		log::add('synovideo', 'debug',' Mise à jour des API - Début' );

		$url=config::byKey('SYNO.conf.url','synovideo');
		$list_API = array(
		'SYNO.API.Auth',
		'SYNO.VideoStation2.AcrossLibrary',			
		'SYNO.VideoStation2.Backdrop', 				
		'SYNO.VideoStation2.Collection', 			
		'SYNO.VideoStation2.Controller.Device', 		
		'SYNO.VideoStation2.Controller.Password', 	
		'SYNO.VideoStation2.Controller.Playback',	
		'SYNO.VideoStation2.Controller.Volume',		
		'SYNO.VideoStation2.DTV.Channel',			
		'SYNO.VideoStation2.DTV.ChannelScan',		
		'SYNO.VideoStation2.DTV.DVBSScan',			
		'SYNO.VideoStation2.DTV.Program',			
		'SYNO.VideoStation2.DTV.Schedule',			
		'SYNO.VideoStation2.DTV.Statistic',			
		'SYNO.VideoStation2.DTV.StreamController',	
		'SYNO.VideoStation2.DTV.Streaming',			
		'SYNO.VideoStation2.DTV.StreamingNonAuth',	
		'SYNO.VideoStation2.DTV.Tuner',				
		'SYNO.VideoStation2.File',					
		'SYNO.VideoStation2.Folder',					
		'SYNO.VideoStation2.HomeVideo',				
		'SYNO.VideoStation2.Info',					
		'SYNO.VideoStation2.Library',				
		'SYNO.VideoStation2.Metadata',				
		'SYNO.VideoStation2.Misc',					
		'SYNO.VideoStation2.Movie',					
		'SYNO.VideoStation2.OfflineConversion',		
		'SYNO.VideoStation2.ParentalControl',		
		'SYNO.VideoStation2.PluginSearch',			
		'SYNO.VideoStation2.Poster',					
		'SYNO.VideoStation2.Setting.Folder',			
		'SYNO.VideoStation2.Setting.Network',		
		'SYNO.VideoStation2.Setting.Personal',		
		'SYNO.VideoStation2.Setting.PreAnalysis',	
		'SYNO.VideoStation2.Sharing',				
		'SYNO.VideoStation2.Streaming',				
		'SYNO.VideoStation2.Subtitle',				
		'SYNO.VideoStation2.TVRecording',			
		'SYNO.VideoStation2.TVShow',					
		'SYNO.VideoStation2.TVShowEpisode',
		);
		
		$fURL=$url . '/webapi/query.cgi?api=SYNO.API.Info&method=Query&version=1&query=SYNO.API.Auth,SYNO.VideoStation2.';
		//$json = file_get_contents($fURL);
		
		$json = synovideo::getCurlPage($fURL);
		$obj = json_decode($json);
				
		if($obj->success != "true"){
			log::add('synovideo', 'error', 'Mise à jour des API ' . $API . ' en erreur, url : ' . $fURL . ' , code : ' . $obj->error->code );
		}else{
			foreach ($list_API as $element){
				config::save($element, array (
											"path" => $obj->data->$element->path,
											"version" =>$obj->data->$element->maxVersion
										)
							, 'synovideo');
			}
			log::add('synovideo', 'debug',' Mise à jour des API - OK' );
		}
		log::add('synovideo', 'debug',' Mise à jour des API - Fin' );
	}
	
	public function getSid(){ //fini
        if (config::byKey('SYNO.SID.Session', 'synovideo') != '') {
            log::add('synovideo', 'debug',' La session existe déjà ' );
			return true;
		}    
		log::add('synovideo', 'debug',' Création de la session - Début ' );
		
		$url=config::byKey('SYNO.conf.url','synovideo');
		$login=urlencode(config::byKey('synoUser','synovideo'));
		$pass=urlencode(config::byKey('synoPwd','synovideo'));
        $auth=urlencode(config::byKey('syno2auth','synovideo'));

		$arrAPI=config::byKey('SYNO.API.Auth','synovideo');
			
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
		
		//Login and creating SID
		$fURL = $url.'/webapi/'. $apiPath .'?api=' . $apiName . '&method=Login&version='. $apiVersion .'&account='.$login.'&passwd='.$pass.'&session=VideoStation&format=sid&otp_code=' . $auth . '&enable_device_token=yes';
		//$json = file_get_contents($fURL);
		$json = synovideo::getCurlPage($fURL);
		$obj = json_decode($json);
		if($obj->success != "true"){
			log::add('synovideo', 'error',' Création de la session ' . $apiName . ' en erreur, url : ' . $fURL . ', code : ' . $obj->error->code );
			exit();
		}else{
			//authentification successful
			$sid = $obj->data->sid;
			config::save('SYNO.SID.Session', $sid , 'synovideo');
			log::add('synovideo', 'debug',' Création de la session OK , $sid : ' . $sid);
		}
		log::add('synovideo', 'debug',' Création de la session - Fin ' );
	}
	
	public function deleteSid(){ //fini
		//Logout and destroying SID
		log::add('synovideo', 'debug',' Destruction de la session - Début ');
		$url=config::byKey('SYNO.conf.url','synovideo');
		
		$sessionsid= config::byKey('SYNO.SID.Session','synovideo');
		$arrAPI=config::byKey('SYNO.API.Auth','synovideo');
			
		$apiName = 'SYNO.API.Auth';
		$apiPath = $arrAPI['path'];
		$apiVersion = $arrAPI['version'];
						
		
		if($sessionsid==null){
			log::add('synovideo', 'debug',' Pas de session à détruire ');
		}else{
			$fURL=$url.'/webapi/'.$apiPath.'?api=SYNO.API.Auth&method=Logout&version='.$apiVersion.'&session=VideoStation&_sid='.$sessionsid;
			//$json = file_get_contents($fURL);
			$json = synovideo::getCurlPage($fURL);
			$obj = json_decode($json);
			if($obj->success != "true"){
				log::add('synovideo', 'error',' Destruction de la session en erreur, code : ' . $obj->error->code );
				exit();
			}else{
				//authentification successful
				config::remove('SYNO.SID.Session','synovideo');
				log::add('synovideo', 'debug',' Destruction de la session - OK ');
			}
		}
		log::add('synovideo', 'debug',' Destruction de la session - Fin ');
	}
	
	public function listLibrary (){
		log::add('synovideo', 'debug',' Listage des libraries - Début ');
				
		$obj=self::appelURL('SYNO.VideoStation2.Library','list',null,null,null,null);
		$i=0;
		$arrLibrary=array();
		foreach($obj->data->library as $library ){
			log::add('synovideo', 'debug',' Listage '.$i.' Type '.$library->type .' ID '. $library->id .' Titre '. $library->title );
			
			//$arrLibrary[$library->type][$library->title]=$library->id;
			$arrLibrary[$library->type][]= array (
												"id" => $library->id,
												"title" =>$library->title
											);
		}
		config::save('SYNO.Library.List', $arrLibrary , 'synovideo');
		
		log::add('synovideo', 'debug',' Listage des libraries - Fin ');
	}
	
	public function play($_player='__SYNO_WEB_PLAYER__', $_position=null) { //OK 3/05/2017	
		
		if ($_position != null) {
			$cmd_duration = $this->getCmd(null, 'duration');
			if (is_object($cmd_duration)) {
				$pos= (intval($cmd_duration->execCmd())/intval($_position))/100;
			}
			$position = '&position='. $pos;
		}
	
		self::appelURL('SYNO.VideoStation2.Controller.Playback','pause',null,$_player,$_position,null);	
	}

	public function pause($_player='__SYNO_WEB_PLAYER__') { //OK 3/05/2017	
				
		self::appelURL('SYNO.VideoStation2.Controller.Playback','pause',null,$_player,null,null);
	}
	
	public function stop($_player='__SYNO_WEB_PLAYER__') { //OK 3/05/2017	
	
		self::appelURL('SYNO.VideoStation2.Controller.Playback','stop',null,$_player,null,null);
	}

	public function inplay($_player='__SYNO_WEB_PLAYER__',$_file_id,$_position=null){
	
		$eqLogic = synovideo::byLogicalId($_player, 'synovideo');
		$client_id='';
		$cmd_client_id = $eqLogic->getCmd(null, 'client_id');
		if (is_object($cmd_client_id)) {
			$client_id = $cmd_client_id->execCmd();
		}
		
		$obj=synovideo::subtitle_info($_file_id);
		foreach ($obj->subtitle as $subtitle){
			if($subtitle->id){
				$subtitle_url = '&subtitle=%7B"id":"' . $subtitle->id .'","codepage":"auto"%7D';
			}
			break;
		}
		
		if ($_position != null) {
			$cmd_duration = $this->getCmd(null, 'duration');
			if (is_object($cmd_duration)) {
				$pos= (intval($cmd_duration->execCmd())/intval($_position))/100;
			}
			$position = '&position='. $pos;
		}
		
		$compl_URL='file_id='. $_file_id .'&client_id="'. $client_id .'"&playback_target="file_id"&pin=""'. $subtitle_url . $position;

		self::appelURL('SYNO.VideoStation2.Controller.Playback','play',null,$_player,null,$compl_URL);
	}
	
	public function movie_info($_id ) {

		$compl_URL='id=%5B'. $_id .'%5D&additional=%5B"summary","poster_mtime","backdrop_mtime","file","collection","watched_ratio","conversion_produced","actor","director","genre","writer","extra"%5D'; 		
		$obj=synovideo::appelURL('SYNO.VideoStation2.Movie','getinfo',null,null,null,$compl_URL);
		
		return $obj->data;
	}
	
	public function tvshow_info($_id ) {

		$compl_URL='id=%5B'. $_id .'%5D&additional=%5B"summary","poster_mtime","backdrop_mtime"%5D'; //,"extra"

		$obj=synovideo::appelURL('SYNO.VideoStation2.TVShow','getinfo',null,null,null,$compl_URL);
		return $obj->data;
	}
	
	public function tvshowepisode_list($_id,$_library=0 ) {

		$compl_URL='library_id='.$_library.'&tvshow_id='. $_id .'&limit=500000&additional=%5B"summary","collection","poster_mtime","backdrop_mtime","watched_ratio","file"%5D'; //,"extra"

		$obj=synovideo::appelURL('SYNO.VideoStation2.TVShowEpisode','list',null,null,null,$compl_URL);
		return $obj->data;
	}
	
	public function tvshowepisode_info($_id ) {

		$compl_URL='id=%5B'. $_id .'%5D&additional=%5B"summary","collection","poster_mtime","backdrop_mtime","watched_ratio","file","conversion_produced","actor","director","genre","writer","extra","tvshow_summary"%5D'; //,"extra"
		
		$obj=synovideo::appelURL('SYNO.VideoStation2.TVShowEpisode','getinfo',null,null,null,$compl_URL);
		return $obj->data;
	}

	public function homevideo_info($_id ) {

		$compl_URL='id=%5B'. $_id .'%5D&additional=%5B"summary","poster_mtime","backdrop_mtime","file","collection","watched_ratio","conversion_produced","actor","director","genre","writer","extra"%5D'; //,"extra"
 
 //&additional="%"5B"%"22summary"%"22"%"2C"%"22poster_mtime"%"22"%"2C"%"22backdrop_mtime"%"22"%"2C"%"22file"%"22"%"2C"%"22collection"%"22"%"2C"%"22watched_ratio"%"22"%"2C"%"22conversion_produced"%"22"%"2C"%"22actor"%"22"%"2C"%"22director"%"22"%"2C"%"22genre"%"22"%"2C"%"22writer"%"22"%"2C"%"22extra"%"22"%"5D
 
		$obj=synovideo::appelURL('SYNO.VideoStation2.HomeVideo','getinfo',null,null,null,$compl_URL,'1');
		return $obj->data;
	}

	public function file_info($_fileid){
	
		$compl_URL='id='. $_fileid;
		
		$obj=synovideo::appelURL('SYNO.VideoStation2.Controller.file','get_track_info',null,null,null,$compl_URL);
		return $obj->data;
	}
	
	public function subtitle_info($_fileid){

		$compl_URL='id='. $_fileid;

		
		$obj=synovideo::appelURL('SYNO.VideoStation2.File','get_playback_setting',null,null,null,$compl_URL);
	
		//{"data":{"subtitle":[
		//{"embedded":true,"format":"srt","id":3,"lang":"fre","need_preview":false,"title":"French"},
		//{"embedded":true,"format":"srt","id":4,"lang":"fre","need_preview":false,"title":"French SDH"}]},"success":true}
		
		return $obj->data;
	}
	
	public function mute($_player='__SYNO_WEB_PLAYER__'){  //OK 3/05/2017
		$eqLogic = synovideo::byLogicalId($_player, 'synovideo');
		$cmd_volume = $eqLogic->getCmd(null, 'volume');
		if (is_object($cmd_volume)) {
			cache::set( 'SYNO.tmp.volume'. $eqLogic-> getId() ,$cmd_volume->execCmd(), 240,null) ;
			self::appelURL('SYNO.VideoStation2.Controller.Volume','set',null,$_player,null,'volume=0');
		}
	}
	
	public function unmute($_player='__SYNO_WEB_PLAYER__'){  //OK 3/05/2017
		$eqLogic=synovideo::byLogicalId($_player,'synovideo');
		
		$volume=cache::byKey('SYNO.tmp.volume'. $eqLogic-> getId() );

		if ($volume->getvalue()!= '') {
			self::appelURL('SYNO.VideoStation2.Controller.Volume','set',null,$_player,null,'volume='.$volume->getvalue());
		}
		$volume->remove();
	}

	public function setVolume( $_value,$_player='__SYNO_WEB_PLAYER__'){  //OK 3/05/2017
		
		self::appelURL('SYNO.VideoStation2.Controller.Volume','set',null,$_player,null,'volume='.$_value);
	}
	
	public function setPosition( $_player='__SYNO_WEB_PLAYER__',$_position=null){  //OK 3/05/2017
		
		if ($_position != null) {
			$cmd_duration = $this->getCmd(null, 'duration');
			if (is_object($cmd_duration)) {
				$position= intval((intval($cmd_duration->execCmd())*intval($_position))/100);
			}
		}
		self::appelURL('SYNO.VideoStation2.Controller.Playback','seek',null,$_player,null,'position='.$position);
	}
	
/* Fonctions pas supportées 
	public function prev($player='__SYNO_WEB_PLAYER__') { //Fini
		
		self::appelURL('SYNO.VideoStation.RemotePlayer','control','prev',$player,null,null);
	}
	
	public function next($player='__SYNO_WEB_PLAYER__') { //Fini
		
		self::appelURL('SYNO.VideoStation.RemotePlayer','control','next',$player,null,null);
	}

	public function seek($_player='__SYNO_WEB_PLAYER__', $_position=null ) { // A Fini
		self::appelURL('SYNO.VideoStation.RemotePlayer','control','seek',$_player,$_position,null);	
	}
	
	public function repeat($_player='__SYNO_WEB_PLAYER__',$_repeat=null){ //Fini
		$eqLogic = synovideo::byLogicalId($_player, 'synovideo');
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
			self::appelURL('SYNO.VideoStation.RemotePlayer','control','set_repeat',$_player,$repeat,null);
			
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
		self::appelURL('SYNO.VideoStation.RemotePlayer','control','set_shuffle',$_player,$shuffle,null);
		}
	}
*/		

	
	public static function listing($_type,$_sortby=null,$_keyword=null,$_library=0){
		// récupère la liste des chansons en fonction du mot cle
		log::add('synovideo', 'debug', ' Lancement de la listing ' .$_type.' ' . $_keyword);
		
		$limit=10000;
		$keyword='';
		$sortby = 'sort_by=%22'. $_sortby .'%22&sort_direction=%22desc%22';

		if($_sortby == null){
			$sortby = 'sort_direction=%22asc%22';
		}
		
		if($_keyword != null){
			$limit=100;
			$keyword='&keyword=%22'. $_keyword .'%22';
		}
		
		switch($_type) {
			case 'movie':
				$api='SYNO.VideoStation2.Movie';
				$compl_URL=$sortby . $keyword .'&library_id='.$_library.'&additional=%5B%22poster_mtime%22%2C%22summary%22%2C%22watched_ratio%22%2C%22collection%22%2C%22file%22%5D&offset=0&limit='. $limit;
				break;
			case 'tvshow':
				$api='SYNO.VideoStation2.TVShow';
				$compl_URL=$sortby . $keyword .'&library_id='.$_library.'&additional=%5B%22poster_mtime%22%2C%22summary%22%2C%22backdrop_mtime%22%5D&offset=0&limit='. $limit;
				break;
			case 'home_video':
				$api='SYNO.VideoStation2.HomeVideo';
				$compl_URL=$sortby . $keyword .'&library_id='.$_library.'&additional=%5B%22summary%22%2C%22collection%22%2C%22poster_mtime%22%2C%22watched_ratio%22%2C%22file%22%5D&offset=0&limit='. $limit;
				break;
			case 'tvrecording':
				$api='SYNO.VideoStation2.TVRecording';
				$compl_URL=$sortby . $keyword .'&library_id='.$_library.'&additional=%5B%22summary%22%2C%22collection%22%2C%22poster_mtime%22%2C%22watched_ratio%22%2C%22file%22%5D&offset=0&limit='. $limit;
				break;
			default:
				throw new Exception(__('Type non reconnu', __FILE__));
		}
		
		$obj=self::appelURL($api,'list',null,null,null,$compl_URL);
		
		return $obj->data;
	}
	
	public static function getPoster($_type,$_id){
		
		$img_poster = 'plugins/synovideo/docs/images/syno_poster_' . $_type . '_' . $_id . '.jpg';
		if (!file_exists(dirname(__FILE__) . '/../../../../'. $img_poster)) {
			log::add('synovideo', 'debug', ' Chargement du poster ' . $_type . '_' . $_id );
			$r_url = config::byKey('SYNO.conf.url','synovideo');
			$sessionsid=config::byKey('SYNO.SID.Session','synovideo');
			//				   webapi/entry.cgi?type=tvshow&id=19&api=SYNO.VideoStation2.Poster&method=get&version=1&_sid=pYjje4wG3Nkt.14C0MGN304200
			$poster=$r_url . '/webapi/entry.cgi?api=SYNO.VideoStation2.Poster&method=get&version=1&id=' . $_id . '&type=' . $_type . '&_sid=' . $sessionsid; 
			if (!stristr(synovideo::getCurlPage($poster),'not found')){
				file_put_contents(dirname(__FILE__) . '/../../../../plugins/synovideo/docs/images/syno_poster_' . $_type . '_' . $_id . '.jpg', synovideo::getCurlPage($poster));
			} else {
				log::add('synovideo', 'debug', ' Pas de poster ');
				if (!file_exists(dirname(__FILE__) . '/../../../../'. $img_poster)) {
					unlink(dirname(__FILE__) . '/../../../../'. $img_poster);
				}
				$img_poster='';
			}
		}
		return $img_poster;
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

		$cmd_track_title = $this->getCmd(null, 'movie_title');
		if (is_object($cmd_track_title)) {
			$replace['#title#'] = $cmd_track_title->execCmd();
		}

		if (strlen($replace['#title#']) > 15) {
			$replace['#title#'] = '<marquee behavior="scroll" direction="left" scrollamount="2">' . $replace['#title#'] . '</marquee>';
		}

		$cmd_track_image = $this->getCmd(null, 'movie_image');
		if (is_object($cmd_track_image)) {
			$img=$cmd_track_image->execCmd();
			if (file_exists(dirname(__FILE__) . '/../../../../'. $img) && filesize(dirname(__FILE__) . '/../../../../'. $img) > 110) {
				$replace['#thumbnail#'] = $img . '?time=' .time();
			} else {
				$replace['#thumbnail#'] = 'plugins/synovideo/docs/images/syno_poster_default.png?time=' .time();
			}
		}
	
		$replace['#seekable#'] = $this->getConfiguration('seekable');
		
        $cmd_position = $this->getCmd(null, 'position');
		$replace['#position#'] = intval($cmd_position->execCmd())*1000;
		$cmd_duration = $this->getCmd(null, 'duration');
		$replace['#duration#'] = intval($cmd_duration->execCmd())*1000;
		if (is_object($cmd_position) && is_object($cmd_duration)) {
			if(intval($cmd_duration->execCmd())!= 0){
				$position100= (intval($cmd_position->execCmd())/intval($cmd_duration->execCmd()))*100;
			}else{
				$position100=0;
			}
			$replace['#position100#'] = $position100;
		}

        $replace['#blockVolume#'] = $this->getConfiguration('volume_adjustable');
        $cmd_volume = $this->getCmd(null, 'volume');
		if (is_object($cmd_volume)) {
			$replace['#volume#'] = $cmd_volume->execCmd();
		}
        
		$cmd_setVolume = $this->getCmd(null, 'setVolume');
		if (is_object($cmd_setVolume)) {
			$replace['#volume_id#'] = $cmd_setVolume->getId();
		}
		$cmd_setPosition = $this->getCmd(null, 'setPosition');
		if (is_object($cmd_setPosition)) {
			$replace['#position_id#'] = $cmd_setPosition->getId();
		}
		
		$volume=cache::byKey('SYNO.tmp.volume');
		if (is_object($volume)) {
			$replace['#onmute#'] = true;
		} 

		foreach ($this->getCmd('action') as $cmd) {
			$replace['#cmd_' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
		}

//		$parameters = $this->getDisplay('parameters');
//		if (is_array($parameters)) {
//			foreach ($parameters as $key => $value) {
//				$replace['#' . $key . '#'] = $value;
//			}
//		}
//		
//		$replace['#IsMultiple#'] = $this->getConfiguration('is_multiple');
		
		
		$_version = jeedom::versionAlias($_version);
		return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'synovideo', 'synovideo')));
	}
	
}

class synovideoCmd extends cmd {
	/*     * *************************Attributs****************************** */
	public static $_widgetPossibility = array('custom' => true);
	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function execute($_options = array()) {
		
		$synovideo = $this->getEqLogic();
		log::add('synovideo', 'debug', $synovideo->getHumanName().' Commande ['.$this->getName() . '] id '. $this->getLogicalId() );
		
		switch($this->getLogicalId()) {
			case 'play':
				$synovideo->play($synovideo->getLogicalId());
				break;
			case 'pause':
				$synovideo->pause($synovideo->getLogicalId());
				break;
			case 'stop':
				$synovideo->stop($synovideo->getLogicalId());
				break;
			case 'mute' :
				$synovideo->mute($synovideo->getLogicalId());
				break;
			case 'unmute' :
				$synovideo->unmute($synovideo->getLogicalId());
				break;
			
			case 'setPosition':
				if ($_options['slider'] < 0) {
					$_options['slider'] = 0;
				}
				if ($_options['slider'] > 100) {
					$_options['slider'] = 100;
				}
				$synovideo->setPosition($synovideo->getLogicalId(), $_options['slider']);
				break;
			case 'setVolume':
				if ($_options['volume'] < 0) {
					$_options['volume'] = 0;
				}
				if ($_options['volume'] > 100) {
					$_options['volume'] = 100;
				}
				$synovideo->setVolume($_options['volume'],$synovideo->getLogicalId());
				break;
			case 'ordre':
				$cmd_state = $synovideo->getCmd(null, 'state');
				if (is_object($cmd_state)) {
					if ( $cmd_state->execCmd() == 'Player hors ligne'){
						log::add('synovideo', 'info', ' Le player ' .$synovideo->getName() . ' est hors ligne ');
					}else{
						if (empty($_options['title'])) {
							$type='film'; //Film ou Serie
							log::add('synovideo', 'debug', ' Ordre : Le type est positionné à \'artist\' par défaut.');
						}else {
							$input = $_options['title']; 	// mot a tester
							$words  = array('film','serie','perso');	// tableau de mots à vérifier
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

							log::add('synovideo', 'debug', ' Ordre : Le type est positionné à ' . $type );
						}
						
						if (empty($_options['message'])) {
							log::add('synovideo', 'debug', ' Ordre : Pas de champ de nom pour faire la recherche.');
							break;
						}else {
							$message=str_replace(' ', '%20', $_options['message']); // -> ' ' par %20
							//$message=urlencode($message);
						}
					
						//Ajout liste lecture
						$arrLibrary=config::byKey('SYNO.Library.List','synovideo');
						switch($type) {
							case 'film':
								foreach($arrLibrary['movie'] as $library){
									log::add('synovideo', 'debug',' Listing de la librairie : '. $library['id'] .' , ' .$library['title']  .' avec le mot clé : '.$message );
									$searchmovie=synovideo::listing('movie','added',$message, $library['id']);
									if	( $searchmovie->movie->total!= '0'){
										foreach ( $searchmovie->movie as $movie) {
											log::add('synovideo', 'debug', ' Ordre : Film -> ' . $movie->title );
											foreach ($movie->additional->file as $mfile){
												$idfile=$mfile->id;
												break 1;
											}
											break 1;
										}
										$synovideo->inplay($synovideo->getLogicalId(),$idfile );
									}else{
										log::add('synovideo', 'info', ' Ordre : ' . $type . ' pas de résultat dans la librairie '.$library['title']);
									}
								}
								break;
							case 'serie':
								foreach($arrLibrary['tvshow'] as $library){
									log::add('synovideo', 'debug',' Listing de la librairie : '. $library['id'] .' , ' .$library['title'] .' avec le mot clé : '.$message );
									$searchtvshow=synovideo::listing('tvshow','added',$message);
									if ($searchtvshow->total != '0'){
										foreach ( $searchtvshow->tvshow as $tvshow) {
											log::add('synovideo', 'debug', ' Ordre : Série -> ' . $tvshow->title);
											$searchtvshowepisode=synovideo::tvshowepisode_list($tvshow->id,$tvshow->library_id);
											if ($searchtvshowepisode->total != '0'){
												foreach ( $searchtvshowepisode->episode as $episode) {
													foreach ($episode->additional->file as $mfile){
														$idfile=$mfile->id;
														break 1;
													}
													if(intval($episode->additional->watched_ratio * 100) < 90 ){
														break 2;
													}
												}
											}
											break 1;
										}
										$synovideo->inplay($synovideo->getLogicalId(),$idfile );
									}else {
										log::add('synovideo', 'info', ' Ordre : ' . $type . ' pas de résultat dans la librairie '.$library['title']);
									}
								}
								break;
							case 'perso':
								foreach($arrLibrary['home_video'] as $library){
									log::add('synovideo', 'debug',' Listing de la librairie : '. $library['id'] .' , ' .$library['title'] .' avec le mot clé : '.$message );
									$searchhomevideo=synovideo::listing('home_video','added',$message,$library['id']);
									if ($searchhomevideo->total != '0'){
										foreach ( $searchhomevideo->video as $homevideo) {
											log::add('synovideo', 'debug', ' Ordre : Perso -> ' . $homevideo->title);
											foreach ($homevideo->additional->file as $mfile){
												$idfile=$mfile->id;
												break 1;
											}
											break 1;
										}
										
										log::add('synovideo', 'debug', ' idfile : ' . $idfile  );
										$synovideo->inplay($synovideo->getLogicalId(),$idfile );
										break 1;
									}else{
										log::add('synovideo', 'info', ' Ordre : ' . $type . ' pas de résultat dans la librairie '.$library['title']);
									}
								}
								break;
							default:
								break;
						}	
					}
				}
				break;
			case 'clean':
				$synovideo->purgePlugin();
				$synovideo->listLibrary();
				break;
			case 'tache':
				if (empty($_options['message'])) {
					log::add('synovideo', 'debug', ' la commande Tache n\'a pas de paramêtre.');
				}else {
					$synovideo->tache_deamon($_options['message']);
				}
				break;
			default:
				throw new Exception(__('Commande non reconnu', __FILE__));
		}
		return false;
	}

	/*     * **********************Getteur Setteur*************************** */
}
