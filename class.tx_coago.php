<?php

/**
 * ************************************************************
 *  Copyright notice
 *
 *  (c) Krystian Szymukowicz (http://www.prolabium.com)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 *
 */


class tx_coago {

	public $extKey = 'coago';

	public $debug;
	public $defaultCacheDirectory = 'typo3temp/cached_cobj/';
	public $defaultCacheFileExtension = 'html';
	public static $counter = 1;

	public $TSObjectName;
	public $TSObjectConf;
	public $TSObjectTSKey;
	public $cObj;

	public $cacheHash;
	public $cacheType;
	public $cachePeriod;
	public $refresh;
	public $onLoading;
	public $typeNum;


	function cObjGetSingleExt($name, $conf, $TSkey, &$cObj) {

		switch($name) {

			case "COA_GO":

				$content = '';
				$this->cObj = $cObj;
				$this->confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

					
				if(! $this->confArr['coagoDisable'] ) {

					$this->TSObjectName = $name;
					$this->TSObjectConf = $conf;
					$this->TSObjectTSKey = $TSkey;
					$this->debug = $this->TSObjectConf['cache.']['debug'];
					$this->table = $this->cObj->stdWrap($this->TSObjectConf['cache.']['clearCacheOnTableChange'], $this->TSObjectConf['cache.']['clearCacheOnTableChange.']);
					$this->ident = '';
					if( strlen($this->table) ) {
						$this->ident = '_' . $this->table;
					}
					$rangeUidList = $this->cObj->stdWrap($this->TSObjectConf['cache.']['range.']['uidList'], $this->TSObjectConf['cache.']['range.']['uidList.']);
					
					$process = TRUE;
					if($rangeUidList) {
						$process = t3lib_div::inList($rangeUidList, $GLOBALS['TSFE']->id);
					}

					if($process) {

						$this->cacheHash = $this->getCobjHash();

						$this->cacheType = $this->cObj->stdWrap($this->TSObjectConf['cache.']['type'], $this->TSObjectConf['cache.']['type.']);

						// set default cacheType
						if( ! strlen($this->cacheType) ) {
							$this->cacheType = 'beforeCacheDb';
						}

						$this->cachePeriod = intval($this->cObj->stdWrap($this->TSObjectConf['cache.']['period'], $this->TSObjectConf['cache.']['period.']));

						$this->typeNum = $GLOBALS['TSFE']->tmpl->setup['coago_ajax.']['typeNum'];

						$this->setPathesAndFiles();


						switch($this->cacheType) {

							case 'beforeCache_db':
							case 'beforeCacheDb':
							case '1':

								$content = $this->beforeCacheDb();

								break;


							case 'afterCache_file':
							case 'afterCacheFile':
							case '2':

								$content = $this->afterCacheFile();

								break;


							case 'afterCache_file_ajax':
							case 'afterCacheFileAjax':
							case '3':

								$content = $this->afterCacheFileAjax();

								break;

						}

						self::$counter++;

					} else {
						// if cache.range is out of scope then just return simple COA
						$content = $this->getCoagoContent($conf);
					}
				} else {
					// if EM parameter coago_active is FALSE then just return simple COA
					$content = $this->getCoagoContent($conf);
				}

				return $content;

				break;

		}

	}





	function getCobjHash() {

		// read unique settings try to figure out if the cObj should be regenerated for $GLOBALS['TSFE']->id page
		if( is_array($this->TSObjectConf['cache.']['hash.']['special.']) ){

			$uidList = $this->TSObjectConf['cache.']['hash.']['special.']['unique.']['uidList'];
			$pidList = $this->TSObjectConf['cache.']['hash.']['special.']['unique.']['pidList'];
			if($pidList) {
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows ('uid', 'pages', 'pid IN('. trim($pidList) . ') AND doktype <> 254', $groupBy='', $orderBy='', $limit='');
				if($rows) {
					foreach($rows as $row) {
						$uidList .= ',' . $row['uid'];
					}
				}
			}

			//check if any uid from rootline is equal to any value from uniqness $uidList
			$unique = '';
			foreach($GLOBALS['TSFE']->tmpl->rootLine as $page) {
				if( t3lib_div::inList($uidList, $page['uid']) ) {
					$unique =  '_' . $page['uid'];
					break;
				}
			}
		}

		//get the hash base name
		$hash = $this->cObj->stdWrap($this->TSObjectConf['cache.']['hash'], $this->TSObjectConf['cache.']['hash.']);

		if( !strlen($hash) ){
			if($this->confArr['hashNamesFromTS']) {
				$hash = str_replace('.', '_', $this->TSObjectTSKey);
			} else {
				$hash = md5( serialize($this->TSObjectConf) );
			}
		}

		// check if language shuold be included into hash
		if($this->TSObjectConf['cache.']['hash.']['special.']['lang']) {
			$hash .= '_' . $GLOBALS['TSFE']->config['config']['language'];
		}
		
		//join it with unique
		$hash .= $unique;

		// hook for implementing own hash calculating methods. You can also use stdWrap - postUserfunc for that.
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['hashScript'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['hashScript'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$hash = $_procObj->getCobjHash($this);
			}
		}

		return $hash;

	}




	/*
	 * @return string Content of the cObject
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function beforeCacheDb() {

		$content = $GLOBALS['TSFE']->sys_page->getHash($this->cacheHash, $this->cachePeriod);

		// Not yet cached? So generate and store in cache_hash.
		if ( !strlen($content) ) {

			$content = $this->getCoagoContent($this->TSObjectConf);
			if($this->debug) {
				$content .= $this->getFormattedTimeStamp('beforeCacheDb');
			}

			$GLOBALS['TSFE']->sys_page->storeHash($this->cacheHash, $content, 'COA_GO'. $this->ident);

		}
		return $content;
	}





	/*
	 * @return string EXT_SCRIPT which is put in HTML of generated page and then replaced each time the pages is delivered from the cache. Content of the cObject is written in file.
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function afterCacheFile() {
		// use EXT_SCRIPT to include cObject stored in files
		$substKey = "EXT_SCRIPT." . $GLOBALS['TSFE']->uniqueHash();
		$content .= "<!--" . $substKey . "-->";

		$GLOBALS["TSFE"]->config["EXTincScript"][$substKey] = array(
                     		"file" => $this->absolutePathWithFilename,
		);

		// cObject not yet cached in file or cache period expired? So generate and store in files.
		if( file_exists($this->absolutePathWithFilename) ) {
			$cachedFileExist = TRUE;
			$ageInSeconds = time() - filemtime($this->absolutePathWithFilename);
		}else {
			$cachedFileExist = FALSE;
		}

		if(! $cachedFileExist
		||  ( $this->cachePeriod && ($ageInSeconds > $this->cachePeriod)) ){

			$contentToStore = $this->getCoagoContent($this->TSObjectConf);

			if( $this->cachePeriod ) {
				$contentToStore = $this->getAfterCacheFileExpireChecks() . $contentToStore;
			}

			if($this->debug) {
				$contentToStore .= $this->getFormattedTimeStamp('aferCacheFile - first call' . t3lib_div::getIndpEnv('TYPO3_REFERER'));
			}

			// write data needed to reender this object later on using special PAGE type
			$this->restoreData['cObj'] = $this->cObj;
			$this->restoreData['conf'] = $this->TSObjectConf;
			$this->restoreData['absolutePathWithFilename'] = $this->absolutePathWithFilename;
			$GLOBALS['TSFE']->sys_page->storeHash($this->cacheHash, serialize($this->restoreData), 'COA_GO'. $this->ident);

			$fileStatus = t3lib_div::writeFileToTypo3tempDir($this->absolutePathWithFilename, $contentToStore);
			if ($fileStatus)t3lib_div::devLog('Error writing afterCacheFile: '.$fileStatus, $this->extKey, 3);
			t3lib_div::fixPermissions($this->absolutePathWithFilename);

		}

		return $content;
	}





	/*
	 * @return string Javascript which is put in HTML of generated page and then when delivered to the client browser it gets the content through ajax. Content of the cObject is written in file.
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function afterCacheFileAjax() {

		// ASSIGN SOME VARS
		$siteUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$this->counter = self::$counter;
		$this->cachePeriod = $this->cachePeriod?$this->cachePeriod : '0';
		$this->refresh = intval($this->cObj->stdWrap($this->TSObjectConf['cache.']['refresh'], $this->TSObjectConf['cache.']['refresh.'])) * 1000;
		$this->onLoading = $this->cObj->stdWrap($this->TSObjectConf['cache.']['onLoading'], $this->TSObjectConf['cache.']['onLoading.']);
		if( $this->table ) {
				$this->ident = '_' . $this->table;
		}

		// CREATE FILE, THAT WILL BE FETACHED WITH AJAX

		// cObject not yet cached in file or cache period expired? So generate and store in files.
		if( file_exists($this->absolutePathWithFilename) ) {
			$cachedFileExist = TRUE;
			$ageInSeconds = time() - filemtime($this->absolutePathWithFilename);
		}else {
			$cachedFileExist = FALSE;
		}

		if(! $cachedFileExist
		||  ( $this->cachePeriod && ($ageInSeconds > $this->cachePeriod)) ){

			$contentToStore = $this->getCoagoContent($this->TSObjectConf);
			if($this->debug) {
				$contentToStore .= $this->getFormattedTimeStamp('afterCacheFileAjax - first call');
			}
			$fileStatus = t3lib_div::writeFileToTypo3tempDir($this->absolutePathWithFilename, $contentToStore);
			if ($fileStatus)t3lib_div::devLog('Error writing afterCacheFileAjax: '.$fileStatus, $this->extKey, 3);
			t3lib_div::fixPermissions($this->absolutePathWithFilename);

			// write data needed to reender this object later on using special PAGE type
			$this->restoreData['cObj'] = $this->cObj;
			$this->restoreData['conf'] = $this->TSObjectConf;
			$this->restoreData['absolutePathWithFilename'] = $this->absolutePathWithFilename;
			$GLOBALS['TSFE']->sys_page->storeHash($this->cacheHash, serialize($this->restoreData), 'COA_GO'. $this->ident);

		}



		// GENERATE JAVASCRIPT THAT WILL LOAD CACHED COBJECT

		// hook for implementing own ajax script if you like to make it with some js frameworks as jQuery, MooTools, etc.
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['ajaxScript'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['ajaxScript'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$script = $_procObj->getCoagoAjaxScript($this);
			}
		} else {
			$script = $this->getCoagoAjaxScript();
		}

		// script minification
		$script = t3lib_div::minifyJavaScript($script, $error);
		if ($error) {
			t3lib_div::devLog('Error minimizing script: '.$error, $this->extKey, 3);
		}

		// wrap the script end return
		$content .= "<div id='ncc-{$this->counter}'><script type='text/javascript'>
		{$script}
		</script></div>";

		return $content;
	}




	/*
	 * @param array Configuration
	 * @return string Content generated with COA
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function getCoagoContent($conf) {

		// standard COA
		if ( $this->cObj->checkIf($conf['if.']) )	{
			$contentToStore = $this->cObj->cObjGet($conf);
			if ( $conf['wrap'] ) {
				$contentToStore = $this->cObj->wrap($contentToStore, $conf['wrap']);
			}
			if ( $conf['stdWrap.'] ) {
				$contentToStore = $this->cObj->stdWrap($contentToStore, $conf['stdWrap.']);
			}
		}
		return $contentToStore;
	}




	/*
	 * @param string Text used to distinguish between debug data
	 * @return string Unique text used to debug purposes
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function getFormattedTimeStamp($marker) {

		if( $this->TSObjectConf['cache.']['debug.']['asHtmlComments'] || $this->restoreData['conf']['cache.']['debug.']['asHtmlComments'] ){
			$content = '<!-- cObject hash: ' . ($this->cacheHash) . ' '  . strftime("%Y-%m-%d %H:%M:%S") .' '. ($marker ? '['. $marker .']':'') .' -->';
		} else {
			$content = '<span style="border:1px dashed #aaa; background: yellow; padding:0 5px; margin: 0 5px">cObject hash: ' . ($this->cacheHash) . ' '  . strftime("%Y-%m-%d %H:%M:%S") .' '. ($marker ? '['. $marker .']':'') .'</span>';
		}
		return $content;
	}




	/*
	 * Sets patches used in whole class
	 *
	 * @return void
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function setPathesAndFiles() {

		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		if( !$confArr['cacheDirectory'] ) {
			$confArr['cacheDirectory'] = $this->defaultCacheDirectory;
		}

		// set default cacheFileExtension
		if( $confArr['cacheFileExtension'] ) {
			$this->cacheFileExtension = $confArr['cacheFileExtension'];

		} else {
			$this->cacheFileExtension = $this->defaultCacheFileExtension;
		}

		if($GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension']) {
			$this->cacheFileExtension = $this->cObj->stdWrap($GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension'], $GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension.']);
		}

		$this->relativePath =  $confArr['cacheDirectory'];
		$this->absolutePath = PATH_site . $this->relativePath;
		$this->filename = $this->cacheHash . $this->ident . '.' . $this->cacheFileExtension;
		$this->absolutePathWithFilename = $this->absolutePath . $this->filename;
	}





	/*
	 * Regenerates cObject and put the content to file under _GPmerged('cacheHash') name. This is invoked by
	 * requesting special URL like: http://www.example.com/index.php?id=xxx&type=yyy&cacheHash=zzzz
	 * Type of page is set in static/contants.txt
	 *
	 * @param array Configuration array
	 * @return void
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function regenerateContent($conf) {

		$this->cacheHash = t3lib_div::_GP('cacheHash');
		if(!preg_match('/^[a-zA-Z0-9_\-]{1,250}$/', $this->cacheHash)) {
			t3lib_div::devLog('Bad hash passed in GET vars when regenerating content.', 'coago', 3);
			die('Bad hash passed in GET vars when regenerating content.');
		}
		$this->restoreData = unserialize($GLOBALS['TSFE']->sys_page->getHash($this->cacheHash));

		// this is for situation when "cache_hash" table has been cleared and there is no info to regenarate cObjects fetched by ajax, so we have to fetch the whole page
		if(!$this->restoreData){
			t3lib_div::getURL(t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id='. $GLOBALS['TSFE']->id .'&no_cache=1');
			$this->restoreData = unserialize($GLOBALS['TSFE']->sys_page->getHash($this->cacheHash));
		}

		$this->cacheType = $this->cObj->stdWrap($this->restoreData['conf']['cache.']['type'], $this->restoreData['conf']['cache.']['type.']);
		$this->cachePeriod = intval($this->cObj->stdWrap($this->restoreData['conf']['cache.']['period'], $this->restoreData['conf']['cache.']['period.']));
		$this->absolutePathWithFilename = $this->restoreData['absolutePathWithFilename'];
		$this->typeNum = $GLOBALS['TSFE']->tmpl->setup['coago_ajax.']['typeNum'];

		$GLOBALS['TSFE']->cObj = $this->restoreData['cObj'];

		$contentToStore = $this->getCoagoContent($this->restoreData['conf']);


		if($this->cachePeriod && ($this->cacheType == 'afterCacheFile' || $this->cacheType == 'afterCache_file' || $this->cacheType == 2) ) {
			$contentToStore = $this->getAfterCacheFileExpireChecks() . $contentToStore;
		}

		if($this->restoreData['conf']['cache.']['debug']) {
			$contentToStore .= $this->getFormattedTimeStamp( $this->cacheType . ' - regenerated' );
		}

		$fileStatus = t3lib_div::writeFileToTypo3tempDir($this->absolutePathWithFilename, $contentToStore);
		if ($fileStatus)t3lib_div::devLog('Error writing afterCacheFileAjax: '.$fileStatus, $this->extKey, 3);
	}




	/*
	 * Returns php code needed to check if cObject in method "afterCacheFile" is expired
	 *
	 * @return string php code
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function getAfterCacheFileExpireChecks() {
		$cacheChecks = '<?php
	                     			$ageInSeconds = time() - filemtime(\''.$this->absolutePathWithFilename.'\');
	                     			if( ($ageInSeconds > '.$this->cachePeriod.') && '.$this->cachePeriod.' ){
	                     				t3lib_div::getURL(\''. t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id=' . $GLOBALS['TSFE']->id . '&type=' . $this->typeNum . '&cacheHash=' . $this->cacheHash . '\');' .
	                     			"\n" . '} ?>'. "\n\n";
		return $cacheChecks;
	}




	/*
	 * Returns javascript code that fetch and regenerate the content objects
	 *
	 * @return string javascript code
	 * @author Krystian Szymukowicz <typo3@prolabium.com>
	 */
	function getCoagoAjaxScript() {

		$counter = $this->counter;

		$script = "
	
function coa_go_{$counter}() {	
var http_req_{$counter} = false;
if( navigator.appName === 'Microsoft Internet Explorer' ) {
	http_req_{$counter} = new ActiveXObject('Microsoft.XMLHTTP');
 } else {
	http_req_{$counter} = new XMLHttpRequest();
 }

 http_req_{$counter}.open('GET', '{$siteUrl}{$this->relativePath}{$this->filename}',true);
 http_req_{$counter}.setRequestHeader('Content-type', 'text/html');
 http_req_{$counter}.setRequestHeader('If-Modified-Since', 'Thu, 01 Jan 1970 00:00:00 GMT' );	 			
 http_req_{$counter}.send(null);

 ";

		if($this->onLoading) {
			$script .= "document.getElementById('ncc-{$counter}').innerHTML = {$this->onLoading};";
		}

		$script .= "
    http_req_{$counter}.onreadystatechange=function() {
	if( http_req_{$counter}.readyState === 4 ) {

	   ageInSeconds = (new Date() - Date.parse(http_req_{$counter}.getResponseHeader('Last-Modified'))) / 1000;

	   if( (http_req_{$counter}.status === 200) && ( (ageInSeconds < {$this->cachePeriod}) || ('{$this->cachePeriod}' === '0') ) ) {		  	
	   		document.getElementById('ncc-{$counter}').innerHTML = http_req_{$counter}.responseText;
	   } else {

		  var http_req_{$counter}_r1 = false;
		  if(navigator.appName === 'Microsoft Internet Explorer') {
			 http_req_{$counter}_r1 = new ActiveXObject('Microsoft.XMLHTTP');
		  } else {
			 http_req_{$counter}_r1 = new XMLHttpRequest();
		  }

		http_req_{$counter}_r1.open('GET', '{$siteUrl}index.php?id={$GLOBALS['TSFE']->id}&type={$this->typeNum}&cacheHash={$this->cacheHash}');
		http_req_{$counter}_r1.setRequestHeader('Content-type', 'text/html');
		http_req_{$counter}_r1.setRequestHeader('If-Modified-Since', 'Thu, 01 Jan 1970 00:00:00 GMT' );		 			
		http_req_{$counter}_r1.send(null);

		http_req_{$counter}_r1.onreadystatechange=function() {
			 if( http_req_{$counter}_r1.readyState === 4 ) {

				var http_req_{$counter}_r2 = false;
				if(navigator.appName === 'Microsoft Internet Explorer') {
				   http_req_{$counter}_r2 = new ActiveXObject('Microsoft.XMLHTTP');
				} else {
				   http_req_{$counter}_r2 = new XMLHttpRequest();
				}

			 http_req_{$counter}_r2.open('GET', '{$siteUrl}{$this->relativePath}{$this->filename}',true);
			 http_req_{$counter}_r2.setRequestHeader('Content-type', 'text/html');
			 http_req_{$counter}_r2.setRequestHeader('If-Modified-Since', 'Thu, 01 Jan 1970 00:00:00 GMT' );		 			
			 http_req_{$counter}_r2.send(null);";

		if($this->onLoading) {
			$script .= "		document.getElementById('ncc-{$counter}').innerHTML = {$this->onLoading};";
		}

		$script .= "
			http_req_{$counter}_r2.onreadystatechange=function() {

				if( http_req_{$counter}_r2.readyState === 4 ) {
					  if( http_req_{$counter}_r2.status === 200 ) {
						 document.getElementById('ncc-{$counter}').innerHTML = http_req_{$counter}_r2.responseText;
					  }
				   }
				};
			 }
		  };
	   }
	}
  };
  ";
		//condition for refreshing the content at apge
		if($this->refresh){
			$script .= " setTimeout('coa_go_{$counter}()', {$this->refresh});";
		}
		//cloasing bracet for all javascript
		$script .= "} coa_go_{$counter}();";

		return $script;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/class.tx_coago.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/class.tx_coago.php']);
}

?>