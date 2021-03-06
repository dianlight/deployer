<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionDeployerDeployer extends AdministrationPage{

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Deployer');
		}
	
		function action(){
			
			$checked = @array_keys($_POST['items']);


			
			if(!isset($_POST['action']['apply']) || empty($checked)) return;
			
			$Deployer =& $this->_Parent->ExtensionManager->create('deployer');		

			
			
			switch($_POST['with-selected']){
				
				case 'delete':
				
					$path = $Deployer->getStorageUrl();
					
					foreach($checked as $rel_file){					
						if(!is_dir($rel_file) && file_exists($rel_file)) General::deleteFile($rel_file);
						elseif(is_dir($rel_file)){
							
							if(!@rmdir($rel_file))
								$this->pageAlert(
									__(
										'%s could not be deleted as is still contains files.',
										array('<code>'.$rel_file.'</code>')
									),
									AdministrationPage::PAGE_ALERT_ERROR
								);
						}
						
					}
					
					break;
					
//				case 'deploy':
//				
//					$path = (is_array($this->_context) && !empty($this->_context) ? '/' . implode('/', $this->_context) . '/' : NULL);
//					$filename = $FileManager->createArchive($checked, $path);
//					
//					break;					
			}
		}

		function view(){
			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/deployer/assets/styles.css', 'screen', 70);

			$Deployer =& $this->_Parent->ExtensionManager->create('deployer');

			$path = $Deployer->getStorageUrl();
			
			if(is_writable($path)) {
				// Build file/dir creation menu
				$create_menu = new XMLElement('ul');
				$create_menu->setAttribute('class', 'create-menu');
			
				$li = new XMLElement('li');
				$li->appendChild(Widget::Anchor(__('Make Snapshot'), extension_deployer::baseURL() . 'new/snapshot/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'New Snapshot', 'button create'));
				$create_menu->appendChild($li);
			
//				$li = new XMLElement('li');
//				$li->appendChild(Widget::Anchor(__('Snapshot and Deploy'), extension_deployer::baseURL() . 'new/file/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'New File', 'button create'));
//				$create_menu->appendChild($li);
			
//				$li = new XMLElement('li');
//				$li->appendChild(Widget::Anchor('Upload File', extension_filemanager::baseURL() . 'new/upload/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'Upload File', 'button create'));
//				$create_menu->appendChild($li);
			} else if(file_exists($path)) {
				$create_menu = new XMLElement('p',__('This directory is not writable'));
				$create_menu->setAttribute('class','create-menu');
			} else {
				$create_menu = new XMLElement('p',__('This directory not exists'));
				$create_menu->setAttribute('class','create-menu');
			}

			$this->setPageType('table');
			$this->appendSubheading(__("Storage").' '.__('path').": ".$Deployer->getStorageUrl());
			$this->Form->appendChild($create_menu);

			$aTableHead = array(

				array(__('Name'), 'col'),
				array(__('Size'), 'col'),
				array(__('Date'), 'col'),
				array(__('Available Actions'), 'col'),
//				array('', 'col'),			

			);	

			$aTableBody = array();

			if(file_exists($path)){
				$Iterator = new DirectoryIterator($path);
				if(iterator_count($Iterator) <= 0){
	
					$aTableBody = array(
										Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead))))
									);
				} else{
	
					foreach($Iterator as $file){
						if($row = $this->buildTableRow($file, false)) $aTableBody[] = $row;
					}
				
				}
			}
			
			sort($aTableBody);
			
			$table = Widget::Table(
								Widget::TableHead($aTableHead), 
								NULL, 
								Widget::TableBody($aTableBody)
						);

			$this->Form->appendChild($table);

			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
//				array('deploy', false, 'Deploy'),
				array('delete', false, __('Delete'))									
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			$this->Form->appendChild($tableActions);

			$this->appendSubheading(__("Production").' '.__('path').": ".$Deployer->getDeployUrl());
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$prodstatus = new XMLElement('span');

			if(!file_exists($Deployer->getDeployUrl().'/manifest/config.php')){	
				$dpy_status = '-';
			} else {
				require_once($Deployer->getDeployUrl().'/manifest/config.php');
				$dpy_status = ($settings['maintenance_mode']['enabled'] == 'no')?__('Production'):__('Maintence');
			}
			if(!file_exists($Deployer->getDeployUrl().'/META-INF/deploy.php')){
				$dpy_fileName='-';
				$dpy_deployDate='-';
				$dpy_ensambleDate='-';
			} else {
				require_once($Deployer->getDeployUrl().'/META-INF/deploy.php');
			}

			$create_menu = new XMLElement('ul');
			$create_menu->setAttribute('class', 'create-menu');		
			$li = new XMLElement('li');
			if($settings['maintenance_mode']['enabled'] == 'no')$li->appendChild(Widget::Anchor(__('Turn productionin Maintence Mode'), extension_deployer::baseURL() . 'command?cmd=goOffline', __('Turn productionin Maintence Mode'), 'button'));
			else if($settings['maintenance_mode']['enabled'] == 'yes')$li->appendChild(Widget::Anchor(__('Remove Maintence Mode'), extension_deployer::baseURL() . 'command?cmd=goOnline' , __('Remove Maintence Mode'), 'button'));
			$create_menu->appendChild($li);
			$this->Form->appendChild($create_menu);	

			
			$prodstatus->appendChild(new XMLElement('p',__('Status').':',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_status,array('class' => 'status-text')));
			$prodstatus->appendChild(new XMLElement('br'));
				
			$prodstatus->appendChild(new XMLElement('p',__('Deployed Zip').':',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_fileName,array('class' => 'status-text')));
			$prodstatus->appendChild(new XMLElement('br'));

			$prodstatus->appendChild(new XMLElement('p',__('Ensamble Date').':',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_ensambleDate,array('class' => 'status-text')));
			$prodstatus->appendChild(new XMLElement('br'));

			$prodstatus->appendChild(new XMLElement('p',__('Deploy Mode').':',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_deployMode,array('class' => 'status-text')));
			$prodstatus->appendChild(new XMLElement('br'));
			
			$prodstatus->appendChild(new XMLElement('p',__('Deployed Date').':',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_deployDate,array('class' => 'status-text')));
			$prodstatus->appendChild(Widget::Anchor('('.__('show').' '.__('log').')', '/deploy-log.txt', __('Display Log of deploy'), 'status-text'));
			$prodstatus->appendChild(new XMLElement('br'));
			
			
			$div->appendChild($prodstatus);
			$this->Form->appendChild($div);                           

			
		}


		public function buildTableRow(DirectoryIterator $file, $includeParentDirectoryDots=true){
			
			$Deployer =& $this->_Parent->ExtensionManager->create('deployer');

			if(!$file->isDot() && substr($file->getFilename(), 0, 1) == '.' && Administration::instance()->Configuration->get('show-hidden', 'filemanager') != 'yes') return;
			elseif($file->isDot() && !$includeParentDirectoryDots && $file->getFilename() == '..') return;
			elseif($file->getFilename() == '.') return;
			
			$relpath = $file->getPathname();
			
			if(!$file->isDir()){
				$download_uri = $Deployer->baseURL() . 'download/?file=' . urlencode($relpath);
				$deploy_uri = $Deployer->baseURL() . 'deploy/?file=' . urlencode($relpath);
			} else {
				$download_uri = $Deployer->baseURL() . 'properties/?file=' . urlencode($relpath) . '/';
			}
			if(!$file->isDot()){
				$td1 = Widget::TableData(Widget::Anchor($file->getFilename(),$download_uri));
//				$td1 = Widget::TableData($file->getFilename());
	
//				$group = $file->getGroup();
//				$owner = $file->getOwner();
				
				$td2 = Widget::TableData(General::formatFilesize($file->getSize()), NULL);

//				$td3 = Widget::TableData(File::getOctalPermission($file->getPerms()) . ' <span class="inactive">' . File::getReadablePerm($file->getPerms()), NULL, NULL, NULL, array('title' => (isset($owner['name']) ? $owner['name'] : $owner) . ', ' . (isset($group['name']) ? $group['name'] : $group) . '</span>'));
				
				$td3 = Widget::TableData(DateTimeObj::get(__SYM_DATETIME_FORMAT__, $file->getMTime()));
				
				$actions_list = new XMLElement('p');
				if($file->isWritable()) {
					if($file->isDir()){
						$actions_list->appendChild(Widget::Anchor(__('Edit'), $download_uri));
//						$td4 = Widget::TableData(Widget::Anchor(__('Edit'), $download_uri));
//						$td5 = Widget::TableData('-', 'inactive');	
					} else {
	//					$actions_list->appendChild(Widget::Anchor(__('Download'), $download_uri));
	//					$actions_list->appendChild(new XMLElement('br'));
						$actions_list->appendChild(Widget::Anchor(__('Deploy'), $deploy_uri));
					
//						$td4 = Widget::TableData(Widget::Anchor(__('Download'), $download_uri));
//						$td5 = Widget::TableData(Widget::Anchor(__('Deploy'), $deploy_uri));	
					}	
//				} else {
//					$td4 = Widget::TableData('-', 'inactive');	
//					$td5 = Widget::TableData('-', 'inactive');	
				}
				$td4=Widget::TableData($actions_list);
			}
			
			else{
				$td1 = Widget::TableData(Widget::Anchor('&crarr;', self::baseURL() . 'browse' . $relpath . '/'));
				$td2 = Widget::TableData('-', 'inactive');
				$td3 = Widget::TableData('-', 'inactive');
				$td4 = Widget::TableData('-', 'inactive');
				$td5 = Widget::TableData('-', 'inactive');
			}
	
			
		//	$startlocation = DOCROOT . $this->getStartLocation();
			
//			if(!$file->isDot())
			$td4->appendChild(Widget::Input('items['.str_replace($startlocation, '', $file->getPathname()) . ($file->isDir() ? '/' : NULL).']', NULL, 'checkbox'));
			
			return Widget::TableRow(array($td1, $td2 , $td3, $td4
				//, $td5
				));
						
		}

		
	}
	
?>