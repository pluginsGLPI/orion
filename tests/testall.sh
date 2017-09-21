#!/bin/sh
SELF=`readlink -f $0`
SELFDIR=`dirname $SELF`
DBNAME=unit_test_01

# not working
DEBUG="-d xdebug.remote_host=127.0.0.1 -d xdebug.remote_port=9000 -d xdebug.remote_enable=1"
#DEBUG="$DEBUG -d xdebug.autostart=1 -d xdebug.remote_mode=req -d xdebug.remote_log=/tmp/xdebug.log"

oldpath=`pwd`
cd $SELFDIR/..
mysql -u glpi -pglpi -e "DROP DATABASE IF EXISTS \`$DBNAME\`"
php ../../tools/cliinstall.php --db=$DBNAME --user=glpi --pass=glpi --tests --force
#php -S localhost:8088 -t ../.. ../../tests/router.php &>/dev/null &
#PID=$!
#echo $PID
vendor/bin/atoum --debug -bf tests/bootstrap.php -no-cc --max-children-number 1 -d tests/suite-install
#vendor/bin/atoum --debug -bf tests/bootstrap.php -no-cc --max-children-number 1 -d tests/suite-unit
#vendor/bin/atoum --debug -bf tests/bootstrap.php -no-cc --max-children-number 1 -d tests/suite-integration
vendor/bin/atoum --debug -bf tests/bootstrap.php -no-cc --max-children-number 1 -d tests/suite-uninstall
cd $oldpath
#kill $PID

vendor/bin/phpcbf -p --standard=vendor/glpi-project/coding-standard/GlpiStandard/ *.php install/ inc/ front/ ajax/ tests/
