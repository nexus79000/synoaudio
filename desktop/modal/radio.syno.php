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
<div id='div_radioSynoAlert' style="display: none;"></div>
<table class="table table-condensed">
    <thead>
        <tr>
            <th style="width : 60px;">{{Action}}</th>
            <th>{{Radio}}</th>
        </tr>
    </thead>
    <tbody>
        <?php
foreach (synoaudio::getRadios() as $radio) {
	echo '<tr>';
	echo '<td>';
	echo '<a class="playRadio btn btn-xs btn-primary" data-syno_id="' . init('id') . '" data-name="' . $radio->title . '"><i class="fa fa-play"></i></a>';
	echo '</td>';
	echo '<td>';
	echo $radio->title;
	echo '</td>';
	echo '</tr>';
}
?>
   </tbody>
</table>

<script>
 $('.playRadio').on('click',function(){
    var id = $(this).attr('data-syno_id');
    var name = $(this).attr('data-name');
 $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/synoaudio/core/ajax/synoaudio.ajax.php", // url du fichier php
            data: {
                action: "playRadio",
                id :id,
                radio : name
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error,$('#div_radioSynoAlert'));
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_radioSynoAlert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#md_modal2').dialog('close');
        }
    });
});
</script>




