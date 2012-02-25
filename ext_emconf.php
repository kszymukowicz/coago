<?php

########################################################################
# Extension Manager/Repository config file for ext: "coago"
#
# Auto generated 11-11-2009 13:06
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'COA_GO - per cObject caching with cache period',
	'description' => 'This extension improves caching of content objects in TYPO3. It can decrease time rendering of page and improve overall performance of your site. Works for fe users and nc_staticfilecache.',
	'category' => 'fe',
	'shy' => 0,
	'version' => '0.2.1',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'beta',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Krystian Szymukowicz',
	'author_email' => 'ks@cms-partner.pl',
	'author_company' => 'http://www.cms-partner.pl',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.1.0-4.6.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:13:{s:13:"ChangeLog.php";s:4:"290d";s:18:"class.tx_coago.php";s:4:"8b81";s:23:"class.tx_coagohooks.php";s:4:"ac2a";s:21:"ext_conf_template.txt";s:4:"4532";s:12:"ext_icon.gif";s:4:"bc2e";s:17:"ext_localconf.php";s:4:"05ee";s:14:"ext_tables.php";s:4:"04f4";s:14:"doc/manual.sxw";s:4:"6a33";s:21:"doc/readme_coa_go.gif";s:4:"d09c";s:27:"misc/class.tx_coago_mod.php";s:4:"8b68";s:14:"res/ajax/1.gif";s:4:"7b97";s:20:"static/constants.txt";s:4:"8638";s:16:"static/setup.txt";s:4:"4d39";}',
);

?>