<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionDeployerDeploy extends AdministrationPage{		
		function __construct(&$parent){
			parent::__construct($parent);
			
			$Deployer =& $this->_Parent->ExtensionManager->create('deployer');
			
			$file = $_REQUEST['file'];
			
			$Deployer->deploy($file);
			
			exit();
		}

	}
	
?>
