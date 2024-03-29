#!/bin/zsh
#
#   This file is part of Pac Ninja.
#   https://github.com/Liquid369/pacninja-ctl
#
#   Pac Ninja is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#   (at your option) any later version.
#
#   Pac Ninja is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with Pac Ninja.  If not, see <http://www.gnu.org/licenses/>.
#

# Disable logging by default
updatelog=/dev/null
statuslog=/dev/null
votesrrdlog=/dev/null
balancelog=/dev/null
portchecklog=/dev/null
blockparserlog=/dev/null
autoupdatelog=/dev/null

# If parameter 1 is log then enable logging
if [[ "$1" == "log" ]]; then
  rundate=$(date +%Y%m%d%H%M%S)
  updatelog=/var/log/dmn/update.$rundate.log
  statuslog=/var/log/dmn/status.$rundate.log
  votesrrdlog=/var/log/dmn/votesrrd.$rundate.log
  balancelog=/var/log/dmn/balance.$rundate.log
  portchecklog=/var/log/dmn/portcheck.$rundate.log
  blockparserlog=/var/log/dmn/blockparser.$rundate.log
  autoupdatelog=/var/log/dmn/autoupdate.$rundate.log
fi

# Sequentially run scripts
#/opt/dmnctl/pacdupdate >> $updatelog
/opt/pacninja-ctl/dmnctl status >> $statuslog
#/opt/dmnctl/dmnvotesrrd >> $votesrrdlog
/opt/pacninja-ctl/dmnblockparser >> $blockparserlog

# Concurrently run scripts
/opt/pacninja-ctl/dmnbalance >> $balancelog &
/opt/pacninja-ctl/dmnportcheck db >> $portchecklog &
/opt/pacninja-ctl/dmnautoupdate >> $autoupdatelog &
