<?php

	Class extension_deployer extends Extension{

		public function about(){
			return array('name' => 'Deployer',
						 'version' => '0.01',
						 'release-date' => '2010-12-11',
						 'author' => array('name' => 'Lucio Tarantino',
										   'website' => '-',
										   'email' => 'lucio.tarantino@gmail.com'),
						 'description' => 'Allow to deploy symphonycms to a production enironment'
				 		);
		}

		public static function baseURL(){
			return URL . '/symphony/extension/deployer/';
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'System',
					'name'		=> 'Deployer',
					'link'		=> '/deployer/'
				)
			);
		}
		
		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),

					);
		}
		
		public function install(){
			
			if(!class_exists('ZipArchive')){
				Administration::instance()->Page->pageAlert(__('Export Ensemble cannot be installed, since the "<a href="http://php.net/manual/en/book.zip.php">ZipArchive</a>" class is not available. Ensure that PHP was compiled with the <code>--enable-zip</code> flag.'), AdministrationPage::PAGE_ALERT_ERROR);
				return false;
			}
			
			return true;
		}
		
		private function __addFolderToArchive(&$archive, $path, $parent=NULL){
			$iterator = new DirectoryIterator($path);
			foreach($iterator as $file){
				if($file->isDot() || preg_match('/^\./', $file->getFilename())) continue;
				else if($file->isDir()){
					$this->__addFolderToArchive($archive, $file->getPathname(), $parent);
				}

				else $archive->addFile($file->getPathname(), ltrim(str_replace($parent, NULL, $file->getPathname()), '/'));
			}
		}
		
		public function export(){
			$sql_schema = $sql_data = NULL;

/*			
			if(isset($div)){
				$ul = new XMLElement('ul');
				$div->appendChild($ul);
				$ul->appendChild(new XMLElement('il', 'Database: Collecting Database Data'));
			}
*/			
			require_once(dirname(__FILE__) . '/lib/class.mysqldump.php');
			
			$dump = new MySQLDump(Symphony::Database());

			$rows  = Symphony::Database()->fetch("SHOW TABLES LIKE '" . Administration::instance()->Configuration->get('tbl_prefix', 'database') . "_%';");		
			$rows = array_map (create_function ('$x', 'return array_values ($x);'), $rows);
			$tables = array_map (create_function ('$x', 'return $x[0];'), $rows);
//			$alltables = $tables;
			
			
			## Grab the schema
//			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab the schema'));

			foreach($tables as $t) $sql_schema .= $dump->export($t, MySQLDump::STRUCTURE_ONLY);
			$sql_schema = str_replace('`' . Administration::instance()->Configuration->get('tbl_prefix', 'database'), '`tbl_', $sql_schema);
			
			$sql_schema = preg_replace('/ AUTO_INCREMENT=\d+/i', NULL, $sql_schema);

/*			
			$tables = array(
				'tbl_entries',
				'tbl_extensions',
				'tbl_extensions_delegates',
				'tbl_fields',			
				'tbl_pages',
				'tbl_pages_types',
				'tbl_sections',
				'tbl_sections_association'			
			);			
*/			
			## Field data and entry data schemas and unknown tables needs to be apart of the workspace sql dump
//			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab data and schamas for workspace'));
			$sql_data = '';
			foreach($tables as $t){
				$t = str_replace(Administration::instance()->Configuration->get('tbl_prefix', 'database'), 'tbl_', $t);
				if($t == "tbl_cache" || $t == "tbl_sessions" || $t == "tbl_authors")continue;
				$sql_data .= $dump->export($t, MySQLDump::DATA_ONLY);
			}
			
			## Grab the data
//			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab data for workspace'));
//			foreach($tables as $t){
//				$sql_data .= $dump->export($t, MySQLDump::DATA_ONLY);
//			}
			
			$sql_data = str_replace('`' . Administration::instance()->Configuration->get('tbl_prefix', 'database'), '`tbl_', $sql_data);
			
			$config_string = NULL;
			$config = Administration::instance()->Configuration->get();	

//			if(isset($div))$ul->appendChild(new XMLElement('il', 'Config: cleaning config'));
			
			unset($config['symphony']['build']);
			unset($config['symphony']['cookie_prefix']);
			unset($config['general']['useragent']);
			unset($config['file']['write_mode']);
			unset($config['directory']['write_mode']);
			unset($config['database']['host']);
			unset($config['database']['port']);
			unset($config['database']['user']);
			unset($config['database']['password']);
			unset($config['database']['db']);
			unset($config['database']['tbl_prefix']);
			unset($config['region']['timezone']);

			foreach($config as $group => $set){
				foreach($set as $key => $val){
					$config_string .= "		\$conf['{$group}']['{$key}'] = '{$val}';" . self::CRLF;
				}
			}

//			if(isset($div))$ul->appendChild(new XMLElement('il', 'Config: mark build,version and configuration'));
			
			$install_template = str_replace(
				
									array(
										'<!-- BUILD -->',
										'<!-- VERSION -->',
										'<!-- CONFIGURATION -->'
									),
				
									array(
										Administration::instance()->Configuration->get('build', 'symphony'),
										Administration::instance()->Configuration->get('version', 'symphony'),
										trim($config_string),										
									),
				
									file_get_contents(dirname(__FILE__) . '/lib/installer.tpl')
			);
			
//			if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: colleting files'));
			$archive = new ZipArchive;
//			$res = $archive->open(TMP . '/ensemble.tmp.zip', ZipArchive::CREATE);
			$fileName = 'ensemble.'.gmdate('Ymd.His').'.zip';
			$res = $archive->open(self::getStorageUrl() . '/'.$fileName, ZipArchive::CREATE);

			if ($res === TRUE) {
				
//				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Extensions'));
				$this->__addFolderToArchive($archive, EXTENSIONS, DOCROOT);
//				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Symphony Engine'));
				$this->__addFolderToArchive($archive, SYMPHONY, DOCROOT);
//				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Workspace'));
				$this->__addFolderToArchive($archive, WORKSPACE, DOCROOT);
				
//				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: colleting install scripts'));
				$archive->addFromString('install.php', $install_template);
				$archive->addFromString('install.sql', $sql_schema);
				$archive->addFromString('workspace/install.sql', $sql_data);
				
				$archive->addFile(DOCROOT . '/index.php', 'index.php');
				
				$readme_files = glob(DOCROOT . '/README.*');
				if(is_array($readme_files) && !empty($readme_files)){
					foreach($readme_files as $filename){
						$archive->addFile($filename, basename($filename));	
					}
				}
				
				if(is_file(DOCROOT . '/README')) $archive->addFile(DOCROOT . '/README', 'README');
				if(is_file(DOCROOT . '/LICENCE')) $archive->addFile(DOCROOT . '/LICENCE', 'LICENCE');
				if(is_file(DOCROOT . '/update.php')) $archive->addFile(DOCROOT . '/update.php', 'update.php');
				if(is_file(DOCROOT . '/.htaccess')) $archive->addFile(DOCROOT . '/.htaccess', 'META-INF/.htaccess');

				// Save Config Template
				$config_tpl = Administration::instance()->Configuration->get();	
				$config_tpl['database']['character_set']='%%DBCHARSET%%';
				$config_tpl['database']['character_encoding']='%%DBCHARENCODING%%';
				$config_tpl['database']['runtime_character_set_alter']='%%DBRUNTIMECHARSETALTER%%';
				$config_tpl['database']['query_caching']='%%DBCACHE%%';
				$config_tpl['database']['host']='%%DBHOST%%';
				$config_tpl['database']['port']='%%DBPORT%%';
				$config_tpl['database']['user']='%%DBUSER%%';
				$config_tpl['database']['password']='%%DBPASSWORD%%';
				$config_tpl['database']['db']='%%DB%%';
				$config_tpl['database']['tbl_prefix']='%%DBPREFIX%%';
				$config_tpl['maintenance_mode']['enabled']='%%MAINTENCEMODE%%';
				$config_tpl_obj= new Configuration();
				$config_tpl_obj->setArray($config_tpl);
				$archive->addFromString('META-INF/config.php',"<?php \n\$settings =".$config_tpl_obj->__toString().";\n?>");

			
				// Adding deploy.php
				$deploy_template="<?php \n\$dpy_fileName='".$fileName."'; \n\$dpy_ensambleDate='".gmdate('d M Y H:i:s')."';\n\$dpy_deployDate='%%DEPLOY_DATE%%';\n\$dpy_deployMode='%%DEPLOY_MODE%%'\n?>";
				$archive->addFromString('META-INF/deploy.php', $deploy_template);
			}
			
			$archive->close();
/*
			header('Content-type: application/octet-stream');	
			header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			
		    header(
				sprintf(
					'Content-disposition: attachment; filename=%s-ensemble.zip', 
					Lang::createFilename(
						Administration::instance()->Configuration->get('sitename', 'general')
					)
				)
			);
			
		    header('Pragma: no-cache');
		
			readfile(TMP . '/ensemble.tmp.zip');
			unlink(TMP . '/ensemble.tmp.zip');
			exit();
*/			
		}

		public function __SavePreferences($context){
			$this->__export();
		}
		
		public function savePreferences($context){

			$conf = array_map('trim', $context['settings']['deployer']);
			
			$context['settings']['deployer'] = array(
														  'auto-maintence' => (isset($conf['auto-maintence']) ? 'yes' : 'no'),
														  'storage-url' => (isset($conf['storage-url']) ? $conf['storage-url'] : DOCROOT . '/backup'),
														  'deploy-url' => (isset($conf['deploy-url']) ? $conf['deply-url'] : '')
														);

		}
	
			
		public function appendPreferences($context){
			
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Deployer'));
			
			$label = Widget::Label('Deploy Path');
			$label->appendChild(Widget::Input('settings[deployer][deploy-url]', General::Sanitize(Administration::instance()->Configuration->get('deploy-url', 'deployer'))));		
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'This is the path where extract deployer Ensemble', array('class' => 'help')));

			$label = Widget::Label('Storage Path');
			$label->appendChild(Widget::Input('settings[deployer][storage-url]', General::Sanitize(Administration::instance()->Configuration->get('storage-url', 'deployer'))));		
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'This is the path where deployer Ensemble are stored ('. DOCROOT . '/backup)', array('class' => 'help')));

/*
			$label = Widget::Label('Maintence URL');
			$label->appendChild(Widget::Input('settings[filemanager][archive-name]', General::Sanitize(Administration::instance()->Configuration->get('archive-name', 'filemanager'))));		
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Default filename for archives generated by File Manager', array('class' => 'help')));
*/						

			$label = Widget::Label();
			$input = Widget::Input('settings[deployer][auto-maintence]', 'yes', 'checkbox');
			if(Administration::instance()->Configuration->get('auto-maintence', 'deployer') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Automatc Maintence mode on deploy');
			$group->appendChild($label);

			$group->appendChild(new XMLElement('p', 'Puts the production site in "Maintence Mode" automatically during the deployment operation.', array('class' => 'help')));
			$context['wrapper']->appendChild($group);
						
		}

		
		public function getDeployUrl(){
			return Administration::instance()->Configuration->get('deploy-url', 'deployer');
		}

		public function getStorageUrl(){
			return Administration::instance()->Configuration->get('storage-url', 'deployer');
		}
		
			
/*			
		public function appendPreferences($context){
			if(isset($_POST['action']['export'])){
				$this->__SavePreferences($context);
			}
			
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Export Ensemble')));			
			

			$div = new XMLElement('div', NULL, array('id' => 'file-actions', 'class' => 'label'));			
			$span = new XMLElement('span');
			
			if(!class_exists('ZipArchive')){
				$span->appendChild(
					new XMLElement('p', '<strong>' . __('Warning: It appears you do not have the "ZipArchive" class available. Ensure that PHP was compiled with <code>--enable-zip</code>') . '<strong>')
				);
			}
			else{
				$span->appendChild(new XMLElement('button', __('Create'), array('name' => 'action[export]', 'type' => 'submit')));	
			}
			
			$div->appendChild($span);

			$div->appendChild(new XMLElement('p', __('Packages entire site as a <code>.zip</code> archive for download.'), array('class' => 'help')));	

			$group->appendChild($div);						
			$context['wrapper']->appendChild($group);
						
		}
*/	

		public function download($file){
			
			if(!file_exists($file)) 
				$this->_Parent->customError(E_USER_ERROR, 'File Not Found', 'The file you requested, <code>'.$file.'</code>, does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
				
			header('Pragma: public');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Content-Type: ' . self::getFileMIMEType($file));
			header('Content-Disposition: attachment; filename=' . basename($file) . ';');
			header('Content-Length: ' . filesize($file));

			readfile($file);

			break;				
				
		}
		
/*		
		function fireSql(&$db, $data, &$error, $compatibility='NORMAL'){
			$compatibility = strtoupper($compatibility);
	
			if($compatibility == 'HIGH'){	
				$data = str_replace('ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci', NULL, $data);
				$data = str_replace('collate utf8_unicode_ci', NULL, $data);
			}
	
			## Silently attempt to change the storage engine. This prevents INNOdb errors.
			$db->query('SET storage_engine=MYISAM', $e);
			
			$queries = preg_split('/;[\\r\\n]+/', $data, -1, PREG_SPLIT_NO_EMPTY);
	
			if(is_array($queries) && !empty($queries)){                                
			    foreach($queries as $sql) {
				if(strlen(trim($sql)) > 0) $result = $db->query($sql);
				if(!$result){ 
							$err = $db->getLastError();
							$error = $err['num'] . ': ' . $err['msg'];
							return false;
						}
			    }
			}
				
			return true;

		}
*/


		public function deploy($file){
					
			$deploy_log = new Log('deploy-log.txt');
			$start=time();
			
			$deploy_log->writeToLog("============================================", true);
			$deploy_log->writeToLog("DEPLOY STARTED (".$file.") at ".date("d.m.y H:i:s"), true);
			$deploy_log->writeToLog("============================================", true);

			
			if(!file_exists($file)){ 
				$deploy_log->writeToLog("FATAL: ".$file. " file not found!", true);
				$this->_Parent->customError(E_USER_ERROR, 'File Not Found', 'The file you requested, <code>'.$file.'</code>, does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
			}
			
			$zip = new ZipArchive;
			if ($zip->open($file) === TRUE) {
				$deploy_log->writeToLog("INFO: Extracting file to ".$this->getDeployUrl(), true);
				$zip->extractTo($this->getDeployUrl());
				$zip->close();
				$deploy_log->writeToLog("INFO: Extracting done.", true);
				// Execute SQL
				if(file_exists($this->getDeployUrl().'/manifest/config.php')){
					require($this->getDeployUrl().'/manifest/config.php');
					$deploy_log->writeToLog("INFO: Found installed environment! ", true);
					$deploy_log->writeToLog("INFO: Connecting to DB".var_export($settings['database'],true), true);
//					$dpy_status = ($settings['maintenance_mode']['enabled'] == 'no')?'Production':'Maintence';
					$mysql = new MySQL();
					$mysql->connect($settings['database']['host'],
							$settings['database']['user'], 
							$settings['database']['password'], 
							$settings['database']['port']);
					$mysql->setCharacterSet($settings['database']['character_set']);
					$mysql->setCharacterEncoding($settings['database']['character_encoding']);
					$mysql->select($settings['database']['db']);
					$mysql->setPrefix($settings['database']['tbl_prefix']);
							
					// Check if SOFT DEPLOY IS POSSIBLE
					require_once(dirname(__FILE__) . '/lib/class.mysqldump.php');			
					$dump = new MySQLDump($mysql);
					
					$deploy_log->writeToLog("INFO: Starting DB Sync", true);
					// Usare firesql to manage errors
					$deployType='SOFT';
					$deploy_log->writeToLog("INFO: Recreate db schema..START", true);
					$handle = fopen($this->getDeployUrl().'/install.sql', "r");
					while (($sql = fgets($handle, 4096)) !== false) {
						 $query.=$sql;
						 if(trim($sql) == '' && trim($query) != ''){
							if(preg_match('/.*TABLE `(.*)`.*/', $query,$matches) != 0){
								$deploy_log->writeToLog("INFO: Table name:'".$matches[1]."'", true);
								$sql_schema = $dump->export($matches[1], MySQLDump::STRUCTURE_ONLY);
								$sql_schema = str_replace('`' . $settings['database']['tbl_prefix'], '`tbl_', $sql_schema);
								$sql_schema = preg_replace('/ AUTO_INCREMENT=\d+/i', NULL, $sql_schema);
							};
							if(strcmp(trim($sql_schema),trim($query)) != 0){
								$deploy_log->writeToLog("INFO: FI:'".trim($query)."'", true);
								$deploy_log->writeToLog("INFO: DB:'".trim($sql_schema)."'", true);
								$deploy_log->writeToLog("INFO: FI:'".md5(trim($query))."' DB:'".md5(trim($sql_schema))."'", true);
								if($deployType=='SOFT'){
									$deployType='HARD';
									$deploy_log->writeToLog("INFO: Switch to HARD Deploy Mode....", true);
								}
								$mysql->import($query);
							} else {
								$deploy_log->writeToLog("INFO: No update schema needed for table ".$matches[1], true);
							}
						 	 $query='';
						 }
					}        
					fclose($handle);
					$deploy_log->writeToLog("INFO: Recreate db schema..END", true);
					
					
					$query='';
					$deploy_log->writeToLog("INFO: Importing workspace data..(".$deployType." mode )START", true);
					$handle = fopen($this->getDeployUrl().'/workspace/install.sql', "r");
					while (($sql = fgets($handle, 4096)) !== false) {
						 $query.=$sql;
						 if(trim($sql) == '' && trim($query) != ''){
						 	 $query = preg_replace('/INSERT/i', 'REPLACE', $query);
						 	 $mysql->import($query);
						 	 $query='';
						 }
					}        
					fclose($handle);
					$deploy_log->writeToLog("INFO: Importing workspace data..END", true);
				// Merge Config
					$deploy_log->writeToLog("INFO: Merging Config...", true);
					$handle = fopen($this->getDeployUrl()."/META-INF/config.php", "rb");
					$content = fread($handle,filesize($this->getDeployUrl()."/META-INF/config.php"));
					fclose($handle);
					$content = str_replace("%%DBCHARSET%%", $settings['database']['character_set'], $content);
					$content = str_replace("%%DBCHARENCODING%%", $settings['database']['character_encoding'], $content);
					$content = str_replace("%%DBRUNTIMECHARSETALTER%%", $settings['database']['runtime_character_set_alter'], $content);
					$content = str_replace("%%DBCACHE%%", $settings['database']['query_caching'], $content);
					$content = str_replace("%%DBHOST%%", $settings['database']['host'], $content);
					$content = str_replace("%%DBPORT%%", $settings['database']['port'], $content);
					$content = str_replace("%%DBUSER%%", $settings['database']['user'], $content);
					$content = str_replace("%%DBPASSWORD%%", $settings['database']['password'], $content);
					$content = str_replace("%%DB%%",$settings['database']['db'],$content);
					$content = str_replace("%%DBPREFIX%%", $settings['database']['tbl_prefix'], $content);
					$content = str_replace("%%MAINTENCEMODE%%", $settings['maintenance_mode']['enabled'], $content);
					$handle = fopen($this->getDeployUrl()."/manifest/config.php", "wb");
					$numbytes = fwrite($handle, $content);
					fclose($handle);
					
					// Move .htaccess
					$deploy_log->writeToLog("INFO: Moving .htaccess to root. Result (1=OK): ".
						rename($this->getDeployUrl()."/META-INF/.htaccess",$this->getDeployUrl()."/.htaccess"), true);					
					
				}
				// Mark Deploy Date
				$deploy_log->writeToLog("INFO: Mark deploy date", true);
				$handle = fopen($this->getDeployUrl()."/META-INF/deploy.php", "rb");
				$content = fread($handle,filesize($this->getDeployUrl()."/META-INF/deploy.php"));
				fclose($handle);
				$handle = fopen($this->getDeployUrl()."/META-INF/deploy.php", "wb");
				$content = str_replace("%%DEPLOY_DATE%%", gmdate('d M Y H:i:s'), $content);
				$content = str_replace("%%DEPLOY_MODE%%", $deployType, $content);
				$numbytes = fwrite($handle, $content);
				fclose($handle);
				
				
				
			} else {
				$this->_Parent->customError(E_USER_ERROR, 'Deploy Failed', 'The file you requested, <code>'.$file.'</code>, can\'t be extract to deploy location.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
			}
			$deploy_log->writeToLog("============================================", true);
			$deploy_log->writeToLog("DEPLOY COMPLETED: Execution Time - ".max(1, time() - $start)." sec (" . date("d.m.y H:i:s") . ")", true);
			$deploy_log->writeToLog("============================================", true);
			
		}
		
		
		
		public static function getFileMIMEType($file){

			$types = array(
				'/.(jpg|jpeg)$/i'  => 'image/jpeg',
				'/.gif$/i'         => 'image/gif',
				'/.png$/i'         => 'image/png',
				'/.pdf$/i'         => 'application/pdf'
			);

			foreach ($types as $pattern => $mimetype) {
				if (preg_match($pattern, $file)) return $mimetype;
			}

			return 'application/octet-stream';
	
		}
		
		
		
		public function productionMaintence($status){
				if(file_exists($this->getDeployUrl().'/manifest/config.php')){
					require($this->getDeployUrl().'/manifest/config.php');
					$oldStatus = $settings['maintenance_mode']['enabled'];
					$settings['maintenance_mode']['enabled']=$status?'yes':'no';
					$config_tpl_obj= new Configuration();
					$config_tpl_obj->setArray($settings);
					$handle = fopen($this->getDeployUrl()."/manifest/config.php", "wb");
					$numbytes = fwrite($handle,"<?php \n\$settings =".$config_tpl_obj->__toString().";\n?>");
					fclose($handle);
					return $oldStatus;
				}
		}
		
	}