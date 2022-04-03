<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class alexaamazonmusic extends eqLogic {
		/*     * *************************Attributs pour autoriser les onglets Affichage et Disposition****************************** */
	public static $_widgetPossibility = array('custom' => true, 'custom::layout' => true);
	
	
	public static function cron($_eqlogic_id = null) {
		$deamon_info = alexaapi::deamon_info();
		$r = new Cron\CronExpression('*/17 * * * *', new Cron\FieldFactory);// boucle refresh
		if ($r->isDue() && $deamon_info['state'] == 'ok') self::cronManuel($_eqlogic_id);
	}
	
	public static function cronManuel($_eqlogic_id = null) {
		$deamon_info = alexaapi::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			$eqLogics = ($_eqlogic_id !== null) ? array(eqLogic::byId($_eqlogic_id)) : eqLogic::byType('alexaamazonmusic', true);
			config::save("listPlaylistsProchain",time()+1020,"alexaamazonmusic");	
			foreach ($eqLogics as $alexaamazonmusic) {
				$alexaamazonmusic->refresh(); 				
				sleep(2);
			}	
		}
	}
	
	public static function createNewDevice($deviceName, $deviceSerial) {
		$defaultRoom = intval(config::byKey('defaultParentObject','alexaapi','',true));
		$newDevice = new alexaamazonmusic();
		$newDevice->setName($deviceName);
		$newDevice->setLogicalId($deviceSerial);
		$newDevice->setEqType_name('alexaamazonmusic');
		$newDevice->setIsVisible(1);
		if($defaultRoom) $newDevice->setObject_id($defaultRoom);
		$newDevice->setDisplay('height', '500');
		$newDevice->setConfiguration('device', $deviceName);
		$newDevice->setConfiguration('serial', $deviceSerial);
		$newDevice->setIsEnable(1);
		return $newDevice;
	}

	public function hasCapaorFamilyorType($thisCapa) {
		
		$family=$this->getConfiguration('family',""); // Si c'est la bonne famille, on dit OK tout de suite
		if($thisCapa == $family) return true; // ajouté pour filtrer sur la famille (pour les groupes par exemple)
		$type=$this->getConfiguration('type',"");// Si c'est le bon type, on dit OK tout de suite
		if($thisCapa == $type) return true; 
		$capa=$this->getConfiguration('capabilities',"");
		if(((gettype($capa) == "array" && in_array($thisCapa,$capa))) || ((gettype($capa) == "string" && strpos($capa, $thisCapa) !== false))) {
			if($thisCapa == "REMINDERS" && $type == "A15ERDAKK5HQQG") return false;
			return true;
		} else {
			return false;
		}
	}
	
	public function sortBy($field, &$array, $direction = 'asc') {
		usort($array, create_function('$a, $b', '
		$a = $a["' . $field . '"];
		$b = $b["' . $field . '"];
		if ($a == $b) return 0;
		$direction = strtolower(trim($direction));
		return ($a ' . ($direction == 'desc' ? '>' : '<') . ' $b) ? -1 : 1;
    	'));
		return true;
	}

	public function refresh() { 
		$deamon_info = alexaapi::deamon_info();
		if ($deamon_info['state'] != 'ok') return false;
		
		$devicetype=$this->getConfiguration('devicetype');
		log::add('alexaamazonmusic', 'info', ' ');
		log::add('alexaamazonmusic', 'info', ' ╔══════════════════════[Refresh du device '.$this->getName().' ('.$devicetype.')]═════════════════════════════════════════════════════════');
		$widgetPlayer=($devicetype == "Player");
		$widgetSmarthome=($devicetype == "Smarthome");
		$widgetPlaylist=($devicetype == "PlayList");
		$device=str_replace("_player", "", $this->getConfiguration('serial'));

		if ($widgetPlayer) {	// Refresh d'un player
			$url = network::getNetworkAccess('internal'). "/plugins/alexaapi/core/php/jeeAlexaapi.php?apikey=".jeedom::getApiKey('alexaapi')."&nom=refreshPlayer"; // Envoyer la commande Refresh 
			$ch = curl_init($url);
			$data = array(
				'deviceSerialNumber' => $device,
				'audioPlayerState' => 'REFRESH'
			);
			$payload = json_encode($data);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close($ch);
			$_playlists=true;
			log::add('alexaamazonmusic', 'debug', '╠═ Demande de rafraichissement du Widget '.$this->getName());				

		}
		else {
			$_playlists=false;			
		}

		if ($_playlists) {
			$trouvePlaylist = false;
			if (config::byKey("listPlaylistsValidFin","alexaamazonmusic",time()-20) < time()) { // On regarde si on va chercher la playlist sur Amazon ou dans la Config
				$json = file_get_contents("http://" . config::byKey('internalAddr') . ":3456/playlists?device=".$device);
				$json = json_decode($json, true);	
				$ListeDesPlaylists = [];
				foreach ($json as $key => $value) {
					foreach ($value as $key2 => $playlist) {
						foreach ($playlist as $key3 => $value2) {
						//$ListeDesPlaylists[]= $value2['playlistId'] . '|' . $value2['title']." (".$value2['trackCount'].")"; Modif 09/21 Sigalou - Plus ID mais le nom de la playlist
						$ListeDesPlaylists[]= $value2['title'] . '|' . $value2['title']." (".$value2['trackCount'].")";
						$trouvePlaylist = true;
						}	
					}
				}		
				$ListeDesPlaylists_string=join(';',$ListeDesPlaylists);
				config::save("listPlaylists",$ListeDesPlaylists_string,"alexaamazonmusic");		
				config::save("listPlaylistsValidDebut",time(),"alexaamazonmusic");		
				config::save("listPlaylistsValidFin",time()+86400,"alexaamazonmusic");					
				log::add('alexaamazonmusic', 'debug', '╠═ Enregistre Playlists dans Config sur plugin: '.$ListeDesPlaylists_string);
				$ouRecupPlaylists="Amazon";
			} else
			{
				$ListeDesPlaylists_string=config::byKey("listPlaylists","alexaamazonmusic","");
				//log::add('alexaamazonmusic', 'debug', '╚═> ListeDesPlaylists_string : '.$ListeDesPlaylists_string);
				if ($ListeDesPlaylists_string!="") $trouvePlaylist = true;
				$ouRecupPlaylists="Configuation";
			}
			$cmd = $this->getCmd(null, 'playList');
			if (is_object($cmd) && ($trouvePlaylist)){ //Playlists existe on  met à jour la liste des Playlists
				$cmd->setConfiguration('listValue', $ListeDesPlaylists_string);
				$cmd->save();
				log::add('alexaamazonmusic', 'debug', '╠═ Mise à jour de la liste des Playlists de '.$this->getName().' depuis '.$ouRecupPlaylists);
				log::add('alexaamazonmusic', 'debug', '╠═══> contenu : '.$ListeDesPlaylists_string);
			}
		}
		
			$cmd = $this->getCmd(null, 'url');
			if (is_object($cmd)) { //on vérifie si URL existe
			
				//$this->checkAndUpdateCmd('url','https://album-art-storage-eu.s3.eu-west-1.amazonaws.com/2c5dbb98a362045796a3128325ce273c032d9aca18bbb690ef05da05f2be91f5_500x500.jpg?response-content-type=image%2Fjpeg&X-Amz-Security-Token=FwoGZXIvYXdzEBwaDH5rluRcVWYXs8DDgCKCAe9YUJTQuHUBxEqVajcT8KjofrtfggxU%2Bss5trLcwXo3C6c2cdI5PDq4CNpZq63nSs3Pyr912Ndvd1mffATQr8E2TdsmYY9NtMQ%2B4yl60q6ngZcw%2FM478Gm1O1g4UEmdvDpkTAun7j8P0Xy4Tuw2QBT6lmaWeIEFoAOuy1AVvLb8Z9sonpW19QUyKFqDn%2Bu6Xc5EPhxUFN%2B77hDsXGXHy%2BkwOUISo7HwxXQQd2ksB3eCjNc%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20200502T184740Z&X-Amz-SignedHeaders=host&X-Amz-Expires=86399&X-Amz-Credential=ASIAZZLLX6KMR5BZEFXL%2F20200502%2Feu-west-1%2Fs3%2Faws4_request&X-Amz-Signature=bd8c04eed80268ba7908ba5a3b9d22c8e28c8a027ac377168bebfaf19d0ed4c7');
				
				$url=$cmd->execCmd();
				//log::add('alexaamazonmusic', 'debug', 'Fin URL vaut : '.substr($url, -8));				
				
				
				
				if ((!self::urlExist($url)) || (substr($url, -8) == 'vide.gif')) {
							if (file_exists(dirname(__FILE__).'/../../core/config/logourl.png'))
								$futureURL="plugins/alexaamazonmusic/core/config/logourl.png";	
							else {
								$plugin = plugin::byId('alexaamazonmusic');
								$futureURL=$plugin->getPathImgIcon();
							}
				$this->checkAndUpdateCmd('url',$futureURL);
				log::add('alexaamazonmusic', 'debug', '╠═ Le logo URL est remplacé par : '.$futureURL. ' ( au lieu de '.$url);				
				//log::add('alexaamazonmusic', 'debug', 'header donne : '.json_encode(get_headers($url)));				
				//log::add('alexaamazonmusic', 'debug', 'header donne :: '.get_headers($url)[0]);				
				}
			}
	
		log::add('alexaamazonmusic', 'info', ' ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════');

	}
	
	public function urlExist($url) {
	$file_headers = @get_headers($url);
	//log::add('alexaamazonmusic', 'debug', 'existe test: : '.$file_headers[0]);
	if(substr($file_headers[0], -2) == 'OK') return true; //HTTP/1.x 200 OK
	if($file_headers[0] == '') return true; // fichier local
	//log::add('alexaamazonmusic', 'debug', 'FALSE');
    return false;
	}
	
	public static function forcerDefaultCmd($_id = null) {
		if (!is_null($_id)) { 
		$device = alexaamazonmusic::byId($_id);
				if (is_object($device)) {
				$device->setStatus('forceUpdate',true);
				$device->save();
				}
		}		
	}
	
	public function updateCmd ($forceUpdate, $LogicalId, $Type, $SubType, $RunWhenRefresh, $Name, $IsVisible, $title_disable, $setDisplayicon, $infoNameArray, $setTemplate_lien, $request, $infoName, $listValue, $Order, $Test) {
		if ($Test) {
			try {
				if (empty($Name)) $Name=$LogicalId;
				$cmd = $this->getCmd(null, $LogicalId);
				if ((!is_object($cmd)) || $forceUpdate) {
					if (!is_object($cmd)) $cmd = new alexaamazonmusicCmd();
					$cmd->setType($Type);
					$cmd->setLogicalId($LogicalId);
					$cmd->setSubType($SubType);
					$cmd->setEqLogic_id($this->getId());
					$cmd->setName($Name);
					$cmd->setIsVisible((($IsVisible)?1:0));
					if (!empty($setTemplate_lien)) {
						$cmd->setTemplate("dashboard", $setTemplate_lien);
						$cmd->setTemplate("mobile", $setTemplate_lien);
					}						
					if (!empty($setDisplayicon)) $cmd->setDisplay('icon', '<i class="'.$setDisplayicon.'"></i>');
					if (!empty($request)) $cmd->setConfiguration('request', $request);
					if (!empty($infoName)) $cmd->setConfiguration('infoName', $infoName);
					if (!empty($infoNameArray)) $cmd->setConfiguration('infoNameArray', $infoNameArray);
					if (!empty($listValue)) $cmd->setConfiguration('listValue', $listValue);
					$cmd->setConfiguration('RunWhenRefresh', $RunWhenRefresh);				
					$cmd->setDisplay('title_disable', $title_disable);
					$cmd->setDisplay('showNameOndashboard', !$title_disable);
					$cmd->setOrder($Order);
					//cas particulier
						if (($LogicalId == 'speak') || ($LogicalId == 'announcement')){
							//$cmd->setDisplay('title_placeholder', 'Options');
							$cmd->setDisplay('message_placeholder', 'Phrase à faire lire par Alexa');
						}
						if (($LogicalId == 'reminder')){
							//$cmd->setDisplay('title_placeholder', 'Options');
							$cmd->setDisplay('message_placeholder', 'Texte du rappel');
						}						
						if (($LogicalId=='volumeinfo') || ($LogicalId=='volume')) {
							$cmd->setConfiguration('minValue', '0');
							$cmd->setConfiguration('maxValue', '100');
							$cmd->setDisplay('forceReturnLineBefore', true);
						}			
						if ($LogicalId=='volume') {
							$volinfo = $this->getCmd(null, 'volumeinfo'); // il faut que voluminfo soit créé avant volume
							if (is_object($volinfo)) $cmd->setValue($volinfo->getId());// Lien entre volume et volumeinfo (pas pour les groupes)
							
						}	
						if (($LogicalId=='repeaton') || ($LogicalId=='repeatoff')) {
							$repeat = $this->getCmd(null, 'repeat');// il faut que repeat soit créé avant repeaton et repeatoff
							$cmd->setValue($repeat->getId());// 
						}	
						if (($LogicalId=='shuffleon') || ($LogicalId=='shuffleoff')) {
							$shuffle = $this->getCmd(null, 'shuffle');
							$cmd->setValue($shuffle->getId());// 
						}	
					
				}
				$cmd->save();
			}
			catch(Exception $exc) {
				log::add('alexaamazonmusic', 'error', __('Erreur pour ', __FILE__) . ' : ' . $exc->getMessage());
			}
		} else {
							//log::add('alexaamazonmusic', 'debug', 'PAS de **'.$LogicalId.'*********************************');

		$cmd = $this->getCmd(null, $LogicalId);
			if (is_object($cmd)) {
				$cmd->remove();
			}
		}
	}


	public function postSave() {
		//log::add('alexaamazonmusic', 'debug', '**********************postSave '.$this->getName().'***********************************');
		$F=$this->getStatus('forceUpdate');// forceUpdate permet de recharger les commandes à valeur d'origine, mais sans supprimer/recréer les commandes
				$capa=$this->getConfiguration('capabilities','');
				$type=$this->getConfiguration('type','');
		if(!empty($capa)) {

			$widgetEcho=($this->getConfiguration('devicetype') == "Echo");
			$widgetPlayer=($this->getConfiguration('devicetype') == "Player");
			$widgetSmarthome=($this->getConfiguration('devicetype') == "Smarthome");
			$widgetPlaylist=($this->getConfiguration('devicetype') == "PlayList");

			$cas1=(($this->hasCapaorFamilyorType("AUDIO_PLAYER")) && $widgetPlayer);
			$cas1bis=(($this->hasCapaorFamilyorType("AUDIO_PLAYER")) && !$widgetPlayer);
			$cas1ter=(($this->hasCapaorFamilyorType("AUDIO_PLAYER")) && !$this->hasCapaorFamilyorType("WHA"));
			$cas2=(($this->hasCapaorFamilyorType("TIMERS_AND_ALARMS")) && !$widgetPlayer);
			$cas3=(($this->hasCapaorFamilyorType("REMINDERS")) && !$widgetPlayer);
			$cas4=(($this->hasCapaorFamilyorType("REMINDERS")) && !$widgetSmarthome);
			$cas5=($this->hasCapaorFamilyorType("VOLUME_SETTING"));
			$cas6=($cas5 && (!$this->hasCapaorFamilyorType("WHA")));
			$cas7=((!$this->hasCapaorFamilyorType("WHA")) && ($this->getConfiguration('devicetype') != "Player") &&(!$this->hasCapaorFamilyorType("FIRE_TV")) && !$widgetSmarthome && (!$this->hasCapaorFamilyorType("AMAZONMOBILEMUSIC_ANDROID")));
			$cas8=(($this->hasCapaorFamilyorType("turnOff")) && $widgetSmarthome);
			$cas9=($this->hasCapaorFamilyorType("WHA") && $widgetPlayer);
			$false=false;
			

            // Volume on traite en premier car c'est fonction de WHA
            if ($cas6) 	{
				self::updateCmd ($F, 'volumeinfo', 'info', 'string', false, 'Volume Info', false, false, 'fa fa-volume-up', null, null, null, null, null, 30, $cas6);				
				self::updateCmd ($F, 'volume', 'action', 'slider', false, 'Volume', true, true, null, null, 'alexaapi::volume', 'volume?value=#slider#', null, null, 29, $cas6);
			}
            else        
				self::updateCmd ($F, 'volume', 'action', 'slider', false, 'Volume', false, true, null, null, 'alexaapi::volume', 'volume?value=#slider#', null, null, 29, $cas9);

			
			
			self::updateCmd ($F, 'subText2', 'info', 'string', false, null, true, false, null, null, 'alexaapi::subText2', null, null, null, 2, $cas1);
			self::updateCmd ($F, 'subText1', 'info', 'string', false, null, true, false, null, null, 'alexaapi::title', null, null, null, 4, $cas1);			
			self::updateCmd ($F, 'url', 'info', 'string', false, null, true, false, null, null, 'alexaapi::image', null, null, null, 5, $cas1);			
			self::updateCmd ($F, 'title', 'info', 'string', false, null, true, false, null, null, 'alexaapi::title', null, null, null, 9, $cas1);
			self::updateCmd ($F, 'previous', 'action', 'other', false, 'Previous', true, true, 'fa fa-step-backward', null, null, 'command?command=previous', null, null, 16, $cas1);
			self::updateCmd ($F, 'pause', 'action', 'other', false, 'Pause', true, true, 'fa fa-pause', null, null, 'command?command=pause', null, null, 17, $cas1);
			self::updateCmd ($F, 'play', 'action', 'other', false, 'Play', true, true, 'fa fa-play', null, null, 'command?command=play', null, null, 18, $cas1);
			self::updateCmd ($F, 'next', 'action', 'other', false, 'Next', true, true, 'fa fa-step-forward', null, null, 'command?command=next', null, null, 19, $cas1);
			self::updateCmd ($F, 'repeat', 'info', 'numeric', false, null, false, false, null, null, null, null, null, null, 79, $cas1ter);
			self::updateCmd ($F, 'shuffle', 'info', 'numeric', false, null, false, false, null, null, null, null, null, null, 79, $cas1ter);
			self::updateCmd ($F, 'repeaton', 'action', 'other', false, 'Repeat On', true, true, null, null, 'alexaapi::repeat', 'command?command=repeat&value=on', null, null, 21, $cas1ter);			
			self::updateCmd ($F, 'repeatoff', 'action', 'other', false, 'Repeat Off', true, true, null, null, 'alexaapi::repeat', 'command?command=repeat&value=off', null, null, 21, $cas1ter); //fas fa-redo" style="opacity:0.3
			self::updateCmd ($F, 'shuffleon', 'action', 'other', false, 'Shuffle On', true, true, 'fas fa-random', null, 'alexaapi::shuffle', 'command?command=shuffle&value=on', null, null, 22, $cas1ter);			
			self::updateCmd ($F, 'shuffleoff', 'action', 'other', false, 'Shuffle Off', true, true, 'fas fa-random" style="opacity:0.3', null, 'alexaapi::shuffle', 'command?command=shuffle&value=off', null, null, 22, $cas1ter);		//self::updateCmd ($F, 'rwd', 'action', 'other', false, 'Rwd', true, true, 'fa fa-fast-backard', null, null, 'command?command=rwd', null, null, 80, $cas1);
			//self::updateCmd ($F, 'multiplenext', 'action', 'other', false, 'Multiple Next', true, true, 'fa fa-step-forward', null, null, 'multiplenext?text=3', null, null, 19, $cas1);		
			self::updateCmd ($F, 'providerName', 'info', 'string', false, 'Fournisseur de musique :', true, true, 'loisir-musical7', null, null , null, null, null, 20, $cas1);
			self::updateCmd ($F, 'contentId', 'info', 'string', false, 'Amazon Music Id', false, true, 'loisir-musical7', null, null , null, null, null, 25, $cas1);			
			self::updateCmd ($F, 'playList', 'action', 'select', false, 'Ecouter une playlist', true, false, null, null, 'alexaapi::list', 'playlist?playlist=#select#', null, 'Lancer Refresh|Lancer Refresh', 26, $cas1);
			self::updateCmd ($F, 'radio', 'action', 'select', false, 'Ecouter une radio', true, false, null, null, 'alexaapi::list', 'radio?station=#select#', null, 'Nostalgie|Nostalgie;Sud+Radio|Sud Radio;NRJ|N.R.J.', 27, $cas1);	
			self::updateCmd ($F, 'playMusicTrack', 'action', 'select', false, 'Ecouter une piste musicale', true, false, null, null, 'alexaapi::list', 'playmusictrack?trackId=#select#', null, 'Petit+papa+no%C3%ABl|Petit Papa Noël;Crazy in love|Crazy in love;Nirvana|Nirvana', 28, $cas1);
			self::updateCmd ($F, 'mute', 'action', 'other', false, 'Muet On', false, true, 'fas fa-volume-mute', null, 'alexaapi::mute', 'textCommand?text=mute', null, null, 29, $cas1ter);			
			self::updateCmd ($F, 'unmute', 'action', 'other', false, 'Muet Off', false, true, 'fas fa-volume-off"', null, 'alexaapi::mute', 'textCommand?text=unmute', null, null, 29, $cas1ter);		//self::updateCmd ($F, 'rwd', 'action', 'other', false, 'Rwd', true, true, 'fa fa-fast-backard', null, null, 'command?command=rwd', null, null, 80, $cas1);
			self::updateCmd ($F, '0', 'action', 'other', false, '0', true, true, null, null,null, 'volume?value=0', null, null, 30, $cas9);
			self::updateCmd ($F, 'volume20', 'action', 'other', false, '20', true, true, null, null,null, 'volume?value=20', null, null, 31, $cas9);
			self::updateCmd ($F, 'volume40', 'action', 'other', false, '40', true, true, null, null,null, 'volume?value=40', null, null, 32, $cas9);
			self::updateCmd ($F, 'volume60', 'action', 'other', false, '60', true, true, null, null,null, 'volume?value=60', null, null, 33, $cas9);
			self::updateCmd ($F, 'volume80', 'action', 'other', false, '80', true, true, null, null,null, 'volume?value=80', null, null, 34, $cas9);
			self::updateCmd ($F, 'volume100', 'action', 'other', false, '100', true, true, null, null,null, 'volume?value=100', null, null, 35, $cas9);
			
			self::updateCmd ($F, 'playlistName', 'info', 'string', false, null, true, true, null, null, null, null, null, null, 79, $widgetPlaylist);
			self::updateCmd ($F, 'playlisthtml', 'info', 'string', false, null, true, true, null, null, null, null, null, null, 79, $widgetPlaylist);
			self::updateCmd ($F, 'command', 'action', 'message', false, 'Command', false, true, "fa fa-play-circle", null, null, 'command?command=#select#', null, null, 79, $cas1);		
			self::updateCmd ($F, 'mediaLength', 'info', 'string', false, null, false, false, null, null, null , null, null, null, 79, $cas1);
			self::updateCmd ($F, 'mediaProgress', 'info', 'string', false, null, false, false, null, null, null , null, null, null, 79, $cas1);
			self::updateCmd ($F, 'state', 'info', 'string', false, null, true, false, null, null, 'alexaapi::state', null, null, null, 79, $cas1);
			self::updateCmd ($F, 'nextState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);
			self::updateCmd ($F, 'previousState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);
			self::updateCmd ($F, 'playPauseState', 'info', 'string', false, null, false, true, null, null, null, null, null, null, 79, $cas1);


					
			$repeatinfo = $this->getCmd(null, 'repeat');
			$shuffleinfo = $this->getCmd(null, 'shuffle');
			$repeat = $this->getCmd(null, 'repeaton');
					if((is_object($repeatinfo)) && (is_object($repeat))) {
					$repeat->setValue($repeatinfo->getId());// Lien entre repeat et repeaton
					$repeat->save();
					}		
			$repeat = $this->getCmd(null, 'repeatoff');
					if((is_object($repeatinfo)) && (is_object($repeat))) {
					$repeat->setValue($repeatinfo->getId());// Lien entre repeat et repeatoff
					$repeat->save();
					}						
			$shuffle = $this->getCmd(null, 'shuffleoff');
					if((is_object($shuffleinfo)) && (is_object($shuffle))) {
					$shuffle->setValue($shuffleinfo->getId());// Lien entre repeat et repeatoff
					$shuffle->save();
					}
			$shuffle = $this->getCmd(null, 'shuffleon');
					if((is_object($shuffleinfo)) && (is_object($shuffle))) {
					$shuffle->setValue($shuffleinfo->getId());// Lien entre repeat et repeatoff
					$shuffle->save();
					}
								
								
								
								// Pour la commande Refresh, on garde l'ancienne méthode
			if (($this->hasCapaorFamilyorType("REMINDERS")) && !($this->getConfiguration('devicetype') == "Smarthome")) { 
				//Commande Refresh
				$createRefreshCmd = true;
				$refresh = $this->getCmd(null, 'refresh');
				if (!is_object($refresh)) {
					$refresh = cmd::byEqLogicIdCmdName($this->getId(), __('Rafraichir', __FILE__));
					if (is_object($refresh)) {
						$createRefreshCmd = false;
					}
				}
				if ($createRefreshCmd) {
					if (!is_object($refresh)) {
						$refresh = new alexaamazonmusicCmd();
						$refresh->setLogicalId('refresh');
						$refresh->setIsVisible(1);
						$refresh->setDisplay('icon', '<i class="fa fa-sync"></i>');
						$refresh->setName(__('Refresh', __FILE__));
					}
					$refresh->setType('action');
					$refresh->setSubType('other');
					$refresh->setEqLogic_id($this->getId());
					$refresh->save();
				}
			}
		} else {
		log::add('alexaamazonmusic', 'warning', 'Pas de capacité détectée sur '.$this->getName().' , assurez-vous que le démon est OK');
		}
		$this->refresh(); 
		$this->setStatus('forceUpdate', false); //dans tous les cas, on repasse forceUpdate à false
				//log::add('alexaamazonmusic', 'debug', '**********************fin postSave '.$this->getName().'***********************************');

	}


	public function preRemove () {
		if ($this->getConfiguration('devicetype') == "Player") { // Si c'est un type Player, il faut supprimer le Device Playlist
			$device_playlist=str_replace("_player", "", $this->getConfiguration('serial'))."_playlist"; //Nom du device de la playlist
		$eq=eqLogic::byLogicalId($device_playlist,'alexaamazonmusic');
				if(is_object($eq)) $eq->remove();
		}
	}
	
	public function preSave() {
	}

// https://github.com/NextDom/NextDom/wiki/Ajout-d%27un-template-a-votre-plugin	
// https://jeedom.github.io/documentation/dev/fr_FR/widget_plugin	

  public function toHtml($_version = 'dashboard') {
	$replace = $this->preToHtml($_version);
	//log::add('alexaamazonmusic_widget','debug','************Début génération Widget de '.$replace['#logicalId#']);  
	$typeWidget="alexaapi";	
	if ((substr($replace['#logicalId#'], -7))=="_player") $typeWidget="alexaapi_player";
	if ((substr($replace['#logicalId#'], -9))=="_playlist") $typeWidget="alexaapi_playlist";
    if ($typeWidget!="alexaapi_playlist") return parent::toHtml($_version);
	//log::add('alexaamazonmusic_widget','debug',$typeWidget.'************Début génération Widget de '.$replace['#name#']);        
	if (!is_array($replace)) {
		return $replace;
	}
	$version = jeedom::versionAlias($_version);
	if ($this->getDisplay('hideOn' . $version) == 1) {
		return '';
	}
	foreach ($this->getCmd('info') as $cmd) {
		 	//log::add('alexaamazonmusic_widget','debug',$typeWidget.'dans boucle génération Widget');        
            $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
            $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
            $replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
            if ($cmd->getLogicalId() == 'encours'){
                $replace['#thumbnail#'] = $cmd->getDisplay('icon');
            }
            if ($cmd->getIsHistorized() == 1) {
                $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
            }
        }
	$replace['#height#'] = '800';
		if ($typeWidget=="alexaapi_playlist") {
			if ("#playlistName#" != "") {
				$replace['#name_display#']='#playlistName#';
			}
		}
	//log::add('alexaamazonmusic_widget','debug',$typeWidget.'***************************************************************************Fin génération Widget');        
	return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, $typeWidget, 'alexaapi')));
	}
}

class alexaamazonmusicCmd extends cmd {

	public function dontRemoveCmd() {
		if ($this->getLogicalId() == 'refresh') {
			return true;
		}
		return false;
	}
	
	public function postSave() {

	}
	
	
	
	
	public function preSave() {
		if ($this->getLogicalId() == 'refresh') {
			return;
		}
		if ($this->getType() == 'action') {
			$eqLogic = $this->getEqLogic();
			$this->setConfiguration('value', 'http://' . config::byKey('internalAddr') . ':3456/' . $this->getConfiguration('request') . "&device=" . $eqLogic->getConfiguration('serial'));
		}
		$actionInfo = alexaamazonmusicCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
		if (is_object($actionInfo)) $this->setId($actionInfo->getId());
		if (($this->getType() == 'action') && ($this->getConfiguration('infoName') != '')) {//Si c'est une action et que Commande info est renseigné
			$actionInfo = alexaamazonmusicCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getConfiguration('infoName'));
			if (!is_object($actionInfo)) {//C'est une commande qui n'existe pas
				$actionInfo = new alexaamazonmusicCmd();
				$actionInfo->setType('info');
				$actionInfo->setSubType('string');
				$actionInfo->setConfiguration('taskid', $this->getID());
				$actionInfo->setConfiguration('taskname', $this->getName());
			}
			$actionInfo->setName($this->getConfiguration('infoName'));
			$actionInfo->setEqLogic_id($this->getEqLogic_id());
			$actionInfo->save();
			$this->setConfiguration('infoId', $actionInfo->getId());
		}
	}

	public function execute($_options = null) {
		if ($this->getLogicalId() == 'refresh') {
			$this->getEqLogic()->refresh();
			return;
		}

		$request = $this->buildRequest($_options);
		log::add('alexaamazonmusic', 'debug', '╠═══> Request : '.$request);
		//log::add('alexaapi_node', 'debug', '╠═══> 1 : '.$request);
		//log::add('alexaamazonmusic', 'info', 'Request : ' . $request);//Request : http://192.168.0.21:3456/volume?value=50&device=G090LF118173117U
		$request_http = new com_http($request);
		//log::add('alexaapi_node', 'debug', '╠═══> 2 : '.$request);
		$request_http->setAllowEmptyReponse(true);//Autorise les réponses vides
		//log::add('alexaapi_node', 'debug', '╠═══> 3 : '.$request);
		if ($this->getConfiguration('noSslCheck') == 1) $request_http->setNoSslCheck(true);
		if ($this->getConfiguration('doNotReportHttpError') == 1) $request_http->setNoReportError(true);
		//log::add('alexaapi_node', 'debug', '╠═══> 4 : '.$request);
		if (isset($_options['speedAndNoErrorReport']) && $_options['speedAndNoErrorReport'] == true) {// option non activée 
			$request_http->setNoReportError(true);
		//log::add('alexaapi_node', 'debug', '╠═══> 5 : '.$request);
			$request_http->exec(0.1, 1);
		//log::add('alexaapi_node', 'debug', '╠═══> 6 : '.$request);
			return;
		}
		log::add('alexaamazonmusic', 'debug', '╠═══> Request : '.$request);
		
		$result = $request_http->exec(10,3);//Time out à 10s 3 essais Modifié 04/07/2020
		//$result = $request_http->exec(10, 0);//Time out à 3s 3 essais
		//$result = $request_http->exec();//Time out à 3s 3 essais
		if (!$result) throw new Exception(__('Serveur injoignable', __FILE__));
		// On traite la valeur de resultat (dans le cas de whennextalarm par exemple)
		$resultjson = json_decode($result, true);
		//log::add('alexaapi_node', 'info', '9999999999999999999999999999999999999999999999999999999999999999999resultjson:'.json_encode($resultjson));
					// Ici, on va traiter une commande qui n'a pas été executée correctement (erreur type "Connexion Close")
						if (isset($resultjson['value'])) $value = $resultjson['value']; else $value="";
						if (isset($resultjson['detail'])) $detail = $resultjson['detail']; else $detail="";					
						if (($value =="Connexion Close") || ($detail =="Unauthorized")){
						//$value = $resultjson['value'];
						//$detail = $resultjson['detail'];
						log::add('alexaamazonmusic', 'debug', '**On traite '.$value.$detail.' Connexion Close** dans la Class');
						sleep(6);
							if (ob_get_length()) {
							ob_end_flush();
							flush();
							}	
						log::add('alexaamazonmusic', 'debug', '**On relance '.$request);
						$result = $request_http->exec(10,3);//Time out à 10s 3 essais Modifié 04/07/2020
						if (!result) throw new Exception(__('Serveur injoignable', __FILE__));
						$jsonResult = json_decode($json, true);
						if (!empty($jsonResult)) throw new Exception(__('Echec de l\'execution: ', __FILE__) . '(' . $jsonResult['title'] . ') ' . $jsonResult['detail']);
						$resultjson = json_decode($result, true);
						$value = $resultjson['value'];
					}
		
				
		if (($this->getType() == 'action') && (is_array($this->getConfiguration('infoNameArray')))) {
			foreach ($this->getConfiguration('infoNameArray') as $LogicalIdCmd) {
				$cmd=$this->getEqLogic()->getCmd(null, $LogicalIdCmd);
				if (is_object($cmd)) { 
					$this->getEqLogic()->checkAndUpdateCmd($LogicalIdCmd, $resultjson[0][$LogicalIdCmd]);					
					//log::add('alexaamazonmusic', 'info', $LogicalIdCmd.' prévu dans infoNameArray de '.$this->getName().' trouvé ! '.$resultjson[0]['whennextmusicalalarminfo'].' OK !');
				} else {
					log::add('alexaamazonmusic', 'warning', $LogicalIdCmd.' prévu dans infoNameArray de '.$this->getName().' mais non trouvé ! donc ignoré');
				} 
			}
		} 
		elseif (($this->getType() == 'action') && ($this->getConfiguration('infoName') != '')) {
			// Boucle non testée !!
				$LogicalIdCmd=$this->getConfiguration('infoName');
				$cmd=$this->getEqLogic()->getCmd(null, $LogicalIdCmd);
				if (is_object($cmd)) { 
					$this->getEqLogic()->checkAndUpdateCmd($LogicalIdCmd, $resultjson[$LogicalIdCmd]);
				} else {
					log::add('alexaamazonmusic', 'warning', $LogicalIdCmd.' prévu dans infoName de '.$this->getName().' mais non trouvé ! donc ignoré');
				} 
		}
		log::add('alexaamazonmusic', 'info', ' ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════');
		return true;
	}


	private function buildRequest($_options = array()) {
		if ($this->getType() != 'action') return $this->getConfiguration('request');
		list($command, $arguments) = explode('?', $this->getConfiguration('request'), 2);
		log::add('alexaamazonmusic', 'info', ' ');
		log::add('alexaamazonmusic', 'info', ' ╔══════════════════════[command : *'.$command.'* Request:'.json_encode($_options).']═════════════════════════════════════════════════════════');
		//log::add('alexaamazonmusic', 'info', '----Command:*'.$command.'* Request:'.json_encode($_options));
		switch ($command) {
            case 'textCommand':
                $request = $this->build_ControledeSliderSelectMessage($_options);
                break;
			case 'volume':
				$request = $this->build_ControledeSliderSelectMessage($_options, '50');
			break;
			case 'playlist':
				$request = $this->build_ControledeSliderSelectMessage($_options, "");
			break;			
			case 'playmusictrack':
				$request = $this->build_ControledeSliderSelectMessage($_options, "53bfa26d-f24c-4b13-97a8-8c3debdf06f0");
			break;				
			case 'radio':
				$request = $this->build_ControledeSliderSelectMessage($_options, 'France+Info');
			break;
			case 'command':
				$request = $this->build_ControledeSliderSelectMessage($_options, 'pause');
			break;
			case 'restart':
				$request = $this->buildRestartRequest($_options);
			break;				
			default:
				$request = '';
			break;			
		}
		//log::add('alexaamazonmusic_debug', 'debug', '----RequestFinale:'.$request);
		$request = scenarioExpression::setTags($request);
		if (trim($request) == '') throw new Exception(__('Commande inconnue ou requête vide : ', __FILE__) . print_r($this, true));
		$device=str_replace("_player", "", $this->getEqLogic()->getConfiguration('serial'));
		
		if ((strlen($device)>20) && (strpos($request, "textCommand?") !== false)) // On va chercher un device car le serial des groupes ne fonctionne pas.
		{
		$groupName=str_replace(" Player", "", $this->getEqLogic()->getName());
		$groupName=str_replace(" ", "+", $groupName);
			// C'est un groupe dans une commande textCommand
		//log::add('alexaapi', 'debug', 'C est un groupe device=' . $device);
		$request=$request."+sur+le+groupe+".$groupName;
		//log::add('alexaapi', 'debug', 'name=' . $deviceName);
					
			$device=config::byKey('defaultDevice','alexaapi','',true);
			if ($device==""){
					log::add('alexaamazonmusic', 'warning', ' ╔═══════════════════════════════[Souci de configuration]══════════════════════════════════════════════════════════════');
					log::add('alexaamazonmusic', 'warning', " ║ La commande n'a pas fonctionné puisqu'aucun appareil n'est défini par défaut dans la configuration");
					log::add('alexaamazonmusic', 'warning', ' ╚═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════');
			}	
		}
		//log::add('alexaapi', 'debug', 'request=' . $request);
		log::add('alexaamazonmusic', 'debug', '----url:'.'http://' . config::byKey('internalAddr') . ':3456/' . $request . '&device=' . $device);
		return 'http://' . config::byKey('internalAddr') . ':3456/' . $request . '&device=' . $device;
	}

	private function build_ControledeSliderSelectMessage($_options = array(), $default = "Ceci est un message de test") {
		$cmd=$this->getEqLogic()->getCmd(null, 'volumeinfo');
		if (is_object($cmd))
			$lastvolume=$cmd->execCmd();
		
		$request = $this->getConfiguration('request');
		
		
		//log::add('alexaapi', 'info', '1---->request:'.$request);
		// Rustine dans le cas d'anciennes commandes. Ces commandes ne fonctionnaient plus depuis des modifs d'Amazon - 09/21 Sigalou
		if ($request=="playlist?playlist=#select#") 
			{
			$request="textCommand?text=Joue+la+playlist+#select#";
			if (isset($_options['select'])) $_options['select'] = str_replace(" ", "+", $_options['select']);
			}			
		if ($request=="radio?station=#select#") 
			{
			//$request="textCommand?text=Joue+la+station+#select#+sur+TuneIn";
			$request="textCommand?text=Joue+la+station+#select#";
			if (isset($_options['select'])) $_options['select'] = str_replace(" ", "+", $_options['select']);
			}		
		if ($request=="playmusictrack?trackId=#select#") 
			{
			$request="textCommand?text=#select#";
			if (isset($_options['select'])) $_options['select'] = str_replace(" ", "+", $_options['select']);
			}
		//log::add('alexaapi', 'info', '2---->request:'.$request);
		
		
		//log::add('alexaamazonmusic_node', 'info', '---->Request2:'.$request);
		//log::add('alexaamazonmusic_node', 'debug', '---->getName:'.$this->getEqLogic()->getCmd(null, 'volumeinfo')->execCmd());
		if ((isset($_options['slider'])) && ($_options['slider'] == "")) $_options['slider'] = $default;
		if ((isset($_options['select'])) && ($_options['select'] == "")) $_options['select'] = $default;
		if ((isset($_options['message'])) && ($_options['message'] == "")) $_options['message'] = $default;
		// Si on est sur une commande qui utilise volume, on va remettre après execution le volume courant
		if (strstr($request, '&volume=')) $request = $request.'&lastvolume='.$lastvolume;
		// Pour eviter l'absence de déclaration :
		if (isset($_options['slider'])) $_options_slider = $_options['slider']; else $_options_slider="";
		if (isset($_options['select'])) $_options_select = $_options['select']; else $_options_select="";
		if (isset($_options['message'])) $_options_message = $_options['message']; else $_options_message="";
		if (isset($_options['volume'])) $_options_volume = $_options['volume']; else $_options_volume="";
		$request = str_replace(array('#slider#', '#select#', '#message#', '#volume#'), 
		//array($_options_slider, $_options_select, urlencode(self::decodeTexteAleatoire($_options_message)), $_options_volume), $request);
		array($_options_slider, $_options_select, urlencode(self::decodeTexteAleatoire($_options_message)), $_options_volume), $request);
		//log::add('alexaamazonmusic_node', 'info', '---->RequestFinale:'.$request);
		return $request;
	}	
	
	public static function decodeTexteAleatoire($_text) {
		$return = $_text;
		if (strpos($_text, '|') !== false && strpos($_text, '[') !== false && strpos($_text, ']') !== false) {
			$replies = interactDef::generateTextVariant($_text);
			$random = rand(0, count($replies) - 1);
			$return = $replies[$random];
		}
		preg_match_all('/{\((.*?)\) \?(.*?):(.*?)}/', $return, $matches, PREG_SET_ORDER, 0);
		$replace = array();
		if (is_array($matches) && count($matches) > 0) {
			foreach ($matches as $match) {
				if (count($match) != 4) {
					continue;
				}
				$replace[$match[0]] = (jeedom::evaluateExpression($match[1])) ? trim($match[2]) : trim($match[3]);
			}
		}
		return str_replace(array_keys($replace), $replace, $return);
	}


	private function buildRestartRequest($_options = array()) {
		log::add('alexaamazonmusic_debug', 'debug', '------buildRestartRequest---UTILISE QUAND ???--A simplifier--------------------------------------');
		$request = $this->getConfiguration('request')."?truc=vide";
		return str_replace('#volume#', $_options['slider'], $request);
	}
	
	//public function getWidgetTemplateCode($_version = 'dashboard', $_noCustom = false) {
    public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {

		//if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_noCustom);
        if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
		list($command, $arguments) = explode('?', $this->getConfiguration('request'), 2);
		if ($command == 'command' && strpos($arguments, '#select#')) 
			return getTemplate('core', 'scenario', 'cmd.command', 'alexaamazonmusic');
//		return parent::getWidgetTemplateCode($_version, $_noCustom);
        return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

	}
}