<?php

require_once(APPROOT.'collectors/SQLXCollector.class.inc.php');

require_once(APPROOT.'collectors/SQLXBrandCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXNetworkDeviceCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXNetworkDeviceTypeCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXFarmCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXHypervisorCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXInterfaceCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXIPUsageCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXIPv4AddressCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXlnkIPInterfaceToIPAddressCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXLogicalInterfaceCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXModelCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXOSFamilyCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXOSVersionCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXOSLicenceCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXServerCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXPhysicalInterfaceCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXVirtualMachineTypeCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXVirtualMachineCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXVirtualApplicationCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXlnkApplicationSolutionToFunctionalCICollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXHardDiskTypeCollector.class.inc.php');
require_once(APPROOT.'collectors/SQLXHardDiskCollector.class.inc.php');

// Register the collectors (one collector class per data synchro task to run)
// and tell the orchestrator in which order to run them

$sqlx_config = SQLXCollectorConfig::getConfig();

$sqlx_rank = 0;
foreach (SQLXCollectorConfig::getCollectors() as $name) {
	$sqlx_rank++;
	Orchestrator::AddCollector($sqlx_rank, 'SQLX' . $name . 'Collector');
}
