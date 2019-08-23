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
include_file('desktop', 'modal.syno', 'css', 'synovideo');
?>

<div id='div_searchmovieSynoAlert' style="display: none;"></div>
<div class="form-group">
	<div class="col-sm-9">
		<input class="form-control input-sm txtsearchmovie" type="text" placeholder="Recherche"<?php if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ){echo 'value="'. str_replace('%20', ' ',$_GET['keyword']).'"';}?> />
	</div>
	<a class="btn btn-success btn-search" id="bt_validSearch" data-syno_id="<?php echo init('id');?>"><i class="fas fa-search"></i></a>
</div>

<div id="div_result" style="margin-top: 5px;height:calc(100% - 120px)">
	<div class="tabbed_area">   
		<ul class="tabs">
			<li><a class="btn tab btn-success" id="tab_movie" >Film</a></li>
			<li><a class="btn tab btn-noselect" id="tab_tvshow"  >Série</a></li>
			<li><a class="btn tab btn-noselect" id="tab_homevideo" >Vidéo perso</a></li>
			<li><a class="btn tab btn-noselect" id="tab_tvrecording" >Enregistrement TV</a></li>
		</ul>
	
		<div class="content" id="content_movie" >
			<?php
				$arrLibrary=config::byKey('SYNO.Library.List','synovideo');
			
				if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
					foreach($arrLibrary['movie'] as $library){
						log::add('synovideo', 'debug',' Recherhe dans la librairie : '. $library['id'] .' , ' .$library['title'] );

						$searchmovie=synovideo::listing('movie','added',$_GET['keyword'],$library['id']);
						if ($searchmovie->total != '0'){
							foreach ( $searchmovie->movie as $movie) {
								foreach ($movie->additional->file as $mfile){
									$idfile=$mfile->id;
								}
								$img_poster=synovideo::getPoster('movie',$movie->id);
								echo '<div class="result" >';
								echo '	<div class="btn resultposter movieinfo" style="background-image:url('. $img_poster . ');" data-syno_id="' . init('id') . '" data-video_id="'. $movie->id .'" data-file_id="'. $idfile .'">';
								echo '		<a class="btn btn-xs btn-noselect bt_play" data-syno_id="' . init('id') . '" data-file_id="'. $idfile .'">
												<i class="fas fa-play">&nbsp&nbsp' . $movie->title . '&nbsp</i>
											</a>';
								echo '	</div>';
								echo '	<div  class="watch-status" >
											<div class="grey-bar">
											<div class="blue-bar" style="width:'. intval($movie->additional->watched_ratio * 100) .'%"></div>
										</div></div>';
								echo '</div>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
						}
					}
				}else{
					echo '<tr>';
					echo '<th colspan=2 > {{ Vous pouvez lancer une recherche}}</th>';
					echo '</tr>';
				}
			?>
		</div>
		<div class="content" id="content_tvshow" >				
			<?php
				if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
					foreach($arrLibrary['tvshow'] as $library){
						log::add('synovideo', 'debug',' Recherhe dans la librairie : '. $library['id'] .' , ' .$library['title'] );
						$searchtvshow=synovideo::listing('tvshow','added',$_GET['keyword'],$library['id']);
						if ($searchtvshow->total != '0'){
							foreach ( $searchtvshow->tvshow as $tvshow) {
								$img_poster=synovideo::getPoster('tvshow',$tvshow->id);
								echo '<div class="result" >';
								echo '	<div class="btn resultposter bt_tvshow" style="background-image:url('. $img_poster . ');" data-syno_tvshow_id="' . $tvshow->id . '" data-syno_id="' . init('id') . '" data-file_added="'. $i .'" data-file_alpha="'. str_replace(' ', '_',substr($tvshow->title,0,10)) .'">';
								echo '		<a class="bt_tvshow btn btn-xs btn-noselect" data-syno_tvshow_id="' . $tvshow->id . '" data-syno_id="' . init('id') . '"><i class="fas fa-plus">&nbsp&nbsp' . $tvshow->title . '&nbsp</i></a>';
								echo '	</div>';
								echo '</div>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
						}
					}
				}else{
					echo '<tr>';
					echo '<th colspan=2 > {{ Vous pouvez lancer une recherche}}</th>';
					echo '</tr>';
				}
			?>
		</div>
		<div class="content" id="content_tvshow_detail">
			<?php
				if ($searchtvshow->total != '0'){
					foreach ( $searchtvshow->tvshow as $tvshow) {
						echo '<div class="result_episode" id="tvshow_'. $tvshow->id .'" style="display: none;" >';
						echo '	<div class="titre_episode" >';
						echo '		<a class="btn btn-xs btn-success bt_retour_tvshow" ><i class="fas fa-arrow-left">&nbsp&nbsp retour &nbsp</i></a>';
						echo '	&nbsp&nbsp' . $tvshow->title . '&nbsp';
						echo '	</div>';
						$searchtvshowepisode=synovideo::tvshowepisode_list($tvshow->id,$tvshow->library_id);
						if ($searchtvshowepisode->total != '0'){
							foreach ( $searchtvshowepisode->episode as $episode) {
								foreach ($episode->additional->file as $mfile){
									$idfile=$mfile->id;
								}
								$img_poster=synovideo::getPoster('tvshow_episode',$episode->id);
								echo '<div class="result episode" >';
								echo '	<div class="btn resultposter showinfo episode" style="background-image:url('. $img_poster . ');" data-syno_id="' . init('id') . '" data-video_id="'. $episode->id .'" data-file_id="'. $idfile .'">';
								echo '		<a class="bt_play btn btn-xs btn-noselect" data-syno_id="' . init('id') . '" data-file_id="'. $idfile .'">
												<i class="fas fa-play">&nbspS'. $episode->season .'E'. $episode->episode .'&nbsp'. $episode->tagline  .'&nbsp</i>
											</a>';
								echo '	</div>';
								echo '	<div  class="watch-status" >
											<div class="grey-bar">
											<div class="blue-bar" style="width:'. intval($episode->additional->watched_ratio * 100) .'%"></div>
										</div></div>';
								echo '</div>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
						}
						echo '</div>';
					}
				}
			?>
		</div>
		<div class="content" id="content_homevideo" >
			<?php
				if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
					foreach($arrLibrary['home_video'] as $library){
						log::add('synovideo', 'debug',' Recherhe dans la librairie : '. $library['id'] .' , ' .$library['title'] );
						$searchhomevideo=synovideo::listing('home_video','added',$_GET['keyword'],$library['id']);
						if ($searchhomevideo->total != '0'){
							foreach ( $searchhomevideo->video as $homevideo) {
								foreach ($homevideo->additional->file as $mfile){
									$idfile=$mfile->id;
								}
								$img_poster=synovideo::getPoster('home_video',$homevideo->id);
								echo '<div class="result" style="background-image:url('. $img_poster . ');">';
								echo '	<a class="bt_play btn btn-xs btn-noselect" data-syno_id="' . init('id') . '" data-file_id="'. $idfile .'">
											<i class="fas fa-play">&nbsp&nbsp' . $homevideo->title . '&nbsp</i>
										</a>';
								echo '</div>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
						}
					}
				}else{
					echo '<tr>';
					echo '<th colspan=2 > {{ Vous pouvez lancer une recherche}}</th>';
					echo '</tr>';
				}
			?>
		</div> 
		<div class="content" id="content_tvrecording" >
			<?php
				if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
					foreach($arrLibrary['tv_record'] as $library){
						log::add('synovideo', 'debug',' Recherhe dans la librairie : '. $library['id'] .' , ' .$library['title'] );
						$searchtvrecording=synovideo::listing('tvrecording','added',$_GET['keyword'],$library['id']);
						if ($searchtvrecording->total != '0'){
							foreach ( $searchtvrecording->recording as $tvrecording) {
								foreach ($tvrecording->additional->file as $mfile){
									$idfile=$mfile->id;
								}
								$img_poster=synovideo::getPoster('tvrecording',$tvrecording->id);
								echo '<div class="result" style="background-image:url('. $img_poster . ');">';
								echo '	<a class="bt_play btn btn-xs btn-noselect" data-syno_id="' . init('id') . '" data-file_id="'. $idfile .'">
											<i class="fas fa-play">&nbsp&nbsp' . $tvrecording->title . '&nbsp</i>
										</a>';
								echo '</div>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
						}
					}
				}else{
					echo '<tr>';
					echo '<th colspan=2 > {{ Vous pouvez lancer une recherche}}</th>';
					echo '</tr>';
				}
			?>
		</div>  
	</div>
</div>


<script>
$('#tab_movie').on('click',function(){
	ChangeOnglet('tab_movie', 'content_movie')
});
$('#tab_tvshow').on('click',function(){
	ChangeOnglet('tab_tvshow', 'content_tvshow')
});
$('#tab_homevideo').on('click',function(){
	ChangeOnglet('tab_homevideo', 'content_homevideo')
});
$('#tab_tvrecording').on('click',function(){
	ChangeOnglet('tab_tvrecording', 'content_tvrecording')
});

function ChangeOnglet(onglet, contenu) {   
	$('.content').css('display','none');
	$('.result_episode').css('display','none');
	$('#'+ contenu ).css('display','block');	

	$('.tab').removeClass('btn-success').addClass('btn-noselect');
	$('#' + onglet).removeClass('btn-noselect').addClass('btn-success');
}
$('.content').on('click','.movieinfo',function(){
	var id = $(this).attr('data-syno_id');
	var movietype = "movie";
	var video_id = $(this).attr('data-video_id');
	var file_id = $(this).attr('data-file_id');
	$('#md_modal').dialog({title: "Infos lecture"});
	$('#md_modal').load('index.php?v=d&plugin=synovideo&modal=movieinfo.syno&id=' + id + '&movietype=' + movietype + '&video_id=' + video_id +'&file_id=' + file_id).dialog('open');
});

$('.content').on('click','.showinfo',function(){
	var id = $(this).attr('data-syno_id');
	var movietype = "tvshow_episode";
	var video_id = $(this).attr('data-video_id');
	var file_id = $(this).attr('data-file_id');
	$('#md_modal').dialog({title: "Infos lecture"});
	$('#md_modal').load('index.php?v=d&plugin=synovideo&modal=movieinfo.syno&id=' + id + '&movietype=' + movietype + '&video_id=' + video_id +'&file_id=' + file_id).dialog('open');

});

$('.bt_tvshow').on('click',function(){
	if ($(this).attr('data-syno_tvshow_id')){
		var tvshow =  $(this).attr('data-syno_tvshow_id');
		$('#content_tvshow').css('display','none');
		$('#content_tvshow_detail').css('display','block');
		$('#tvshow_' + tvshow ).css('display','block');
	}
});

$('.bt_retour_tvshow').on('click',function(){
	$('#content_tvshow').css('display','block');
	$('#content_tvshow_detail').css('display','none');
	$('.result_episode').css('display','none');	
});


$('#bt_validSearch').on('click',function(){
	var id = $(this).attr('data-syno_id');
	var keyword = $('.txtsearchmovie').val();
	var keyword = keyword.replace(/ /g,'%20');
	$('#md_modal2').load('index.php?v=d&plugin=synovideo&modal=search.syno&id=' + id + '&keyword=' + keyword).dialog('open');
});

$('.content').on('click','.bt_play',function(){	
	//$('#div_searchmovieSynoAlert').showAlert({message: 'test bt_play', level: 'danger'});
	
	var syno_id = $(this).attr('data-syno_id');
	var file_id = $(this).attr('data-file_id');
	
	//$('#div_searchmovieSynoAlert').showAlert({message: syno_id + ' ' + file_id, level: 'danger'});
	
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
		}
	});
	
});

</script>




