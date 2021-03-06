<?php
// [modif oto] - Verify some options and parameters and update if needed.
$wamp_oto_version = "2.5.18";

// Do not change this line unless you know exactly why you do it.
$check_default_engine_MYISAM = true;

require ('wampserver.lib.php');
require 'config.inc.php';

//Check some files are DOS line ending
file_get_contents_dos($templateFile, false);
file_get_contents_dos($c_apacheConfFile, false);
file_get_contents_dos($c_apacheVhostConfFile, false);

//Check if some parameters exists in wampmanager.conf
$wampConfFileContents = file_get_contents_dos($configurationFile);
$options_add = false;
//Check if [options] group exists
if(strpos($wampConfFileContents, "[options]") === false) {
	//Create [options] group
	$wampConfFileContents = preg_replace("/^(defaultLanguage .*?\r\n)/m","$1[options]\r\n",$wampConfFileContents);
	$options_add = true;
}
//Check if [apacheoptions] group exists
if(strpos($wampConfFileContents, "[apacheoptions]") === false) {
	//Create [apacheoptions] group
	$wampConfFileContents = preg_replace("/^(apacheServiceRemoveParams .*?\r\n)/m","$1[apacheoptions]\r\n",$wampConfFileContents);
	$options_add = true;
}
//Check if [mysqloptions] group exists
if(strpos($wampConfFileContents, "[mysqloptions]") === false) {
	//Create [mysqloptions] group
	$wampConfFileContents = preg_replace("/^(mysqlServiceRemoveParams .*?\r\n)/m","$1[mysqloptions]\r\n",$wampConfFileContents);
	$options_add = true;
}

if($options_add) {
	$fpWampConf = fopen($configurationFile,"w");
	fwrite($fpWampConf,$wampConfFileContents);
	fclose($fpWampConf);
}

//Parameters for Wampmanager settings
for($i = 0 ; $i < count($wamp_Param) ; $i++) {
	if(!isset($wampConf[$wamp_Param[$i]])) {
	//Parameter does not exist
	createWampConfParam($wamp_Param[$i], "off", "[options]", $configurationFile);
	}
}

$apache_create = false;
//Settings for Apache (port used and change port)
for($i = 0 ; $i < count($apache_Param) ; $i++) {
	if(!isset($wampConf[$apache_Param[$i]])) {
	//Parameter does not exist
	createWampConfParam($apache_Param[$i], $apache_Param_Value[$i], "[apacheoptions]", $configurationFile);
	$apache_create = true;
	}
}
if($apache_create) {
	//Has user already set a port other than 80?
	$httpdFileContents = file_get_contents($c_apacheConfFile );
	if(preg_match("/^Listen.+:([0-9]{2,5})$/m",$httpdFileContents, $matches) > 0) {
		//port number is specified
		if($matches[1] != $c_DefaultPort) {
			$apacheConf['apachePortUsed'] = $matches[1];
			$apacheConf['apacheUseOtherPort'] = "on";
			wampIniSet($configurationFile, $apacheConf);
		}
	}
}

//Create wamp/cgi-bin/ directory if not exists
$dir_cgi = true;
if(!file_exists($c_installDir."/cgi-bin")) {
	if(mkdir($c_installDir."/cgi-bin") === false) {
		error_log("Could not create directory ".$c_installDir."/cgi-bin");
		$dir_cgi = false;
	}
}
else {
	if(!is_dir($c_installDir."/cgi-bin")) {
		error_log($c_installDir."/cgi-bin is not a directory");
		$dir_cgi = false;
	}
}
if($dir_cgi) {
	//Verify alias and path to wamp/cgi-bin in httpd.conf
	$httpd_modif = false;
	$httpdFileContents = file_get_contents($c_apacheConfFile);
	if(preg_match("~^([\t ]*ScriptAlias /cgi-bin/) (\".*\")\r?$~m",$httpdFileContents, $matches) > 0) {
		if($matches[2] != '"'.$c_installDir.'/cgi-bin"') {
			$httpdFileContents = preg_replace("~^([\t ]*ScriptAlias /cgi-bin/) (\".*\")\r?$~m","$1 \"".$c_installDir."/cgi-bin/\"",$httpdFileContents);
			$httpd_modif = true;
		}
	}
	else {
		error_log("ScriptAlias /cgi-bin/ ... not found in ".$c_apacheConfFile);
	}
	if(preg_match("~^(<Directory) (\".*/cgi-bin/?\")>\r?$~m",$httpdFileContents, $matches) > 0) {
		if($matches[2] !== '"'.$c_installDir.'/cgi-bin"') {
			$httpdFileContents = preg_replace("~^(<Directory) (\".*/cgi-bin\">)\r?$~m", "$1 \"".$c_installDir."/cgi-bin\">",$httpdFileContents);
			$httpd_modif = true;
		}
	}
	else {
		error_log("<Directory \".../cgi-bin\" not found in ".$c_apacheConfFile);
	}
	if($httpd_modif) {
		$file_write = fopen($c_apacheConfFile,"w");
		fwrite($file_write,$httpdFileContents);
		fclose($file_write);
	}
}

//Check alias and paths in httpd-autoindex.conf
check_autoindex();

//Settings for MySQL (port used and change port)
for($i = 0 ; $i < count($mysql_Param) ; $i++) {
	if(!isset($wampConf[$mysql_Param[$i]])) {
	//Parameter does not exist
	createWampConfParam($mysql_Param[$i], $mysql_Param_Value[$i], "[mysqloptions]", $configurationFile);
	}
}

//Check some value in my.ini file
$myIniContents = file_get_contents_dos($c_mysqlConfFile);
$my_ini_modified = false;
//Check name of the group [wamp...] under '# The MySQL server' in my.ini file
//must be the name of the mysql service.
if(strpos($myIniContents, "[".$c_mysqlService."]") === false) {
	$myIniNewContents = preg_replace("/^\[wamp.*\].*\n/m", "[".$c_mysqlService."]\r\n", $myIniContents, 1, $count);
	if(!is_null($myIniNewContents) && $count == 1) {
		$myIniContents = $myIniNewContents;
		$my_ini_modified = true;
		unset($myIniNewContents);
	}
}
if($check_default_engine_MYISAM) {
	//Check default-storage-engine=MYISAM to avoid problem with novices and innoDB
	if(strpos($myIniContents , "default-storage-engine") === false) {
		$myIniContents = str_replace("[".$c_mysqlService."]\r\n", "[".$c_mysqlService."]\r\n"."# The default storage engine that will be used when create new tables\r\ndefault-storage-engine=MYISAM\r\n", $myIniContents);
		$my_ini_modified = true;
	}
	else {
		//Not commented and MYISAM
		if(preg_match("/^(?![ \t]*#).*default-storage-engine.*[ \t]*=[ \t]*MYISAM\r?\n/m", $myIniContents, $matches) !== 1) {
			$myIniContents = preg_replace("/^.*default-storage-engine.*\r?\n/m","default-storage-engine=MYISAM\r\n",$myIniContents);
			$my_ini_modified = true;
		}
	}
}

if($my_ini_modified) {
	$fp = fopen($c_mysqlConfFile,'w');
	fwrite($fp,$myIniContents);
	fclose($fp);
}

//Language error messages of MySQL
//Put MySQL error messages in English if the language is not French.
//Warnings masked because the my.ini file defines comments by #, which is obsolete (Should be ;)
$mysqlConf = @parse_ini_file($c_mysqlConfFile);
if(isset($mysqlConf['lc-messages'])) {
	if($mysqlConf['lc-messages'] == 'fr_FR' && $wampConf['language'] != 'french') {
		$mysqlConfNew['lc-messages'] = "en_US";
		$iniFileContents = @file_get_contents($c_mysqlConfFile);
		foreach ($mysqlConfNew as $param => $value)
			$iniFileContents = preg_replace('|^'.$param.'=.*|m',$param.'='.$value,$iniFileContents);
		$fp = fopen($c_mysqlConfFile,'w');
		fwrite($fp,$iniFileContents);
		fclose($fp);
	}
}

//date.timezone and intl.default_locale (php.ini and phpForApache.ini)
$file_to_check = array(
	$c_phpVersionDir."/php".$wampConf['phpVersion']."/".$wampConf['phpConfFile'],
	$c_phpVersionDir."/php".$wampConf['phpVersion']."/".$phpConfFileForApache,
);

foreach($file_to_check as $c_phpConfFile) {
	$temp_file = file_get_contents_dos($c_phpConfFile);
	unset($phpConfNew, $temp_file);
	$phpConf = @parse_ini_file($c_phpConfFile);
	if(isset($phpConf['date.timezone']) && $phpConf['date.timezone'] == 'Europe/Paris' && $wampConf['language'] != 'french') {
			$phpConfNew['date.timezone'] = "UTC";
	}
	if(isset($phpConf['intl.default_locale']) && $phpConf['intl.default_locale'] == 'fr_FR' && $wampConf['language'] != 'french') {
			$phpConfNew['intl.default_locale'] = "en_US";
	}
	if(isset($phpConfNew)) {
		$iniFileContents = @file_get_contents($c_phpConfFile);
		foreach ($phpConfNew as $param => $value)
			$iniFileContents = preg_replace('|^'.$param.' = .*|m',$param.' = '.$value,$iniFileContents);
		$fp = fopen($c_phpConfFile,'w');
		fwrite($fp,$iniFileContents);
		fclose($fp);
	}
}

// Insert do not EDIT to wamp/bin/php/phpx.y.z/php.ini file
$do_not_edit = <<< NOTEDITEOF
[PHP]
; **************************************************************
; ****** DO NOT EDIT THIS FILE **** DO NOT EDIT THIS FILE ******
; * This file is only use by PHP CLI (Command Line Interface)  *
; * that is to say by Wampserver internal PHP scripts          *
; * THE CORRECT FILE TO EDIT is Wampmanager Icon->PHP->php.ini *
; * that is wamp/bin/apache/apache2.x.y/bin/php.ini            *
; **************************************************************

NOTEDITEOF;
$iniFileContents = @file_get_contents($c_phpVersionDir."/php".$wampConf['phpVersion']."/".$wampConf['phpConfFile']);
if(strpos($iniFileContents,"* DO NOT EDIT THIS FILE *") === false) {
	$iniFileContents = str_replace("[PHP]", $do_not_edit, $iniFileContents);
	$fp = fopen($c_phpVersionDir."/php".$wampConf['phpVersion']."/".$wampConf['phpConfFile'],'w');
	fwrite($fp,$iniFileContents);
	fclose($fp);
}

//Check if the file wamp/bin/php/DO_NOT_DELETE_x.y.z.txt match CLI php version used
if(!file_exists($c_phpVersionDir."/DO_NOT_DELETE_".$c_phpCliVersion.".txt")) {
	$do_not_delete_txt = "This PHP version ".$c_phpCliVersion." is used by WampServer in CLI mode.\r\nIf you delete it, WampServer won't work anymore.";
	if ($handle = opendir($c_phpVersionDir))	{
		while (false !== ($file = readdir($handle)))	{
			if ($file != "." && $file != ".." && !is_dir($c_phpVersionDir.'/'.$file)) {
				$list[] = $file;
			}
		}
		closedir($handle);
	}
	if(!empty($list)) {
		foreach($list as $value) {
			if(strpos($value,"DO_NOT_DELETE") !== false)
				unlink($c_phpVersionDir."/".$value);
		}
	}
	$fp = fopen($c_phpVersionDir."/DO_NOT_DELETE_".$c_phpCliVersion.".txt",'w');
	fwrite($fp,$do_not_delete_txt);
	fclose($fp);
}

//Check if PhpMyAdmin version in wampmanager.conf is the one used
$PhpMyAdminFileContents = @file_get_contents($aliasDir."phpmyadmin.conf");
//Exemple: <Directory j:/wamp/apps/phpmyadmin4.3.2/>
if(preg_match("#^[ \t]*<Directory.*phpmyadmin([0-9\.]*)/>\r?$#m",$PhpMyAdminFileContents, $matches) == 1) {
	if(version_compare($wampConf['phpmyadminVersion'], $matches[1]) != 0) {
		$phpmyAdminVersion['phpmyadminVersion'] = $matches[1];
		wampIniSet($configurationFile,$phpmyAdminVersion);
	}
}

if(version_compare($wampConf['wampserverVersion'], $wamp_oto_version) < 0 ) {
	$wampVersion['wampserverVersion'] = $wamp_oto_version;
	wampIniSet($configurationFile, $wampVersion);
}

?>
