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

/*
 * Example of hook changing the ajax script
 * 
 * @author Krystian Szymukowicz <ks@cms-partner.pl>
 */
	 

class tx_coago_mod {

	function getCoagoAjaxScript($parentObj) {


		$siteUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
		$counter = $parentObj->counter;

		$script = "
	
function coa_go_{$counter}() {	
var http_req_{$counter} = false;
if( navigator.appName === 'Microsoft Internet Explorer' ) {
	http_req_{$counter} = new ActiveXObject('Microsoft.XMLHTTP');
 } else {
	http_req_{$counter} = new XMLHttpRequest();
 }

 http_req_{$counter}.open('POST', '{$siteUrl}{$parentObj->relativePath}{$parentObj->filename}',true);
 http_req_{$counter}.send(null);

 ";

		if($parentObj->onLoading) {
			$script .= "document.getElementById('ncc-{$counter}').innerHTML = {$parentObj->onLoading};";
		}

		$script .= "
    http_req_{$counter}.onreadystatechange=function() {
	if( http_req_{$counter}.readyState === 4 ) {

	   ageInSeconds = (new Date() - Date.parse(http_req_{$counter}.getResponseHeader('Last-Modified'))) / 1000;

	   //alert('main condition:' + (http_req_{$counter}.status === 200) && ( (ageInSeconds < {$parentObj->cachePeriod}) || ('{$parentObj->cachePeriod}' === '0') ) + '<br /> counter :' +{$counter} + 'age cond :' + (ageInSeconds < {$parentObj->cachePeriod}) + 'cache period: ' + {$parentObj->cachePeriod} + 'ageinSec: ' + ageInSeconds);

	   if( (http_req_{$counter}.status === 200) && ( (ageInSeconds < {$parentObj->cachePeriod}) || ('{$parentObj->cachePeriod}' === '0') ) ) {
		  	setTimeout('', 1250);
	   		document.getElementById('ncc-{$counter}').innerHTML = http_req_{$counter}.responseText;
	   } else {

		  var http_req_{$counter}_r1 = false;
		  if(navigator.appName === 'Microsoft Internet Explorer') {
			 http_req_{$counter}_r1 = new ActiveXObject('Microsoft.XMLHTTP');
		  } else {
			 http_req_{$counter}_r1 = new XMLHttpRequest();
		  }

		  http_req_{$counter}_r1.open('POST', '{$siteUrl}index.php?id={$GLOBALS['TSFE']->id}&type={$parentObj->typeNum}&cacheHash={$parentObj->cacheHash}');
		  http_req_{$counter}_r1.send(null);

		  http_req_{$counter}_r1.onreadystatechange=function() {
			 if( http_req_{$counter}_r1.readyState === 4 ) {

				var http_req_{$counter}_r2 = false;
				if(navigator.appName === 'Microsoft Internet Explorer') {
				   http_req_{$counter}_r2 = new ActiveXObject('Microsoft.XMLHTTP');
				} else {
				   http_req_{$counter}_r2 = new XMLHttpRequest();
				}

				http_req_{$counter}_r2.open('POST', '{$siteUrl}{$parentObj->relativePath}{$parentObj->filename}',true);
				http_req_{$counter}_r2.send(null);";

		if($parentObj->onLoading) {
			$script .= "		document.getElementById('ncc-{$counter}').innerHTML = {$parentObj->onLoading};";
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
		if($parentObj->refresh){
			$script .= " setTimeout('coa_go_{$counter}()', {$parentObj->refresh});";
		}
		//cloasing bracet for all javascript
		$script .= "} coa_go_{$counter}();";

		return $script;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/misc/class.tx_coago_mod.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/coago/misc/class.tx_coago_mod.php']);
}

?>