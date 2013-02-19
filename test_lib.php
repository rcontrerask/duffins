<?php

function get_db_connection(){
  $dbconn = mysqli_connect('localhost', 'USER', 'PASS', 'DBNAME');
	if(!$dbconn){
		throw new Exception("Can't connect to database: ".mysqli_connect_error());
	}
	return $dbconn;
}

function get_random_values($count){
	$values=array();
	for($i=0;$i<$count;$i++){
		$r1=mt_rand(1, 100000);
		$r2=mt_rand(1, 100000);
		$row=array(
			$r1,
			$r2,
			md5($r1),
			md5($r2),
			md5($r1.$r2),
		);
		$values[]=$row;
	}
	return $values;
}

function microtime_float(){
    list($usec,$sec)=explode(' ', microtime());
    return ((float)$usec + (float)$sec);
}

?>
