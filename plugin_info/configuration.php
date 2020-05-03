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

?>
<style>
pre#pre_eventlog {
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace !important;
}
</style>

<form class="form-horizontal">
    <fieldset>
    <legend><i class="icon nature-planet5"></i> {{Playlists Amazon Music}}</legend>
       <div class="form-group">
        <label class="col-sm-4 control-label">{{Essai}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="amazonserver" placeholder="{{en test}}" />
    </div>
   </div>

</fieldset>
</form>



