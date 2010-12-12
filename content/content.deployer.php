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
				$li->appendChild(Widget::Anchor('Make Snapshot', extension_deployer::baseURL() . 'new/snapshot/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'New Snapshot', 'button create'));
				$create_menu->appendChild($li);
			
//				$li = new XMLElement('li');
//				$li->appendChild(Widget::Anchor('Snapshot and Deploy', extension_deployer::baseURL() . 'new/file/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'New File', 'button create'));
//				$create_menu->appendChild($li);
			
//				$li = new XMLElement('li');
//				$li->appendChild(Widget::Anchor('Upload File', extension_filemanager::baseURL() . 'new/upload/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'Upload File', 'button create'));
//				$create_menu->appendChild($li);
			} else if(file_exists($path)) {
				$create_menu = new XMLElement('p','This directory is not writable');
				$create_menu->setAttribute('class','create-menu');
			} else {
				$create_menu = new XMLElement('p','This directory not exists');
				$create_menu->setAttribute('class','create-menu');
			}

			$this->setPageType('table');
			$this->appendSubheading("Storage path: ".$Deployer->getStorageUrl());
			$this->Form->appendChild($create_menu);

			$aTableHead = array(

				array('Name', 'col'),
				array('Size', 'col'),
				array('Date', 'col'),
				array('Available Actions', 'col'),
				array('', 'col'),			

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
				array(NULL, false, 'With Selected...'),
//				array('deploy', false, 'Deploy'),
				array('delete', false, 'Delete')									
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			$this->Form->appendChild($tableActions);

			$this->appendSubheading("Production path: ".$Deployer->getDeployUrl());
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$prodstatus = new XMLElement('span');

			if(!file_exists($Deployer->getDeployUrl().'/manifest/config.php')){	
				$dpy_status = '-';
			} else {
				require_once($Deployer->getDeployUrl().'/manifest/config.php');
				$dpy_status = ($settings['maintenance_mode']['enabled'] == 'no')?'Production':'Maintence';
			}
			if(!file_exists($Deployer->getDeployUrl().'/META-INF/deploy.php')){
				$dpy_fileName='-';
				$dpy_deployDate='-';
			} else {
				require_once($Deployer->getDeployUrl().'/META-INF/deploy.php');
			}
			
			$prodstatus->appendChild(new XMLElement('p','Status:',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_status,array('class' => 'status-text')));

			$prodstatus->appendChild(new XMLElement('p','Deployed Zip:',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_fileName,array('class' => 'status-text')));

			$prodstatus->appendChild(new XMLElement('p','Deployed Date:',array('class' => 'status-label')));
			$prodstatus->appendChild(new XMLElement('p',$dpy_deployDate,array('class' => 'status-text')));

			$prodstatus->appendChild(Widget::Anchor('(show log)', '/deploy-log.txt', 'Display Log of deploy', 'status-text'));
			
			$div->appendChild($prodstatus);
			$this->Form->appendChild($div);                           
/*
			$create_menu = new XMLElement('ul');
			$create_menu->setAttribute('class', 'create-menu');		
			$li = new XMLElement('li');
			$li->appendChild(Widget::Anchor('Make Snapshot', extension_deployer::baseURL() . 'new/snapshot/' . (is_array($this->_context) && !empty($this->_context) ? implode('/', $this->_context) . '/' : NULL), 'New Snapshot', 'button create'));
			$create_menu->appendChild($li);
			$this->Form->appendChild($create_menu);	
*/			
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
//				$td1 = Widget::TableData(Widget::Anchor($file->getFilename(), $Deployer->baseURL() . ($file->isDir() ? 'browse' . $relpath . '/' : 'properties/?file=' . urlencode($relpath)), NULL, 'file-type ' . ($file->isDir() ? 'folder' : File::fileType($file->getFilename()))));
				$td1 = Widget::TableData($file->getFilename());
	
//				$group = $file->getGroup();
//				$owner = $file->getOwner();
				
				$td2 = Widget::TableData(General::formatFilesize($file->getSize()), NULL);

//				$td3 = Widget::TableData(File::getOctalPermission($file->getPerms()) . ' <span class="inactive">' . File::getReadablePerm($file->getPerms()), NULL, NULL, NULL, array('title' => (isset($owner['name']) ? $owner['name'] : $owner) . ', ' . (isset($group['name']) ? $group['name'] : $group) . '</span>'));
				
				$td3 = Widget::TableData(DateTimeObj::get(__SYM_DATETIME_FORMAT__, $file->getMTime()));
				
				if($file->isWritable()) {
					if($file->isDir()){
						$td4 = Widget::TableData(Widget::Anchor('Edit', $download_uri));
						$td5 = Widget::TableData('-', 'inactive');	
					} else {
						$td4 = Widget::TableData(Widget::Anchor('Download', $download_uri));
						$td5 = Widget::TableData(Widget::Anchor('Deploy', $deploy_uri));	
					}	
				}	
				else {
					$td4 = Widget::TableData('-', 'inactive');	
					$td5 = Widget::TableData('-', 'inactive');	
				}
			}
			
			else{
				$td1 = Widget::TableData(Widget::Anchor('&crarr;', self::baseURL() . 'browse' . $relpath . '/'));
				$td2 = Widget::TableData('-', 'inactive');
				$td3 = Widget::TableData('-', 'inactive');
				$td4 = Widget::TableData('-', 'inactive');
				$td5 = Widget::TableData('-', 'inactive');
			}
	
			
		//	$startlocation = DOCROOT . $this->getStartLocation();
			
			if(!$file->isDot()) $td5->appendChild(Widget::Input('items['.str_replace($startlocation, '', $file->getPathname()) . ($file->isDir() ? '/' : NULL).']', NULL, 'checkbox'));
			
			return Widget::TableRow(array($td1, $td2 , $td3, $td4, $td5));
						
		}

		
	}
	
?>