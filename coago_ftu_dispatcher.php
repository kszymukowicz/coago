<?php

$_get = t3lib_div::_GET();

$cachePeriod = intval($_get['cachePeriod']);
$typeNum = intval($_get['typeNum']);
$cacheHash = $_get['cacheHash'];

if(!preg_match('/^[a-zA-Z0-9_\-]{1,250}$/', $cacheHash)) {
	t3lib_div::devLog('Bad hash passed in GET vars when regenerating content.', 'coago', 3);
	die('Bad hash passed in GET vars when regenerating content.');
}


$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['coago']);

if( !$confArr['cacheDirectory'] ) {
	$confArr['cacheDirectory'] = 'typo3temp/cached_cobj/';
}

if( !$confArr['cacheFileExtension'] ) {
	$confArr['cacheFileExtension'] = 'html';
}


$feUser = tslib_eidtools::initFeUser();

// get content only if user is authenticted
if($feUser->user["uid"]) {
	
	$absolutePathWithFilenameToUserCObject = PATH_site . $confArr['cacheDirectory'] . $cacheHash . '/' . $feUser->user["uid"] . '.' . $confArr['cacheFileExtension'];
	
	// check if the created path is inside TYPO3 typo3temp folder and if it is a valid path
	if( t3lib_div::validPathStr($absolutePathWithFilenameToUserCObject) && t3lib_div::isFirstPartOfStr(t3lib_div::fixWindowsFilePath($absolutePathWithFilenameToUserCObject), PATH_site . 'typo3temp' )) {

		if( file_exists($absolutePathWithFilenameToUserCObject) ) {
			$cachedFileExist = TRUE;
			$ageInSeconds = time() - filemtime($absolutePathWithFilenameToUserCObject);
		}else {
			$cachedFileExist = FALSE;
		}

		if(! $cachedFileExist || ( $cachePeriod && ($ageInSeconds > $cachePeriod)) ){
			// it the file do not exist or is expired then regenerate the cObject and save in file
			t3lib_div::getURL( t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id='. $GLOBALS['TSFE']->id . '&type=' . $typeNum .'&cacheHash=' . $cacheHash . '&ftu=' . $feUser->user['ses_id'], 1, Array('User-Agent: '. t3lib_div::getIndpEnv('HTTP_USER_AGENT')) );
		}

		// right now there must be file content with user cObject so return it to ajax call
		$content = t3lib_div::getURL($absolutePathWithFilenameToUserCObject);
	}
	
} else {
	$content = "Invalid user id";
}

echo $content;
?>