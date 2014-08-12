#!/usr/bin/env php
<?php

/**
 * Download and build local assets for phonegap
 * 
 * requires php, wget, gunzip
 *
 */

$curpath = getcwd();
if (preg_match('/platforms\/ios/',$curpath)) {
	$ap = '../../';
}
$server = 'http://cockpit3.localhost/';
$srcPath = $ap.'./www_source/';
$path = $ap.'./platforms/ios/www/';
$live = $argv[1] == 'live' ? true : false;


echo "Building for ".($live ? 'LIVE' : 'BETA')."\n";

// clean assets
echo "Cleaning assets...";
$cleanPaths = array(
	'/Users/arzynik/Sites/crunchbutton/cache/data/*',
	'/Users/arzynik/Sites/crunchbutton/cache/min/*',
	$path.'assets'
);
foreach ($cleanPaths as $p) {
	shell_exec('rm -Rf '.$p);
}
// delete all js that isnt the generated plugins file
shell_exec("rm -rf $(ls ".$path."*.js|grep -v 'cordova_plugins.js')");


echo "complete.\n";


// create and copy template dirs
echo "Creating directories";
shell_exec('cp -R '.$srcPath.'assets '.$path.'assets && mv '.$path.'assets/js/* '.$path);
echo "complete.\n";


// download assets from server
echo "Downloading assets bundle...\n";

function download($file, $dst = null, $usegzip = false) {
	global $server, $path, $curpath;
	
	$parts = pathinfo($path.'assets/'.$file);
	$dstpath = $dst !== null ? $path.$dst.$parts['basename'] : $path.'assets/'.$file;
	
	echo '	'.$file.'... ';

	if (!file_exists($parts['dirname'])) {
		mkdir($parts['dirname'], 0755, true);
	}

	if ($usegzip) {
		shell_exec('/usr/local/bin/wget -q -O - --header="Accept-Encoding: gzip" "'.$server.'assets/'.$file.($live ? '?__live=1' : '').'" | gunzip > "'.$dstpath.'"');
	} else {
		shell_exec('/usr/local/bin/wget -q -O '.$dstpath.' "'.$server.'assets/'.$file.($live ? '?__live=1' : '').'"');
		// file_put_contents($path.'assets/'.$file, file_get_contents($server.$file));
	}
	echo "complete.\n";
}

$assets = json_decode(file_get_contents($server.'api/build'.($live ? '?__live=1' : '')));
$config = json_decode(file_get_contents($server.'api/build/config'.($live ? '?__live=1' : '')));

foreach ($assets as $asset) {
	$type = explode('/',$asset);
	$type = $type[0];

	switch ($type) {
		case 'view':
			$content .= '<script type="text/ng-template" id="assets/'.$asset.'">'.file_get_contents($server.'assets/'.$asset).'</script>';
			break;

		case 'audio':
		case 'images':
		case 'fonts':
			download($asset);
			break;

		case 'js':
			download($asset, '', true);
			break;

		case 'css':
			download($asset, null, true);
			break;
	}
}

echo "Asset download complete.\n";


// create the index file
echo "Building body...\n";
$index = file_get_contents($path.'assets/view/template.html');
$body = file_get_contents($server.'assets/view/body.html'.($live ? '?__live=1' : ''));
$index = str_replace('<body></body>', '<body bgcolor="#fffef8" class="ios7 no-init">'.$body.'</body>', $index);
$index = str_replace('<templates></templates>', $content, $index);
file_put_contents($path.'index.html', $index);


// add facebook to index js
echo "Building index js...\n";
$index = file_get_contents($path.'index.js');
$index = str_replace('FACEBOOK_APP_IP', $config->facebook, $index);
$index = str_replace('APP_SERVER_URL', $live ? 'https://crunchbutton.com/' : 'http://beta.crunchr.co/', $index);
file_put_contents($path.'index.js', $index);


// replace playlist values
echo "Building info plist...\n";
$replaces = [
	'FacebookAppID' => $config->facebook,
	'CFBundleDisplayName' => $live ? '${PRODUCT_NAME}' : 'Beta',
	'CFBundleIdentifier' => $live ? 'com.crunchbutton' : 'com.crunchbutton.beta',
	'CFBundleURLSchemes' => 'fb'.$config->facebook
];

$plst = file($path.'../Crunchbutton/Crunchbutton-Info.plist');

foreach ($replaces as $find => $replace) {
	$correct = 0;
	foreach ($plst as $l => $line) {
		if (preg_match('/'.$find.'/i', $line)) {
			$correct = $l+1;
			break;
		}
	}
	if (trim($plst[$correct]) == '<array>') {
		$correct++;
	}
	$plst[$correct] = '	<string>'.$replace."</string>\n";
}
$plst = join("",$plst);
file_put_contents($path.'../Crunchbutton/Crunchbutton-Info.plist', $plst);


// add facebook to info plst
echo "Building balanced plugin...\n";
$bal = file_get_contents($path.'../Crunchbutton/Plugins/BalancedPlugin.m');
$bal = preg_replace('/(NSString\* balancedId = @").*("\;)/','\\1'.$config->balanced.'\\2',$bal);
file_put_contents($path.'../Crunchbutton/Plugins/BalancedPlugin.m', $bal);


// yay
echo "\033[32m".($live ? 'LIVE' : 'BETA')." build complete!\033[37m
";
