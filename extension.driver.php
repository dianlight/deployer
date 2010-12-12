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
		
		public function export($div = NULL){
			$sql_schema = $sql_data = NULL;
			
			if(isset($div)){
				$ul = new XMLElement('ul');
				$div->appendChild($ul);
				$ul->appendChild(new XMLElement('il', 'Database: Collecting Database Data'));
			}
			
			require_once(dirname(__FILE__) . '/lib/class.mysqldump.php');
			
			$dump = new MySQLDump(Symphony::Database());

			$rows  = Symphony::Database()->fetch("SHOW TABLES LIKE '" . Administration::instance()->Configuration->get('tbl_prefix', 'database') . "_%';");		
			$rows = array_map (create_function ('$x', 'return array_values ($x);'), $rows);
			$tables = array_map (create_function ('$x', 'return $x[0];'), $rows);
			$alltables = $tables;
			
			
			## Grab the schema
			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab the schema'));

			foreach($tables as $t) $sql_schema .= $dump->export($t, MySQLDump::STRUCTURE_ONLY);
			$sql_schema = str_replace('`' . Administration::instance()->Configuration->get('tbl_prefix', 'database'), '`tbl_', $sql_schema);
			
			$sql_schema = preg_replace('/AUTO_INCREMENT=\d+/i', NULL, $sql_schema);
			
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
			
			## Field data and entry data schemas and unknown tables needs to be apart of the workspace sql dump
			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab data and schamas for workspace'));
			$sql_data = '';
			foreach($alltables as $t){
				$t = str_replace(Administration::instance()->Configuration->get('tbl_prefix', 'database'), 'tbl_', $t);
				if($t == "tbl_cache" || $t == "tbl_sessions" || $t == "tbl_authors" || in_array($t,$tables) )continue;
				$sql_data .= $dump->export($t, MySQLDump::ALL);
			}
			
			## Grab the data
			if(isset($div))$ul->appendChild(new XMLElement('il', 'Database: Grab data for workspace'));
			foreach($tables as $t){
				$sql_data .= $dump->export($t, MySQLDump::DATA_ONLY);
			}
			
			$sql_data = str_replace('`' . Administration::instance()->Configuration->get('tbl_prefix', 'database'), '`tbl_', $sql_data);
			
			$config_string = NULL;
			$config = Administration::instance()->Configuration->get();	

			if(isset($div))$ul->appendChild(new XMLElement('il', 'Config: cleaning config'));
			
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

			if(isset($div))$ul->appendChild(new XMLElement('il', 'Config: mark build,version and configuration'));
			
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
			
			if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: colleting files'));
			$archive = new ZipArchive;
//			$res = $archive->open(TMP . '/ensemble.tmp.zip', ZipArchive::CREATE);
			$res = $archive->open(self::getStorageUrl() . '/ensemble.'.gmdate('Ymd.His').'.zip', ZipArchive::CREATE);

			if ($res === TRUE) {
				
				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Extensions'));
				$this->__addFolderToArchive($archive, EXTENSIONS, DOCROOT);
				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Symphony Engine'));
				$this->__addFolderToArchive($archive, SYMPHONY, DOCROOT);
				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: adding Workspace'));
				$this->__addFolderToArchive($archive, WORKSPACE, DOCROOT);
				
				if(isset($div))$ul->appendChild(new XMLElement('il', 'ZIP: colleting install scripts'));
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
				if(is_file(DOCROOT . '/.htaccess')) $archive->addFile(DOCROOT . '/.htaccess', '.htaccess');

			
				// Adding deploy.php
				$deploy_template="<?php \n\$dpy_fileName='ensemble.".gmdate('Ymd.His').".zip'; \n\$dpy_deployDate='".gmdate('d M Y H:i:s')."';\n?>";
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
														//  'show-hidden' => (isset($conf['show-hidden']) ? 'yes' : 'no'),
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

			$label = Widget::Label();
			$input = Widget::Input('settings[filemanager][show-hidden]', 'yes', 'checkbox');
			if(Administration::instance()->Configuration->get('show-hidden', 'filemanager') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Append Version/Date to archive');
			$group->appendChild($label);

			$group->appendChild(new XMLElement('p', 'Hidden files will not be included in archives unless this is checked.', array('class' => 'help')));
*/						
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
					$deploy_log->writeToLog("INFO: Found installed environment! Connecting to DB", true);
					require_once($this->getDeployUrl().'/manifest/config.php');
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
							
					
					$deploy_log->writeToLog("INFO: Starting DESTRUCTIVE deploy", true);
					// Usare firesql to manage errors
					$deploy_log->writeToLog("INFO: Recreate db schema..START", true);
					$handle = fopen($this->getDeployUrl().'/install.sql', "r");
					while (($sql = fgets($handle, 4096)) !== false) {
						 $query.=$sql."\r\n";
						 if(trim($sql) == '' && trim($query) != ''){
						 	 $mysql->import($query);
						 	 $query='';
						 }
					}        
					fclose($handle);
					$deploy_log->writeToLog("INFO: Recreate db schema..END", true);
					
					
					$query='';
					$deploy_log->writeToLog("INFO: Importing workspace data..START", true);
					$handle = fopen($this->getDeployUrl().'/workspace/install.sql', "r");
					while (($sql = fgets($handle, 4096)) !== false) {
						 $query.=$sql."\r\n";
						 if(trim($sql) == '' && trim($query) != ''){
						 	 $mysql->import($query);
						 	 $query='';
						 }
					}        
					fclose($handle);
					$deploy_log->writeToLog("INFO: Importing workspace data..END", true);					
				}								
			} else {
				$this->_Parent->customError(E_USER_ERROR, 'Deploy Failed', 'The file you requested, <code>'.$file.'</code>, can\'t be extract to deploy location.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
			}
			$deploy_log->writeToLog("============================================", true);
			$deploy_log->writeToLog("DEPLOY COMPLETED: Execution Time - ".max(1, time() - $start)." sec (" . date("d.m.y H:i:s") . ")", true);
			$deploy_log->writeToLog("============================================", true);
			redirect(extension_deployer::baseURL() . 'deployer/');
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
		
	}