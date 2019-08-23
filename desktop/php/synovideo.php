<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('synovideo');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor logoSecondary" id="bt_syncLecteur">
                <i class="fas fa-search"></i>
                <br/>
				<span>{{Découverte}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench" ></i>
                <br/>
				<span>{{Configuration}}</span>
			</div>
            <div class="cursor logoSecondary" id="bt_purgeplugin">
                <i class="fas divers-garbage8"></i>
                <br/>
				<span>{{Nettoyage plugin}}</span>
			</div>
		</div>
		<div class="eqLogicThumbnailContainer">
            <legend><i class="fas  fa-table"></i>  {{Mes Players}} </legend>
            <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
			<?php
				foreach ($eqLogics as $eqLogic) {
					$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                    echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
					echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
					echo "</br>";
					echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
					echo '</div>';
				}
			?>
		</div>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-default eqLogicAction btn-sm roundedLeft" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
        </ul>
		<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <div class="row">
                    <div class="col-xs-6">
                        <form class="form-horizontal">
                            <fieldset>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Nom du player}}</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                        <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom du player}}"/>
                                        <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" placeholder="{{Nom du player}}" disabled="disabled" />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                                    <div class="col-sm-9">
                                        <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                            <option value="">{{Aucun}}</option>
                                            <?php
                                                foreach (jeeObject::all() as $object) {
                                                    echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">{{Catégorie}}</label>
                                    <div class="col-sm-9">
                                        <?php
                                            foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                                echo '<label class="checkbox-inline">';
                                                echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                                                echo '</label>';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label" ></label>
                                    <div class="col-sm-9">
                                        <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                                        <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
            
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label" >{{Affichage simplifié}}</label>
                                    <div class="col-sm-9">	
                                        <input type="checkbox" class="eqLogicAttr" data-label-text="{{Light}}" data-l1key="display" data-l2key="isLight" />
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </div>
			</div>
            
			<div role="tabpanel" class="tab-pane" id="commandtab">
                </br>
				<table id="table_cmd" class="table table-bordered table-condensed">
					<thead>
						<tr>
							<th>{{Nom}}</th><th>{{Options}}</th><th></th>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'synovideo', 'js', 'synovideo');?>
<?php include_file('core', 'plugin.template', 'js');?>