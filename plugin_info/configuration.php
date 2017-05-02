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

include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
 }
 ?>
<form class="form-horizontal">
	<fieldset>
		<div class="form-group">
			<label class="col-sm-3 control-label">{{Adresse IP Audio Station}}</label>
			<div class="col-sm-3">
				<input type="text" class="configKey tooltips form-control" data-l1key="synoAddr" placeholder="IP"/>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label">{{Port Audio Station}}</label>
			<div class="col-sm-3">
				<input type="text" class="configKey tooltips form-control" data-l1key="synoPort" placeholder="Port"/>
			</div>
		</div>
<!-- Ajout conf Docker Début -->		
		<?php 
		//config::save('hardware_name','Docker');
		log::add('synoaudio', 'debug', ' Docker : ' . config::byKey('hardware_name','core').' == \'Docker\'');
		if (config::byKey('hardware_name','core') == 'Docker'){ ?>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Adresse IP de jeedom (Pour Docker)}}</label>
				<div class="col-sm-3">
					<input type="text" class="configKey tooltips form-control" data-l1key="synoAddrDocker" placeholder="IP"/>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label">{{Port de jeedom (Pour Docker)}}</label>
				<div class="col-sm-3">
					<input type="text" class="configKey tooltips form-control" data-l1key="synoPortDocker" placeholder="Port Docker"/>
				</div>
			</div>
		<?php }?>
<!-- Ajout conf Docker Fin -->	
		<div class="form-group">
			<label class="col-sm-3 control-label" >{{Connexion sécurisée}}</label>
			<div class="col-sm-1">
				<input type="checkbox" class="configKey tooltips form-control checkbox" data-l1key="synoHttps" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label">{{Utilisateur Audio Station}}</label>
			<div class="col-sm-3">
				<input type="text" class="configKey tooltips form-control" data-l1key="synoUser" placeholder="Utilisateur"/>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label">{{Mot de passe Audio Station}}</label>
			<div class="col-sm-3">
				<input type="password" class="configKey tooltips form-control" data-l1key="synoPwd" placeholder="Mot de passe"/>
			</div>
		</div>
<!-- Ajout TTS Debut -->		
		<div class="form-group" id="ttsEngine" >
			<label class="col-sm-3 control-label">{{TTS - Moteur}}</label>
			<div class="col-sm-2">
				<select class="configKey tooltips form-control" id="SelectEngine" data-l1key="ttsEngine" onchange="hideSelect()">
					<option value="local">Local</option>
					<!--
					<option value="online">Online</option>
					-->
				</select>
			</div>
		</div>
		<div class="form-group" id="ttsTransco">
			<label class="col-sm-3 control-label" >{{TTS - Transcodage vers MP3}}</label>
			<div class="col-sm-1">
				<input type="checkbox" class="configKey tooltips form-control checkbox" data-l1key="ttsTransco" />
			</div>
		</div>
		<!--
		<div class="form-group" id="ttsVoxygen" >
			<label class="col-sm-3 control-label">{{TTS - Voix online}}</label>
			<div class="col-sm-2">
				<select class="configKey tooltips form-control" data-l1key="ttsVoxygenVoice">
					<optgroup label="Arabic">
						<option value="Adel">Adel</option>
					</optgroup>
					<optgroup label="Deutch">
						<option value="Matthias">Matthias</option>
						<option value="Sylvia">Sylvia</option>
					</optgroup>
					<optgroup label="English U.K.">
						<option value="Bronwen">Bronwen</option>
						<option value="Elizabeth">Elizabeth</option>
						<option value="Judith">Judith</option>
						<option value="Paul">Paul</option>
						<option value="Witch">Witch</option>
					</optgroup>
					<optgroup label="English U.S.">
						<option value="Amanda">Amanda</option>
						<option value="Phil">Phil</option>
					</optgroup>
					<optgroup label="Español">
						<option value="Martha">Martha</option>
					</optgroup>
					<optgroup label="Français">
						<option value="Loic">Loic</option>
						<option value="Agnes">Agnes</option>
						<option value="Melodine">Melodine</option>
						<option value="Matteo">Matteo</option>
						<option value="Becool">Becool</option>
						<option value="Philippe">Philippe</option>
						<option value="Electra">Electra</option>
						<option value="Moussa">Moussa</option>
						<option value="Helene" selected>Helene</option>
						<option value="Sorciere">Sorciere</option>
					</optgroup>
					<optgroup label="Italiano">
						<option value="Sonia">Sonia</option>
					</optgroup>
				</select>
			</div>
		</div>
		-->
	<div class="form-group">
		<label class="col-sm-3 control-label">{{Découverte (Sauvegardez avant!)}}</label>
		<div class="col-sm-2">
			<a class="btn btn-default bt_syncLecteur"><i class='fa fa-check'></i> {{Rechercher les lecteurs }}</a>
		</div>
	</div>	
<!-- Ajout TTS Fin -->
	</fieldset>
</form>

<script>

function hideSelect() {
	if(document.getElementById("SelectEngine").value == 'local'){
		$('#ttsVoxygen').hide();
		$('#ttsTransco').show();
	}else{
		$('#ttsVoxygen').show();
		$('#ttsTransco').hide();
	}
}

$('.bt_syncLecteur').on('click', function () {
        $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
            data: {
            	action: "syncLecteur",
            },
            dataType: 'json',
            error: function (request, status, error) {
            	handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
            	$('#div_alert').showAlert({message: data.result, level: 'danger'});
            	return;
            }
            $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
          }
        });
      });


</script>
<?php include_file('desktop', 'synoaudio', 'js', 'synoaudio');?>