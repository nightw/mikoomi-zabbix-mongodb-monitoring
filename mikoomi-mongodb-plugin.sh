#!/bin/bash
PATH=$PATH:/etc/zabbix/externalscripts:/home/zabbix/bin:/bin:/usr/sbin:/usr/bin:/usr/local/bin:/usr/local/sbin
export PATH
shift
BASE_DIR="`dirname $0`"
/usr/bin/php $BASE_DIR/mikoomi-mongodb-plugin.php $* 
echo 0
