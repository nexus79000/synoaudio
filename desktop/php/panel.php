<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
//sendVarToJs('jeedomBackgroundImg', 'plugins/synoaudio/core/img/panel.jpg');
if (init('object_id') == '') {
	$object = jeeObject::byId($_SESSION['user']->getOptions('defaultDashboardObject'));
} else {
	$object = jeeObject::byId(init('object_id'));
}
if (!is_object($object)) {
	$object = jeeObject::rootObject();
}
if (!is_object($object)) {
	throw new Exception('{{Aucun objet racine trouvé. Pour en créer un, allez dans Générale -> Objet.<br/> Si vous ne savez pas quoi faire ou que c\'est la premiere fois que vous utilisez Jeedom n\'hésitez pas a consulter cette <a href="http://jeedom.fr/premier_pas.php" target="_blank">page</a>}}');
}
$allObject = jeeObject::all();
$child_object = jeeObject::buildTree($object);
$parentNumber = array();
?>

<div class="row row-overflow">
    <?php
	if (init('report') != 1) {
        echo '<div class="col-lg-2 col-md-3 col-sm-4" id="div_displayObjectList">';
    } else {
        echo '<div class="col-lg-2 col-md-3 col-sm-4" style="display:none;" id="div_displayObjectList">';
    }
    ?>
    <div class="bs-sidebar">
        <ul id="ul_object" class="nav nav-list bs-sidenav">
            <li class="nav-header">{{Liste objets}} </li>
            <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
            <?php
            foreach ($allObject as $object_li) {
                if ($object_li->getIsVisible() != 1 || count($object_li->getEqLogic(true, false, 'synoaudio', null, true)) == 0) {
					continue;
                }
                $margin = 5 * $object_li->getConfiguration('parentNumber');
                if ($object_li->getId() == $object->getId()) {
                    echo '<li class="cursor li_object active" ><a href="index.php?v=d&p=panel&m=synoaudio&object_id=' . $object_li->getId() . '" style="position:relative;left:' . $margin . 'px;">' . $object_li->getHumanName(true) . '</a></li>';
                } else {
                    echo '<li class="cursor li_object" ><a href="index.php?v=d&p=panel&m=synoaudio&object_id=' . $object_li->getId() . '" style="position:relative;left:' . $margin . 'px;">' . $object_li->getHumanName(true) . '</a></li>';
                }
            }
            ?>
        </ul>
    </div>
</div>
<?php
if (init('report') != 1) {
	echo '<div class="col-lg-10 col-md-9 col-sm-8" id="div_displayObject">';
} else {
	echo '<div class="col-lg-12 col-md-12 col-sm-12" id="div_displayObject">';
}
?>
<i class='fa fa-picture-o cursor tooltips pull-left' id='bt_displayObject' data-display='<?php echo $_SESSION['user']->getOptions('displayObjetByDefault')?>' title="Afficher/Masquer les objets"></i>
<br/>
<?php
echo '<div class="div_displayEquipement" style="width: 100%;">';
if (init('object_id') == '') {
	foreach ($allObject as $object) {
		foreach ($object->getEqLogic(true, false, 'synoaudio') as $syno) {
			echo $syno->toHtml('dview');
		}
	}
} else {
	foreach ($object->getEqLogic(true, false, 'synoaudio') as $syno) {
		echo $syno->toHtml('dview');
	}
	foreach ($child_object as $child) {
		$synos = $child->getEqLogic(true, false, 'synoaudio');
		if (count($synos) > 0) {
			foreach ($synos as $syno) {
				echo $syno->toHtml('dview');
			}
		}
	}
}
echo '</div>';
?>
</div>
</div>
<?php include_file('desktop', 'panel', 'js', 'synoaudio');?>