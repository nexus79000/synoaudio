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
$syno = synoaudio::byId(init('id'));
if (!is_object($syno)) {
	throw new Exception("Equipement non trouvé");
}
?>
<div id='div_playerSynoAlert' style="display: none;"></div>
<a class="btn btn-success eqLogicAction pull-right " id="bt_savePlayer" data-syno_id="<?php echo init('id');?> ><i class="fas fa-check-circle"></i> {{Valider}}</a>
<table class="table table-condensed ">
	<thead>
		<tr>
			<th style="width : 100px;">{{Actif}}</th>
			<th style="width : 300px;">{{Player}}</th>
			<th>{{Volume}}</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$i=0;
		//foreach (synoaudio::getPlayers() as $player) {
		foreach (synoaudio::byType('synoaudio') as $player){
			if ($player->getConfiguration('type') == 'airplay' && $player->getlogicalId() != '__SYNO_Multiple_AirPlay__' ){
				$logicId=$player->getlogicalId();
				$check='';
				$volume=0;
				
				$subplayer=config::byKey('SYNO.subplayer','synoaudio');
				if (isset($subplayer[$logicId])){  // != null){
					$check='checked';
					$volume=$subplayer[$logicId];
				}
				echo '<tr>';
				echo '<td>';
				echo '<form class="form-horizontal"><fieldset>'; 
				echo '<div class="eqlogic synoaudio form-group" data-eqLogic_id=' . $player-> getId() . '>';
				echo '	<div class="col-sm-9">';
				echo '		<input type="checkbox" onClick="checkPlayer();" class="checkbox eqLogicAttr checkbox" data-syno_id="' . init('id') . '" data-eqLogic_id=' . $player-> getId() . ' name="' . $player->getName() . '" data-l1key="isEnable" '. $check .'/>';
				echo '	</div>';
				echo '</div></fieldset></form>';
				echo '</td>';
				echo '<td>';
				echo $player->getName();
				echo '</td>';
				echo '<td>';
					//volume
				echo '<div class="eqlogic synoaudio" data-eqLogic_id="' . $player-> getId() . '">';
				echo '<span class="volume vairplay" style="z-index:1" value="'. $volume .'" id="' . $player->getName() . '"  >';
				echo '</div>';
				echo '<script>';
				echo '$(".synoaudio[data-eqLogic_id=' . $player-> getId() . '] .volume").bootstrapSlider({ min: 0, max: 100, value: (\''. $volume .'\' == \'\') ? 0 : parseInt(\''. $volume .'\'), reversed : false });';
				echo '$(".synoaudio[data-eqLogic_id=' . $player-> getId() . '] .slider.slider-horizontal").css(\'z-index\',1);';
				echo '$(".synoaudio[data-eqLogic_id=' . $player-> getId() . '] .volume").on(\'slideStop\', function () { ';
				echo '		var id = ' . init('id') . '; ';
				echo '		savePlayer(id,"false"); ';
				echo '}); ';
//				echo '$(".synoaudio[data-eqLogic_id=' . $player-> getId() . '] .checkbox").on(\'click\', function () { ';
//				echo '		$("#md_modal2").dialog(\'close\'); ';
//				echo ' 	$(\'#div_playerSynoAlert\').showAlert({message: id+" "+closeModal, level: \'danger\'});';
//				echo '		$("#md_modal2").dialog({title: "Player"}); ';
//				echo '		$("#md_modal2").load(\'index.php?v=d&plugin=synoaudio&modal=player.syno&id=' . init('id') . '\').dialog(\'open\'); ';
//				echo '}); ';
				echo '</script>';
				echo '</td>';
				echo '</tr>';
			}
		}
		?>
	</tbody>
</table>
<script>

$('#bt_savePlayer').on('click',function(){
    var id = $(this).attr('data-syno_id');
	savePlayer(id,"true");
});

function checkPlayer(){
	$('#div_playerSynoAlert').showAlert({message: id+" "+closeModal, level: 'danger'});
	$("#md_modal2").dialog('close');
}

function savePlayer(id,closeModal){
	var players = '';
	var volume = '';
	var e_checkbox = document.getElementsByClassName('checkbox');
	for (var i = 0, n = e_checkbox.length; i < n; ++i) {
		if(e_checkbox[i].checked){
			if (players == ''){
				players=e_checkbox[i].name;
			}else{
				players = players + ',' + e_checkbox[i].name;
			}
			var e_volume = document.getElementsByClassName('vairplay');
			for (y=0;y< e_volume.length;y++){
				if (e_checkbox[i].name == e_volume[y].id){
					if (volume == ''){
						volume=e_volume[y].value;
					}else{
						volume = volume + ',' + e_volume[y].value;
					}
				}
			}
		}
	}

 $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
            data: {
                action: "player",
                id : id,
				players : players,
				volume : volume
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error,$('#div_playerSynoAlert'));
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_playerSynoAlert').showAlert({message: data.result, level: 'danger'});
                return;
            }
			if (closeModal == "true"){
				$('#md_modal2').dialog('close');
			}
        }
    });
}
</script>




