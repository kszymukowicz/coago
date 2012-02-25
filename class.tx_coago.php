<?php

/**
 * ************************************************************
 *  Copyright notice
 *
 *  (c) Krystian Szymukowicz (http://www.cms-partner.pl/)
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
	/**
	 * @var array $confArray extension settings set in Extension Manager
	 */
	public $confArr;

	public $cacheHash;
	public $cacheType;
	public $cachePeriod;
	public $refresh;
	public $onLoading;
	public $typeNum;


	/**
	 * @param $name
	 * @param $conf
	 * @param $TSkey
	 * @param $cObj
	 * @return string
	 */
	public function cObjGetSingleExt($name, $conf, $TSkey, &$cObj) {

		switch ($name) {

			case "COA_GO":
				$content = '';
				$this->cObj = $cObj;
				$this->confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

				if (!$this->confArr['coagoDisable']) {

					$this->TSObjectName = $name;
					$this->TSObjectConf = $conf;
					$this->TSObjectTSKey = $TSkey;
					$this->debug = $this->TSObjectConf['cache.']['debug'];
					$this->table = $this->cObj->stdWrap($this->TSObjectConf['cache.']['clearCacheOnTableChange'], $this->TSObjectConf['cache.']['clearCacheOnTableChange.']);
					$this->ident = '';
					if (strlen($this->table)) {
						$this->ident = '_' . $this->table;
					}
					$rangeUidList = $this->cObj->stdWrap($this->TSObjectConf['cache.']['range.']['uidList'], $this->TSObjectConf['cache.']['range.']['uidList.']);

					$process = TRUE;
					if ($rangeUidList) {
						$process = t3lib_div::inList($rangeUidList, $GLOBALS['TSFE']->id);
					}

					if ($process) {

						$this->cacheHash = $this->getCobjHash();
						$this->cacheType = $this->cObj->stdWrap($this->TSObjectConf['cache.']['type'], $this->TSObjectConf['cache.']['type.']);

						// set default cacheType
						if (!strlen($this->cacheType)) {
							$this->cacheType = 'beforeCacheDb';
						}

						$this->cachePeriod = intval($this->cObj->stdWrap($this->TSObjectConf['cache.']['period'], $this->TSObjectConf['cache.']['period.']));
						$this->typeNum = $GLOBALS['TSFE']->tmpl->setup['coago_ajax.']['typeNum'];
						$this->setPathsAndFiles();

						switch ($this->cacheType) {
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


	/**
	 * Calculate hash identifier for current COA_GO object
	 * @return string
	 */
	protected function getCobjHash() {

		// read unique settings try to figure out if the cObj should be regenerated for $GLOBALS['TSFE']->id page
		if (is_array($this->TSObjectConf['cache.']['hash.']['special.'])) {

			$uidList = $this->TSObjectConf['cache.']['hash.']['special.']['unique.']['uidList'];
			$pidList = $this->TSObjectConf['cache.']['hash.']['special.']['unique.']['pidList'];
			if ($pidList) {
				$addWhere = $this->TSObjectConf['cache.']['hash.']['special.']['unique.']['pidList.']['addWhere'];
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows ('uid', 'pages', 'pid IN('. trim($pidList) . ') AND doktype <> 254 AND hidden=0 AND deleted=0 ' . $addWhere, $groupBy='', $orderBy='', $limit='');
				if($rows) {
					foreach($rows as $row) {
						$uidList .= ',' . $row['uid'];
					}
				}
			}

			//check if any uid from rootline is equal to any value from uniqness $uidList
			$unique = '';
			foreach ($GLOBALS['TSFE']->tmpl->rootLine as $page) {
				if (t3lib_div::inList($uidList, $page['uid'])) {
					$unique =  '_' . $page['uid'];
					break;
				}
			}
		}

		//get the hash base name
		$hash = $this->cObj->stdWrap($this->TSObjectConf['cache.']['hash'], $this->TSObjectConf['cache.']['hash.']);

		if (!strlen($hash)) {
			if ($this->confArr['hashNamesFromTS']) {
				$hash = str_replace('.', '_', $this->TSObjectTSKey);
			} else {
				$hash = md5( serialize($this->TSObjectConf) );
			}
		}

		// check if language should be included into hash
		if ($this->TSObjectConf['cache.']['hash.']['special.']['lang']) {
			$hash .= '_' . $GLOBALS['TSFE']->config['config']['language'];
		}

		// check if feuser hash should be included into hash
		if ($this->TSObjectConf['cache.']['hash.']['special.']['feuser']) {
			$hash .= '_ftu'; //-' . $GLOBALS["TSFE"]->fe_user->user["ses_id"];
		}

		//join it with unique
		$hash .= $unique;

		// hook for implementing own hash calculating methods. You can also use stdWrap - postUserfunc for that.
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['hashScript'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['hashScript'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$hash = $_procObj->getCobjHash($this);
			}
		}

		return $hash;
	}




	/**
	 * @return string Content of the cObject
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function beforeCacheDb() {

		$content = t3lib_pageSelect::getHash($this->cacheHash, $this->cachePeriod);

		// Not yet cached? So generate and store in cache_hash.
		if (!strlen($content)) {

			$content = $this->getCoagoContent($this->TSObjectConf);
			if (!strlen($content)) {
				print_r($this->TSObjectConf);
				die;
			}

			if ($this->debug) {
				$content .= $this->getFormattedTimeStamp('beforeCacheDb');
			}

			t3lib_pageSelect::storeHash($this->cacheHash, $content, 'COA_GO'. $this->ident);
		}
		return $content;
	}





	/**
	 * @return string EXT_SCRIPT which is put in HTML of generated page and then replaced each time the pages is delivered from the cache. Content of the cObject is written in file.
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function afterCacheFile() {
		// use EXT_SCRIPT to include cObject stored in files
		$substKey = "EXT_SCRIPT." . $GLOBALS['TSFE']->uniqueHash();
		$content .= "<!--" . $substKey . "-->";

		$GLOBALS["TSFE"]->config["EXTincScript"][$substKey] = array(
						"file" => $this->absolutePathWithFilename,
		);

		// cObject not yet cached in file or cache period expired? So generate and store in files.
		if (file_exists($this->absolutePathWithFilename)) {
			$cachedFileExist = TRUE;
			$ageInSeconds = time() - filemtime($this->absolutePathWithFilename);
		} else {
			$cachedFileExist = FALSE;
		}

		if (!$cachedFileExist || ($this->cachePeriod && ($ageInSeconds > $this->cachePeriod))) {

			$contentToStore = $this->getCoagoContent($this->TSObjectConf);

			if ($this->cachePeriod) {
				$contentToStore = $this->getAfterCacheFileExpireChecks() . $contentToStore;
			}

			if ($this->debug) {
				$contentToStore .= $this->getFormattedTimeStamp('aferCacheFile - first call' . t3lib_div::getIndpEnv('TYPO3_REFERER'));
			}

			// write data needed to reender this object later on using special PAGE type
			$this->restoreData['cObj'] = $this->cObj;
			$this->restoreData['conf'] = $this->TSObjectConf;
			$this->restoreData['absolutePathWithFilename'] = $this->absolutePathWithFilename;
			t3lib_pageSelect::storeHash($this->cacheHash, serialize($this->restoreData), 'COA_GO'. $this->ident);

			$fileStatus = t3lib_div::writeFileToTypo3tempDir($this->absolutePathWithFilename, $contentToStore);
			if ($fileStatus) t3lib_div::devLog('Error writing afterCacheFile: '.$fileStatus, $this->extKey, 3);
			t3lib_div::fixPermissions($this->absolutePathWithFilename);
		}

		return $content;
	}





	/**
	 * @return string Javascript which is put in HTML of generated page and then when delivered to the client browser it gets the content through ajax. Content of the cObject is written in file.
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function afterCacheFileAjax() {

		// store the date in cache_hash that will be used later by ajax call to rebuild cObject
		$this->restoreData['cObj'] = $this->cObj;
		$this->restoreData['conf'] = $this->TSObjectConf;
		$this->restoreData['absolutePath'] = $this->absolutePath;
		$this->restoreData['filename'] = $this->filename;
		$this->restoreData['cacheFileExtension'] =  $this->cacheFileExtension;
		if ($this->table) {
			$this->ident = '_' . $this->table;
		}
		t3lib_pageSelect::storeHash($this->cacheHash, serialize($this->restoreData), 'COA_GO'. $this->ident);


		// prepare the data needed to create javascript that will fetch the cObject
		$this->counter = self::$counter;
		$this->cachePeriod = $this->cachePeriod?$this->cachePeriod : '0';
		$this->refresh = intval($this->cObj->stdWrap($this->TSObjectConf['cache.']['refresh'], $this->TSObjectConf['cache.']['refresh.'])) * 1000;
		$this->onLoading = $this->cObj->stdWrap($this->TSObjectConf['cache.']['onLoading'], $this->TSObjectConf['cache.']['onLoading.']);


		// hook for implementing own ajax script if you like to make it with some js frameworks as jQuery, MooTools, etc.
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['ajaxScript'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['coago']['ajaxScript'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$script = $_procObj->getCoagoAjaxScript($this);
			}
		} else {
			$script = $this->getCoagoAjaxScript();
		}

		// wrap the script end return
		$content = "<div id='ncc-{$this->counter}'> </div><script type='text/javascript'>
					{$script}
					</script>";

		return $content;
	}




	/**
	 * @param array Configuration
	 * @return string Content generated with COA
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function getCoagoContent($conf) {

		// standard COA
		if ($this->cObj->checkIf($conf['if.'])) {
			$contentToStore = $this->cObj->cObjGet($conf);
			if ($conf['wrap']) {
				$contentToStore = $this->cObj->wrap($contentToStore, $conf['wrap']);
			}
			if ($conf['stdWrap.']) {
				$contentToStore = $this->cObj->stdWrap($contentToStore, $conf['stdWrap.']);
			}
		}
		return $contentToStore;
	}




	/**
	 * @param string Text used to distinguish between debug data
	 * @return string Unique text used to debug purposes
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function getFormattedTimeStamp($marker) {

		if ($this->TSObjectConf['cache.']['debug.']['asHtmlComments'] || $this->restoreData['conf']['cache.']['debug.']['asHtmlComments'] ) {
			$content = '<!-- cObject hash: ' . ($this->cacheHash) . ' '  . strftime("%Y-%m-%d %H:%M:%S") .' '. ($marker ? '['. $marker .']':'') .' -->';
		} else {
			$content = '<span style="border:1px dashed #aaa; background: yellow; padding:0 5px; margin: 0 5px">cObject hash: ' . ($this->cacheHash) . ' '  . strftime("%Y-%m-%d %H:%M:%S") .' '. ($marker ? '['. $marker .']':'') .'</span>';
		}
		return $content;
	}




	/**
	 * Sets paths used in whole class
	 *
	 * @return void
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function setPathsAndFiles() {

		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

		if (!$confArr['cacheDirectory']) {
			$confArr['cacheDirectory'] = $this->defaultCacheDirectory;
		}

		// set default cacheFileExtension
		if ($confArr['cacheFileExtension']) {
			$this->cacheFileExtension = $confArr['cacheFileExtension'];

		} else {
			$this->cacheFileExtension = $this->defaultCacheFileExtension;
		}

		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension']) {
			$this->cacheFileExtension = $this->cObj->stdWrap($GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension'], $GLOBALS['TSFE']->tmpl->setup['plugin.']['coago.']['fileExtension.']);
		}

		$this->relativePath =  $confArr['cacheDirectory'];
		$this->absolutePath = PATH_site . $this->relativePath;
		$this->filename = $this->cacheHash . $this->ident;
		$this->filenameWithExtension =  $this->filename . '.' . $this->cacheFileExtension;
		$this->absolutePathWithFilename = $this->absolutePath . $this->filenameWithExtension;
	}


	/**
	 * Regenerates cObject and put the content to file under _GPmerged('cacheHash') name. This is invoked by
	 * requesting special URL like: http://www.example.com/index.php?id=xxx&type=yyy&cacheHash=zzzz
	 * Type of page is set in static/contants.txt
	 *
	 * @param array $conf Configuration array
	 * @return void
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	public function regenerateContent($conf) {

		$this->cacheHash = t3lib_div::_GP('cacheHash');
		if (!preg_match('/^[a-zA-Z0-9_\-]{1,250}$/', $this->cacheHash)) {
			t3lib_div::devLog('Bad hash passed in GET vars when regenerating content.', 'coago', 3);
			die('Bad hash passed in GET vars when regenerating content.');
		}
		// from "cache_hash" get the data needed to rebuild cObject
		$this->restoreData = unserialize(t3lib_pageSelect::getHash($this->cacheHash));

		// this is for rare situation when "cache_hash" table has been cleared and there is no info to regenarate cObjects fetched by ajax, so we have to fetch the whole page
		if (empty($this->restoreData)) {
			if ($GLOBALS['TSFE']->fe_user->user['uid']) {
				t3lib_div::getURL(t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id='. $GLOBALS['TSFE']->id .'&ftu=' . $GLOBALS['TSFE']->fe_user->user['ses_id'], 1, Array('User-Agent: '. t3lib_div::getIndpEnv('HTTP_USER_AGENT')) );
			} else {
				t3lib_div::getURL(t3lib_div::getIndpEnv('TYPO3_SITE_URL') . 'index.php?id='. $GLOBALS['TSFE']->id .'&no_cache=1');
			}
			$this->restoreData = unserialize(t3lib_pageSelect::getHash($this->cacheHash));
		}

		$this->cacheType = $this->cObj->stdWrap($this->restoreData['conf']['cache.']['type'], $this->restoreData['conf']['cache.']['type.']);
		$this->cachePeriod = intval($this->cObj->stdWrap($this->restoreData['conf']['cache.']['period'], $this->restoreData['conf']['cache.']['period.']));		//
		$this->absolutePath = $this->restoreData['absolutePath'];
		$this->filename = $this->restoreData['filename'];
		$this->cacheFileExtension = $this->restoreData['cacheFileExtension'];

		$this->typeNum = $GLOBALS['TSFE']->tmpl->setup['coago_ajax.']['typeNum'];

		$GLOBALS['TSFE']->cObj = $this->restoreData['cObj'];

		$contentToStore = $this->getCoagoContent($this->restoreData['conf']);

		//		if($this->cachePeriod && ($this->cacheType == 'afterCacheFile' || $this->cacheType == 'afterCache_file' || $this->cacheType == 2) ) {
		//			$contentToStore = $this->getAfterCacheFileExpireChecks() . $contentToStore;
		//		}

		if ($this->restoreData['conf']['cache.']['debug']) {
			$contentToStore .= $this->getFormattedTimeStamp($this->cacheType . ' - regenerated');
		}

		$absolutePathWithFilename = $this->absolutePath . $this->filename;

		if ($this->cacheFileExtension) {
			$absolutePathWithFilename .= '.' . $this->cacheFileExtension;
		}

		if ($this->restoreData['conf']['cache.']['hash.']['special.']['feuser'] && $GLOBALS['TSFE']->fe_user->user['uid']) {
			// user dependent cObject are put into folder with hash name
			$absolutePathWithFilename =  $this->absolutePath . $this->filename . '/' . $GLOBALS["TSFE"]->fe_user->user["uid"];
				
			if ($this->cacheFileExtension) {
				$absolutePathWithFilename .= '.' . $this->cacheFileExtension;
			}
				
		}
		if (t3lib_div::validPathStr($absolutePathWithFilename) && t3lib_div::isFirstPartOfStr(t3lib_div::fixWindowsFilePath($absolutePathWithFilename), PATH_site . 'typo3temp' )) {
			$fileStatus = t3lib_div::writeFileToTypo3tempDir($absolutePathWithFilename, $contentToStore);
			if ($fileStatus) {
				t3lib_div::devLog('Error writing afterCacheFileAjax: '.$fileStatus, $this->extKey, 3);
			}
		}
		// return the content directly to browser
		return $contentToStore;
	}




	/**
	 * Returns php code needed to check if cObject in method "afterCacheFile" is expired
	 *
	 * @return string php code
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	protected function getAfterCacheFileExpireChecks() {
		$cacheChecks = '<?php
						$ageInSeconds = time() - filemtime(\''.$this->absolutePathWithFilename.'\');
						if ( ($ageInSeconds > '.$this->cachePeriod.') && '.$this->cachePeriod.' ) {
							t3lib_div::getURL(\''. t3lib_div::getIndpEnv('TYPO3_SITE_URL') .
								'index.php?id=' . $GLOBALS['TSFE']->id .
								'&type=' . $this->typeNum .
								'&cacheHash=' . $this->cacheHash .
							"');" .
						"\n" . '} ?>'. "\n\n";
		return $cacheChecks;
	}




	/**
	 * Returns javascript code that fetch and regenerate the content objects
	 *
	 * @return string javascript code
	 * @author Krystian Szymukowicz <ks@cms-partner.pl>
	 */
	public function getCoagoAjaxScript() {

		$siteUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL');

		if ($this->confArr['includeCoagoJavascript']) {
			$GLOBALS['TSFE']->additionalHeaderData['coago'] = '<script src="'. $siteUrl .'typo3conf/ext/coago/res/js/coago.js" type="text/javascript"> </script>';
		}

		if (!strlen($this->onLoading)) {
			$this->onLoading = "''";
		}


		//'{$siteUrl}/index{$this->relativePath}{$this->filename}{$ftu}.php',
		if ($this->TSObjectConf['cache.']['hash.']['special.']['lang']) {
			$lang = '&L='. $GLOBALS['TSFE']->sys_language_uid;
		}
		
		if ($this->TSObjectConf['cache.']['hash.']['special.']['feuser']) {
			// ftu = fe_typo_user
			$script = "coagoftu({$this->counter},
				'{$siteUrl}index.php?eID=coagoFtu&cacheHash={$this->cacheHash}&typeNum={$this->typeNum}&cachePeriod={$this->cachePeriod}{$lang}',
				{$this->onLoading},
				{$this->refresh});";

		} else {
			$script = "coago({$this->counter},
				'{$siteUrl}{$this->relativePath}{$this->filename}.{$this->cacheFileExtension}',
				'{$siteUrl}index.php?id={$GLOBALS['TSFE']->id}&type={$this->typeNum}{$lang}&cacheHash={$this->cacheHash}{$ftuHash}',
				{$this->cachePeriod},
				{$this->onLoading},
				{$this->refresh});";
		}
		
		// script minification
		$script = t3lib_div::minifyJavaScript($script, $error);
		if ($error) {
			t3lib_div::devLog('Error minimizing script: '.$error, $this->extKey, 3);
		}
		
		if ($this->confArr['compressJS']) {
			require PATH_site . 'typo3conf/ext/coago/res/contrib/JavaScriptPacker/class.JavaScriptPacker.php';
			$packer = new JavaScriptPacker($script);
			$script = $packer->pack();
		}

		return $script;
	}


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/class.tx_coago.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/class.tx_coago.php']);
}

?>