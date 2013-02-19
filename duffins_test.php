<?php

chdir(dirname(__FILE__));

include 'test_lib.php';

include 'Duffins.class.php';

$numrows=108;  //number of random records to insert.

$workload=100;		//number of records to be inserted in a single prepared statement.
$commitEach=25;		//number of statements to execute within transactions.
					//actual rows inserted will be $workload * $commitEach.

$dbconn=get_db_connection();

$duffins=new Duffins($dbconn,$workload,$commitEach);

$columns=array('a','b','c','d','e');

$allValues=get_random_values($numrows);
$t1=microtime_float();
$duffins->insert('foo', $columns, $allValues, 'ddsss');
$t2=microtime_float();

print round($numrows/($t2-$t1),2)." records per second.\n";

print_r($duffins->getStats());

?>
