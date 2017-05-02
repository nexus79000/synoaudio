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
?>
<div id='div_searchsongSynoAlert' style="display: none;"></div>
<div class="form-group">
	<div class="col-sm-9">
		<input class="form-control input-sm txtsearchsong" type="text" placeholder="Recherche"<?php if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ){echo 'value="'. str_replace('%20', ' ',$_GET['keyword']).'"';}?> />
	</div>
	<a class="btn btn-success btn-search" id="bt_validSearch" data-syno_id="<?php echo init('id');?>"><i class="fa fa-search"></i></a>
	
</div>

<div id="div_result" style="margin-top: 5px;height:calc(100% - 120px)">
<!-- List Artiste -->
	<div id="artiste" style="overflow:auto; height: 40%; width: 36%; position: absolute; ">
		<table class="table table-condensed">
			<thead>
				<tr>
					<!--<th style="width : 60px;">{{Check}}</th>-->
					<th style="width : 60px;"></th>
					<th>{{Artistes}}</th>
				</tr>
			</thead>
			<tbody>
				<?php
					if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
						$result=synoaudio::searchSong($_GET['keyword']);
						if ($result->artistTotal != '0'){
							foreach ( $result->artists as $artist) {
								echo '<tr>';
								echo '<td>';
								echo '<a class="bt_playArtist btn btn-xs btn-success" data-syno_id="' . init('id') . '" data-name="' . $artist->name . '"><i class="fa fa-play"></i></a>';
								echo '</td>';
								echo '<td>';
								echo $artist->name;
								echo '</td>';
							}
						}else{
							echo '<tr>';
							echo '<th colspan=2 > {{ Rien à afficher}}</th>';
							echo '</tr>';
					}
					}else{
						echo '<tr>';
						echo '<th colspan=2 > {{ Vous pouvez lancer une recherche}}</th>';
						echo '</tr>';
					}
				?>			
		</tbody>
		</table>
	</div>
	<!-- List Album -->
	<div id="album" style="overflow:auto; height: 40%; width: 61%; position: absolute; left: 38%; ">
		<table class="table table-condensed">
			<thead>
				<tr>
					<!--<th style="width : 60px;">{{Check}}</th>-->
					<th style="width : 60px;"></th>
					<th>{{Albums}}</th>
					<th>{{Artistes}}</th>
				</tr>
			</thead>
			<tbody>
				<?php
					if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
						//$result=synoaudio::searchSong($_GET['keyword']);
						if ($result->albumTotal != '0'){
							$i=1;
							foreach ( $result->albums as $album) {
								echo '<tr>';
								echo '<td>';
								echo '<a class="bt_playAlbum btn btn-xs btn-success" data-syno_id="' . init('id') . '" data-name="' . $album->name . '" data-artist="' . $album->album_artist . '"data-syno_id='. $i .'"><i class="fa fa-play"></i></a>';
								echo '<a class="bt_AffAlbum btn btn-xs btn-success"  data-alb_id="'. $i .'"><i class="fa fa-arrow-down"></i></a>';
								echo '</td>';
								echo '<td>';
								echo $album->name;
								echo '</td>';
								echo '<td>';
								echo $album->album_artist;
								echo '</td>';
								$i++;
							}
						}else{
							echo '<tr>';
							echo '<th colspan=3 > {{ Rien à afficher}}</th>';
							echo '</tr>';
					}
					}else{
						echo '<tr>';
						echo '<th colspan=3 > {{ Vous pouvez lancer une recherche}}</th>';
						echo '</tr>';
					}
				?>			
		</tbody>
		</table>
	</div>

<?php
// Affichage des chanson par Album
	if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
		if ($result->albumTotal != '0'){
			$i=1;
			foreach ( $result->albums as $album) {
?>
	<div id="chanson_album<?php echo $i;?>" class="listsong" style="overflow:auto; height: 50%; width: 98%; position: absolute; top: 50%; display:none;">
		<table class="table table-condensed">
			<thead>
				<tr>
				<th style="width : 60px;"><a class="btn btn-xs btn-success" id="bt_playSong" data-syno_id="<?php echo init('id');?>"><i class="fa fa-play"></i></a></th>
				<th>{{Chansons}}</th>
				<th>{{Artistes}} <?php $album->album_artist ?>  </th>
				<th>{{Albums}} <?php $album->name ?> </th>
				</tr>
			</thead>
			<tbody>
				<?php
					if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
						$gsong=synoaudio::getSong($album->name, $album->album_artist );
						foreach ( $gsong->songs as $song) {
							echo '<tr>';
							echo '<td>';
							echo '<input type="checkbox" class="configKey tooltips form-control checkbox song" data-name="' . $song->id . '" style="height: 20px ! important;" /> ';
							echo '</td>';
							echo '<td>';
							echo $song->title;
							echo '</td>';
							echo '<td>';
							echo $song->additional->song_tag->artist;
							echo '</td>';
							echo '<td>';
							echo $song->additional->song_tag->album;
							echo '</td>';
							echo '</tr>';
						}
					}else{
						echo '<tr>';
						echo '<th colspan=3 > {{ Vous pouvez lancer une recherche}}</th>';
						echo '</tr>';
					}
				?>
		</tbody>
		</table>
	</div>
<?php
// Fin Affichage des chanson par Album
			$i++;
			} //fin for
		}	//fin if
	}	//fin if
?>	
	
<!-- List Chanson -->
<div id="chanson" class="listsong" style="overflow:auto; height: 50%; width: 98%; position: absolute; top: 50%;">
	<table class="table table-condensed">
		<thead>
			<tr>
				<!--<th style="width : 60px;">{{Check}}</th>-->
				<th style="width : 60px;"><a class="btn btn-xs btn-success" id="bt_playSong" data-syno_id="<?php echo init('id');?>"><i class="fa fa-play"></i></a></th>
				<th>{{Chansons}}</th>
				<th>{{Artistes}}</th>
				<th>{{Albums}}</th>
			</tr>
		</thead>
		<tbody>
			<?php
			//Liste artiste
			
			//Liste Album
			
			
			//Liste Chanson
				if ( isset($_GET['keyword']) && $_GET['keyword']!= '' ) {
					//$result=synoaudio::searchSong($_GET['keyword']);
					if ($result->songTotal != '0'){
						foreach ( $result->songs as $song) {
							echo '<tr>';
							echo '<td>';
							echo '<input type="checkbox" class="configKey tooltips form-control checkbox song" data-name="' . $song->id . '" style="height: 20px ! important;" /> ';
							echo '</td>';
							echo '<td>';
							echo $song->title;
							echo '</td>';
							echo '<td>';
							echo $song->additional->song_tag->artist;
							echo '</td>';
							echo '<td>';
							echo $song->additional->song_tag->album;
							echo '</td>';
							echo '</tr>';
						}
					}else{
						echo '<tr>';
						echo '<th colspan=4 > {{ Rien à afficher}}</th>';
						echo '</tr>';
				}
				}else{
					echo '<tr>';
					echo '<th colspan=4 > {{ Vous pouvez lancer une recherche}}</th>';
					echo '</tr>';
				}
			?>			
	</tbody>
	</table>
</div>


<script>

$('#bt_validSearch').on('click',function(){
	var id = $(this).attr('data-syno_id');
	var keyword = $('.txtsearchsong').val();
	var keyword = keyword.replace(/ /g,'%20');
	$('#md_modal2').load('index.php?v=d&plugin=synoaudio&modal=searchsong.syno&id=' + id + '&keyword=' + keyword).dialog('open');
});

$('.bt_playArtist').on('click',function(){
    var id = $(this).attr('data-syno_id');
	var name = $(this).attr('data-name');
	//$('#div_searchsongSynoAlert').showAlert({message: id, level: 'danger'})
    $.ajax({// fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
		data: {
		action: "playsearchartist",
		id :id,
		artist : name
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error,$('#div_searchsongSynoAlert'));
		},
		success: function (data) { // si l'appel a bien fonctionné
			if (data.state != 'ok') {
				//$('#div_searchsongSynoAlert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			$('#md_modal2').dialog('close');
		}
	});		
});

$('.bt_playAlbum').on('click',function(){
    var id = $(this).attr('data-syno_id');
	var name = $(this).attr('data-name');
	var artist = $(this).attr('data-artist');
	//$('#div_searchsongSynoAlert').showAlert({message: id, level: 'danger'})
    $.ajax({// fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
		data: {
		action: "playsearchalbum",
		id :id,
		album : name,
		artistalbum : artist
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error,$('#div_searchsongSynoAlert'));
		},
		success: function (data) { // si l'appel a bien fonctionné
			if (data.state != 'ok') {
				//$('#div_searchsongSynoAlert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			$('#md_modal2').dialog('close');
		}
	});		
});

$('.bt_AffAlbum').on('click',function(){
	var alb_id = $(this).attr('data-alb_id');
	
	var x = document.getElementsByClassName("listsong");
	var i;
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "none";
	}

	var div_id = 'chanson_album' + alb_id;
	document.getElementById(div_id).style.display = "block";
});

	
 $('#bt_playSong').on('click',function(){
    var id = $(this).attr('data-syno_id');
	//$('#div_searchsongSynoAlert').showAlert({message: id, level: 'danger'})
    $(':checkbox.song').each(function () {
        var ischecked = $(this).is(':checked');
        if (ischecked) {
			//checkbox_value += $(this).attr('data-name') + "|";
			var name = $(this).attr('data-name');
	//$('#div_searchsongSynoAlert').showAlert({message: name, level: 'danger'})
			$.ajax({// fonction permettant de faire de l'ajax
				type: "POST", // methode de transmission des données au fichier php
				url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
				data: {
					action: "playsearchsong",
					id :id,
					song : name
				},
				dataType: 'json',
				error: function (request, status, error) {
					handleAjaxError(request, status, error,$('#div_searchsongSynoAlert'));
				},
				success: function (data) { // si l'appel a bien fonctionné
					if (data.state != 'ok') {
						//$('#div_searchsongSynoAlert').showAlert({message: data.result, level: 'danger'});
						return;
					}
				}
			});
	    }
    });
});

</script>




