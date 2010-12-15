<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionDeployerDeploy extends AdministrationPage{		
		function __construct(&$parent){
			parent::__construct($parent);
			
			$Deployer =& $this->_Parent->ExtensionManager->create('deployer');
			
			$file = $_REQUEST['file'];
			
			if(Administration::instance()->Configuration->get('auto-maintence', 'deployer') == 'yes')$oldMaintenceStatus = $Deployer->productionMaintence(true);
			
			$Deployer->deploy($file);

			if(Administration::instance()->Configuration->get('auto-maintence', 'deployer') == 'yes')$Deployer->productionMaintence($oldMaintenceStatus);
			
			redirect(extension_deployer::baseURL() . 'deployer/');
			exit();
		}

	}
	
?>
