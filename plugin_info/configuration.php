<?php
if (!isConnect())
{
  include_file('desktop', '404', 'php');
  die();
}
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

//print $_GET['plugin'];
//print $_GET['configure'];
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

include_file('core', 'authentification', 'php');
include_file('desktop', 'alexaapi', 'js', 'alexaapi');

        //log::add('alexaapi', 'debug', 'Test de config::byKey dans config: ' . config::byKey('amazonserver','alexaapi'));

// code trouvé dans core\ajax\plugin.ajax.php
		$update = update::byLogicalId('alexaapi');
		$return = utils::o2a($update);
		$versionJeedom = $return['configuration']['version'];



		$foundSelect = false;
		if (config::byKey("listPlaylists","alexaamazonmusic","") != '') {
			$listPlaylists = '';
			$elements = explode(';', config::byKey("listPlaylists","alexaamazonmusic",""));
			// code trouvé dans core\class\cmd?.class.php
			foreach ($elements as $element) {
				$coupleArray = explode('|', $element);
				$listPlaylists .= '<option value="' . $coupleArray[0] . '">' . $coupleArray[1] . '</option>';
				$foundSelect = true;
			}
		}
		if (!$foundSelect) $listPlaylists = '<option value="">Aucune</option>' . $listPlaylists;
		
		$listPlaylistsValidDebut = date("d-m-Y H:i:s",config::byKey("listPlaylistsValidDebut","alexaamazonmusic",""));
		$listPlaylistsValidFin = date("d-m-Y H:i:s",config::byKey("listPlaylistsValidFin","alexaamazonmusic",""));
		$listPlaylistsProchain = date("d-m-Y H:i:s",config::byKey("listPlaylistsProchain","alexaamazonmusic",""));
	
?>
<style>
pre#pre_eventlog {
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace !important;
}
</style>


<form class="form-horizontal">
    <fieldset>
    <legend><i class="fas fa-music"></i> {{Playlists Amazon Music}}</legend>
  <div class="form-group">
        <label class="col-sm-4 control-label">{{Liste des Playlists Amazon Music}}</label>
    <div class="col-lg-3">
        <select class="selectCmd"><?php echo $listPlaylists?></select>
    </div>
	<div class="col-lg-4">
		<input class="configKey form-control" data-l1key="listPlaylists" placeholder="{{en test}}" />
	</div>  
 </div>

  <div class="form-group">
    <label class="col-lg-4 control-label">{{Mise à jour}}</label>
    <div class="col-lg-3">
        Dernière mise à jour : <?php echo $listPlaylistsValidDebut?>
    </div>
    <div class="col-lg-3">
        valable jusqu'à  <?php echo $listPlaylistsValidFin?>
    </div><a class="btn btn-success btn-xs pull-left" id="bt_saveUpdatePlaylists"><i class="fas fa-sync"></i> {{Reset}}</a>
</div>   
	<div class="form-group">
    <label class="col-lg-4 control-label">{{CRON}}</label>
    <div class="col-lg-4">
        Prochain CRON : <?php echo $listPlaylistsProchain?>
    </div>
</div></fieldset>
</form>
          

<script>
$("#bt_saveUpdatePlaylists").on('click', function (event) {
console.log("coucou");
  var el = $(this);
console.log(el);
var heureMaintenant=Math.round(+new Date() / 1000);
//var heureMaintenant="123";
  jeedom.config.save({
    plugin : 'alexaamazonmusic',
//    configuration: {listPlaylistsValidFin: el.attr('data-state')},
    configuration: {listPlaylistsValidFin: heureMaintenant},
    error: function (error) {
      $('#div_alert').showAlert({message: error.message, level: 'danger'});
    },
    success: function () {
		$('#md_modal').dialog( "close" );
		$('#md_modal').dialog({title: "{{Configuration du plugin (après Reset validité Playlists)}}"});
		$("#md_modal").load('index.php?v=d&p=plugin&ajax=1&id='+eqType).dialog('open');
      //$('#div_alert').showAlert({message: 'coucou', level: 'danger'});
    }
  });

  return false;
});


</script>
