<?php

/*
    This file is part of Pac Ninja.
    https://github.com/Liquid369/pacninja-ctl

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

if (!defined('DMN_SCRIPT') || !defined('DMN_CONFIG') || (DMN_SCRIPT !== true) || (DMN_CONFIG !== true)) {
  die('Not executable');
}

define('DMN_VERSION','1.2.5');

// Start the masternodes
function dmn_start($uname,$conf,$pacd,$extra="") {

  $testnet = ($conf->getconfig('testnet') == 1);
  $pid = dmn_getpid($uname,$testnet);
  $startdmn = (dmn_checkpid($pid) === false);
  if (!$startdmn) {
    echo "Already running. Nothing to do.";
    $res = true;
  }
  else {
//    $dmnenabled = ($conf->getmnctlconfig('enable') == 1);
//    if ($dmnenabled) {
      $RUNASUID = dmn_getuid($uname,$RUNASGID);
      if ($testnet) {
        $nice = DMN_NICELEVEL_TEST;
      }
      else {
        $nice = DMN_NICELEVEL_MAIN;
      }
      $trycount = 0;
      $res = false;
      while ((!$res) && (!dmn_checkpid(dmn_getpid($uname,$testnet))) && ($trycount < 3)) {
        echo "T$trycount.";
        exec("/sbin/start-stop-daemon -S -c $RUNASUID:$RUNASGID -N " . $nice . " -x /usr/bin/env MALLOC_ARENA_MAX=1 " . $pacd . " -u $RUNASUID -q -- -daemon $extra");
        usleep(250000);
        $waitcount = 0;
        while ((!dmn_checkpid(dmn_getpid($uname, $testnet))) && ($waitcount < DMN_STOPWAIT)) {
          usleep(1000000);
          $waitcount++;
          echo ".";
        }
        if (dmn_checkpid(dmn_getpid($uname, $testnet))) {
          echo "Started!";
          $res = true;
        }
        $trycount++;
        if ($trycount == 3) {
          echo "Could not start!";
        };
      }
//    }
//    else {
//      echo "DISABLED";
//      $res = true;
//    }
  }
  return $res;

}

// Stop the masternode
function dmn_stop($uname,$conf) {

  $testnet = ($conf->getconfig('testnet') == 1);
  if ($testnet) {
    $testinfo = '/testnet3';
  }
  else {
    $testinfo = '';
  }

  $rpc = new \EasyDash($conf->getconfig('rpcuser'),$conf->getconfig('rpcpassword'),'localhost',$conf->getconfig('rpcport'));

  $pid = dmn_getpid($uname,$testnet);

  if ($pid !== false) {
    $tmp = $rpc->stop();
    if (($rpc->response['result'] != "PacCoin server stopping") && ($rpc->response['result'] != "Paccoin server stopping") && ($rpc->response['result'] != "Paccoin Core server stopping")) {
      echo "Unexpected daemon answer (".$rpc->response['result'].") ";
    }
    usleep(250000);
    $waitcount = 0;
    while (dmn_checkpid($pid) && ($waitcount < DMN_STOPWAIT)) {
      usleep(1000000);
      $waitcount++;
      echo ".";
    }
    if (dmn_checkpid($pid)) {
      echo "Soft Stop Failed! Forcing Kill... ";
      exec('kill -s kill '.$pid);
      $waitcount = 0;
      while (dmn_checkpid($pid) && ($waitcount < DMN_STOPWAIT)) {
        echo '.';
        usleep(1000000);
        $waitcount++;
      }
      if (dmn_checkpid($pid)) {
        echo "Failed!";
        $res = false;
      }
      else {
        if (file_exists('/home/'.$uname."/.darkcoin$testinfo/darkcoind.pid")) {
          unlink('/home/'.$uname."/.darkcoin$testinfo/darkcoind.pid");
        }
        if (file_exists('/home/'.$uname."/.dash$testinfo/pacd.pid")) {
          unlink('/home/'.$uname."/.dash$testinfo/pacd.pid");
        }
        echo "OK (Killed) ";
        $res = true;
      }
    }
    else {
      echo " OK (Soft Stop) ";
      $res = true;
    }
  }
  else {
    echo "NOT started ";
    $res = true;
  }
  return $res;

}

if (($argc < 3) && ($argv > 5)) {
  xecho("Usage: ".basename($argv[0])." uname (start|stop|restart) [pacd] [extra_params]\n");
  die(1);
}

$uname = $argv[1];
$command = $argv[2];
if ($argc > 3) {
  $pacd = $argv[3];
}
else {
  $pacd = DMN_DASHD_DEFAULT;
}
if ($argc > 4) {
  $extra = $argv[4];
}
else {
  $extra = "";
}

if (!is_dir(DMN_PID_PATH.$uname)) {
  xecho("This node don't exist: "."/root\n");
  die(2);
}

$conf = new DashConfig($uname);
if (!$conf->isConfigLoaded()) {
  xecho("Error (Config could not be loaded)\n");
  die(7);
}

if ($command == 'start') {
  if (!is_executable($pacd)) {
    xecho("Error ($pacd is not an executable file)\n");
    die(8);
  }
  xecho("Starting $uname: ");
  if (dmn_start($uname,$conf,$pacd,$extra)) {
    echo "\n";
    die(0);
  }
  else {
    echo "\n";
    die(5);
  }
}
elseif ($command == 'stop') {
  xecho("Stopping $uname: ");
  if (dmn_stop($uname,$conf)) {
    echo "\n";
    die(0);
  }
  else {
    echo "\n";
    die(6);
  }
}
elseif ($command == 'restart') {
  if (!is_executable($pacd)) {
    xecho("Error ($pacd is not an executable file)\n");
    die(8);
  }
  xecho("Restarting $uname: ");
  if (dmn_stop($uname,$conf)) {
    if (dmn_start($uname,$conf,$pacd,$extra)) {
     echo "\n";
     die(0);
    }
    else {
    echo "\n";
      die(5);
    }
  }
  else {
    echo(" Could not stop daemon. Giving up.\n");
    die(4);
  }
}
else {
  xecho('Unknown command: '.$command."\n");
  die(3);
}

?>
