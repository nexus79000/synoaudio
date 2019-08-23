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
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$synovideo = synovideo::byId(init('id'));
if (!is_object($synovideo)) {
	$players=synovideo::byType('synovideo', true);
	$player=null;
}else{
	$players=null;
	$player=$synovideo->getLogicalId();
}

include_file('desktop', 'modal.syno', 'css', 'synovideo');
?>

<div id='div_searchmovieSynoAlert' style="display: none;"></div>

<div id="div_result" >
	<div class="content" id="content_movie" >
	<?php
		$ignore=true;
		if ( isset($_GET['movietype']) && $_GET['movietype']!= '' && isset($_GET['video_id']) && $_GET['video_id']!= '' ) {
			$video_type=$_GET['movietype'];
			$video_id=$_GET['video_id'];
			$ignore=false;
		}else{
			$cmd_state = $synovideo->getCmd(null, 'state');			
			if (is_object($cmd_state)) {
				if ( $cmd_state->execCmd() != 'Player hors ligne'){
					$obj=synovideo::appelURL('SYNO.VideoStation2.Controller.Playback','status',null,$player,null,null);
					if($obj->data->state!='STOPPED'){
						$ignore=false;
						$video_type=$obj->data->type;
						$video_id=$obj->data->video_id;
					}
				}
			}
		}
			
		if(!$ignore){
			switch($video_type) {
				case 'movie':
					$obj_movie=synovideo::movie_info($video_id);
					foreach ($obj_movie->movie as $movie){
						$titre=$movie->title;
						$titre_episode=$movie->season .'x'. $movie->episode .' : '. $movie->tagline;
						foreach ($movie->additional->genre as $genre){
							$genres = $genres . ' ' . $genre;
						}
						$resume=$movie->additional->summary;
						foreach ($movie->additional->actor as $acteur){
							$acteurs = $acteurs . ', ' . $acteur;
						}
						foreach ($movie->additional->file as $file){
							$duration = $file->duration;
							$filesize = synovideo::convertOctet(intval($file->filesize));
							$resolution = $file->resolutionx .' x '. $file->resolutiony;
							$video_codec = $file->video_codec;
							$audio_codec = $file->audio_codec;
						}
						$watched_ratio=$movie->additional->watched_ratio;
						$img_poster=synovideo::getPoster($video_type,$movie->id);
					}
					break;
				case 'tvshow_episode':
					$obj_episode=synovideo::tvshowepisode_info($video_id);
					foreach ($obj_episode->episode as $episode){
						$titre=$episode->title;
						$titre_episode=$episode->season .'x'. $episode->episode .' - '. $episode->tagline;
						foreach ($episode->additional->genre as $genre){
							$genres = $genres . ' ' . $genre;
						}
						$resume=$episode->additional->summary;
						foreach ($episode->additional->actor as $acteur){
							$acteurs = $acteurs . ' ' . $acteur;
						}
						foreach ($episode->additional->file as $file){
							$duration = $file->duration;
							$filesize = synovideo::convertOctet(intval($file->filesize));
							$resolution = $file->resolutionx .' x '. $file->resolutiony;
							$video_codec = $file->video_codec;
							$audio_codec = $file->audio_codec;
						}
						$watched_ratio=$episode->additional->watched_ratio;
						$img_poster=synovideo::getPoster($video_type,$episode->id);
					}
					break;
				case 'home_video':
					$obj_homevideo=synovideo::homevideo_info($video_id);
					foreach ($obj_homevideo->video as $hvideo){
						$titre=$hvideo->title;
						foreach ($hvideo->additional->genre as $genre){
							$genres = $genres . ' ' . $genre;
						}
						$resume=$hvideo->additional->summary;
						foreach ($hvideo->additional->actor as $acteur){
							$acteurs = $acteurs . ' ' . $acteur;
						}
						foreach ($hvideo->additional->file as $file){
							$duration = $file->duration;
							$filesize = synovideo::convertOctet(intval($file->filesize));
							$resolution = $file->resolutionx .' x '. $file->resolutiony;
							$video_codec = $file->video_codec;
							$audio_codec = $file->audio_codec;
						}
						$watched_ratio=$hvideo->additional->watched_ratio;
						$img_poster=synovideo::getPoster($video_type,$hvideo->id);
					}
					break;
				case 'tv_record':
					$genres = __('Aucun', __FILE__);
					log::add('synovideo', 'debug', 'Pas d\'information sur le type '. $video_type);
					break;
				default:
					throw new Exception(__('Type non reconnu', __FILE__));
			}
			
			echo '		<div class="commande" >';
			if(isset($_GET['file_id']) && $_GET['file_id']!= ''){
				if ($player!=null){
					echo '		<a class="btn btn-success bt_play" data-syno_id="'. init('id') .'" data-file_id="'. $_GET['file_id'].'"><i class="fas fa-play"></i></a>';
				}else{
					foreach ( $players as $eqLogic ){
						echo '		<a class="btn btn-success bt_play" data-syno_id="'. $eqLogic->getId() .'" data-file_id="'. $_GET['file_id'].'"><i class="fas fa-play">'. $eqLogic->getName() .'</i></a>';
					}
				}	
			}
			echo '			<a class="btn btn-danger bt_close" ><i class="fas fa-times"> {{Fermer}}</i></a>';
			echo '		</div>';
			
			echo '	<div class="info" >';
			if ($video_type=='tvshow_episode' || $video_type=='home_video'){$episode='episode';}
			echo '		<div class="result '. $episode .'" >';
			echo '			<div class="resultposter '. $episode .'" style="background-image:url('.$img_poster .');">';
			echo '			</div>';
			echo '			<div  class="watch-status" >';
			echo '				<div class="grey-bar">';
			echo '					<div class="blue-bar" style="width:'.intval($movie->additional->watched_ratio * 100) .'%"></div>';
			echo '				</div>';
			echo '			</div>';
			echo '		</div>';
			
			echo '		<div class="infofile '. $episode .'" >';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Titre}} : </label>';
			echo '				<label class="lbl-info">'. $titre .'</label>';
			echo '			</div>';
			if ($video_type=='tvshow_episode'){
				echo '				<div class="info-label">';
				echo '					<label class="lbl-libelle">{{Episode}} : </label>';
				echo '					<label class="lbl-info">'.$titre_episode .'</label>';
				echo '				</div>';
			}
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Durée}} : </label>';
			echo '				<label class="lbl-info">'. $duration .'</label>';
			echo '			</div>';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Résolution}} : </label>';
			echo '				<label class="lbl-info">'. $resolution .'</label>';
			echo '			</div>';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Taille}} : </label>';
			echo '				<label class="lbl-info">'. $filesize .'</label>';
			echo '			</div>';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Codec}} : </label>';
			echo '				<label class="lbl-info">'. $video_codec .', '.$audio_codec .'</label>';
			echo '			</div>';
			echo '		</div>';
			
			echo '		<div class="resultinfo '. $episode .'">';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Résumé}} : </label>';
			echo '				<label class="lbl-info">'. $resume .'</label>';
			echo '			</div>';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Genres}} : </label>';
			echo '				<label class="lbl-info">'. $genres .'</label>';
			echo '			</div>';
			echo '			<div class="info-label">';
			echo '				<label class="lbl-libelle">{{Acteurs}} : </label>';
			echo '				<label class="lbl-info">'. $acteurs .'</label>';
			echo '			</div>';
			echo '		</div>';
			echo '	</div>';
		}else{
				echo '<tr>';
				echo '<th colspan=2 > {{ Rien à afficher}}</th>';
				echo '</tr>';
		}
	?> 
	</div>
</div>


<script>
$('.bt_play').on('click',function(){
	//$('#div_searchmovieSynoAlert').showAlert({message: 'test bt_play', level: 'danger'});
	
	var syno_id = $(this).attr('data-syno_id');
	var file_id = $(this).attr('data-file_id');

// A Supprimer ('#div_searchmovieSynoAlert').showAlert({message: syno_id + ' ' + file_id, level: 'danger'});
	
	$.ajax({// fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/synovideo/core/ajax/synovideo.ajax.php", // url du fichier php
		data: {
			action : "inplay",
			id : syno_id,
			fileid : file_id
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error,$('#div_searchmovieSynoAlert'));
		},
		success: function (data) { // si l'appel a bien fonctionné
			if (data.state != 'ok') {
				$('#div_searchmovieSynoAlert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			$('#md_modal2').dialog('close');
			$('#md_modal').dialog('close');
		}
	});
	
});

$('.bt_close').on('click',function(){
	$('#md_modal').dialog('close');
});
</script>

