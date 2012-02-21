<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE == 'FE') {

	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][] = 
	array('COA_GO', 'EXT:coago/class.tx_coago.php:&tx_coago');	

	// example of hook used to generate own javascript method to replace content
	//$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['ajaxScript'][] = 'EXT:coago/misc/class.tx_coago_mod.php:tx_coago_mod';	
}

if (TYPO3_MODE == 'BE') {

	// hook used to delete files in cacheDirectory if clearCache button 'all', 'pages' will be hit in the backend
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = 'EXT:coago/class.tx_coagohooks.php:tx_coagohooks->clearCachePostProc';

	// hook used to delete files and clear database cache in case of different table changes
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:coago/class.tx_coagohooks.php:tx_coagohooks';
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'EXT:coago/class.tx_coagohooks.php:tx_coagohooks';

}

?>