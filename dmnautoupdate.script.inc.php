<?php

/*
    This file is part of Pac Ninja.
    https://github.com/elbereth/Pacninja-ctl

    Pac Ninja is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Pac Ninja is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Pac Ninja.  If not, see <http://www.gnu.org/licenses/>.

 */

DEFINE('DMN_VERSION','0.1.1');

xecho('dmnautoupdate v'.DMN_VERSION."\n");

function die3($retcode) {
    unlink(DMN_AUTOUPDATE_SEMAPHORE);
    die($retcode);
}

if (file_exists(DMN_AUTOUPDATE_SEMAPHORE) && (posix_getpgid(intval(file_get_contents(DMN_AUTOUPDATE_SEMAPHORE))) !== false) ) {
    xecho("Already running (PID ".sprintf('%d',file_get_contents(DMN_AUTOUPDATE_SEMAPHORE)).")\n");
    die(10);
}
file_put_contents(DMN_AUTOUPDATE_SEMAPHORE,sprintf('%s',getmypid()));

xecho("Reading latest version fetched from server: ");
$curdatafile = dirname(__FILE__).'/dmnautoupdate.data.json';
if (file_exists($curdatafile)) {
    $data = file_get_contents($curdatafile);
    if ($data === FALSE) {
        echo "ERROR (Could not read file)\n";
        die3(1);
    }
    $data = json_decode($data,true);
    if ($data === FALSE) {
        echo "ERROR (Could not decode JSON data)\n";
        die3(1);
    }
    $dt = new \DateTime($data['Last-Modified']);
    echo "OK (".$dt->format('Y-m-d H:i:s')." / ".$data['Content-Length']." bytes)\n";
}
else {
    echo "First execution!\n";
    $data = array("Content-Length" => "-1",'Last-Modified' => 'Always');
}

xecho("Fetching from Pac Atlassian Bamboo server: ");

$url = "https://github.com/PACCommunity/PAC/releases/download/v0.12.6.2/PAC-v0.12.6.2-linux-x86.tar.gz";
$headers = get_headers($url, 1);
$dt = NULL;
if ((is_array($headers) && array_key_exists(0,$headers) && strstr($headers[0], '200'))) {
    $dt = new \DateTime($headers['Last-Modified']);
    echo "OK (".$dt->format('Y-m-d H:i:s')." / ".$headers['Content-Length']." bytes)\n";
}
else {
    echo "ERROR\n";
}

if ((intval($headers['Content-Length']) == intval($data['Content-Length'])) && ($data['Last-Modified'] == $headers['Last-Modified'])) {
    xecho("Nothing to do, no new binary...\n");
//    xecho("Restarting testnet node: ");
//    exec("/opt/dmnctl/dmnctl restart testnet p2pool",$output,$ret);
//    var_dump($output);
//    var_dump($ret);
//    echo "OK\n";
    die3(0);
}
else {
    xecho("Downloading new build from server: ");
    $rawfile = file_get_contents($url);
    if (($rawfile === FALSE) || (strlen($rawfile) != intval($headers['Content-Length']))) {
         echo "ERROR\n";
        die3(2);
    }
    echo "OK\n";
    xecho("Saving file: ");
    $tdir = tempnam('/tmp','dmnautoupdate.build.'.intval($headers['Content-Length']).'.'.md5($headers['Last-Modified']).'.');
    if (file_exists($tdir)) {
        unlink($tdir);
    }
    mkdir($tdir);
    $fnam = $tdir."/build.tar.gz";
    echo $fnam." ";
    if (file_put_contents($fnam,$rawfile) === FALSE) {
        echo "ERROR\n";
        die3(3);
    };
    echo "OK\n";
    unset($rawfile);
    xecho("Extracting file: ");
    $cdir = getcwd();
    chdir($tdir);
    exec("/bin/tar xf build.tar.gz",$output,$ret);
    chdir($cdir);
    if ($ret != 0) {
        echo "ERROR (untar failed with return code $ret)\n";
        die3(4);
    }
    unlink($fnam);
    $folder = FALSE;
    if ($handle = opendir($tdir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $folder = $entry;
            }
        }
        closedir($handle);
    }
    $pacdpath = $tdir."/".$folder."/bin/Pacd";
    if (($folder === FALSE) || (!file_exists($pacdpath))) {
        echo "ERROR (Could not extract correctly)\n";
        die3(5);
    }
    echo "OK (".$pacdpath.")\n";
    xecho("Retrieving version number: ");
    exec($pacdpath." -?",$output,$ret);
    if ($ret != 0) {
        echo "ERROR (Pacd return code $ret)\n";
        die3(5);
    }
    if (!preg_match('/^Pac Core Daemon version v(.+)$/',$output[0],$match)) {
        echo "ERROR (Pacd return version do not match regexp '".$output[0]."')\n";
        die3(6);
    };
    $version = $match[1];
    echo "OK (".$version.")\n";
    xecho("Adding new version to database: ");
    rename($pacdpath,"/opt/Pacd/0.12/Pacd-".$version);
    exec("/opt/dmnctl/dmnctl version /opt/Pacd/0.12/Pacd-".$version." ".$version." 1 1",$output,$ret);
    var_dump($output);
    var_dump($ret);
    delTree($tdir);
    echo "OK\n";
    xecho("Restarting testnet node: ");
    exec("/opt/dmnctl/dmnctl restart testnet p2pool",$output,$ret);
    if ($ret != 0) {
        echo "ERROR (return code of dmnctl restart was $ret)\n";
        var_dump($output);
        die3(8);
    }
    exec("/opt/dmnctl/dmnctl restart testnet masternode",$output,$ret);
    if ($ret != 0) {
        echo "ERROR (return code of dmnctl restart was $ret)\n";
        var_dump($output);
        die3(8);
    }
    echo "OK\n";
    xecho("Saving data for next run...");
    if (file_put_contents($curdatafile,json_encode($headers)) === FALSE) {
        echo "ERROR\n";
        die3(7);
    }
    echo "OK\n";
}

?>
