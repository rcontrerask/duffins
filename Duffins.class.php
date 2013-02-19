<?php

class Duffins{

	private $inserts=0;
	private $partial_inserts=0;
	private $duffcounts=0;

	private $stmtpool=array();
	private $stmtpool_single=array();

	private $dbconn;
	private $workload;
	private $commitEach;
	private $qc=0;

	public function __construct($dbconn,$workload,$commitEach){
		$this->dbconn=$dbconn;
		$this->workload=$workload?$workload:8;
		$this->commitEach=$commitEach>1?$commitEach:0;
	}

	public function __destruct(){
		$this->closeAllStatements();
	}

	public function setWorkload($workload){
		$this->workload=$workload;
	}

	public function setCommitEach($commitEach){
		$this->commitEach=$commitEach;
	}

	public function getStats(){
		return array(
			'insert_calls'=>$this->inserts,
			'executed_statement_calls'=>$this->duffcounts,
			'partial_statement_calls'=>$this->partial_inserts,
		);
	}

	public function closeAllStatements(){
		foreach($this->stmtpool as $tablename=>$v){
			$this->closeDuff($tablename);
		}
	}

	public function closeDuff($tablename){
		if(isset($this->stmtpool[$tablename])){
			mysqli_stmt_close($this->stmtpool[$tablename]);
			unset($this->stmtpool[$tablename]);
		}
		if(isset($this->stmtpool_single[$tablename])){
			mysqli_stmt_close($this->stmtpool_single[$tablename]);
			unset($this->stmtpool_single[$tablename]);
		}
	}

	private function commitEach($n){
		$this->qc++;
		if($this->qc>$n){
			$this->commit();
			$this->startTransaction();
		}
	}

	private function startTransaction(){
		mysqli_query($this->dbconn,'START TRANSACTION;');
	}

	private function commit(){
		mysqli_query($this->dbconn,'COMMIT;');
	}

	public function insert($tablename,$columns,$valuesArray,$mask){
		$this->inserts++;
		$rowcount=count($valuesArray);
		if(!$rowcount){
			return;	//nothing to insert
		}
		$colcount=count($columns);

		$skipStatements=$rowcount<$this->workload;	//skip prepared statements if rows to insert are less than the expected $workload.

		$partialInserts=$skipStatements?$rowcount:$rowcount-floor($rowcount/$this->workload)*$this->workload;

		$sql="insert into $tablename (".implode(',',$columns).") values ";
		$rowindex=0;
		if($partialInserts){
			if(!isset($this->stmtpool_single[$tablename])){
				$singleslots=array_fill(0,$colcount,'');
				$this->stmtpool_single[$tablename] = mysqli_prepare($this->dbconn,$sql."(".implode(',',array_fill(0,$colcount,'?')).")");

				if(!$this->stmtpool_single[$tablename]){
					throw new Exception("Prepare failed: ".mysqli_error($this->dbconn));
				}

				$varlist=array();
				for($i=0;$i<$colcount;$i++){
					$varlist[]='$singleslots['.$i.']';
				}
				$varliststr=implode(',',$varlist);
				$bindcode="mysqli_stmt_bind_param(\$this->stmtpool_single[\$tablename], '".$mask."', $varliststr);";
				eval($bindcode);
			}
			for($i=0;$i<$partialInserts;$i++){
				for($j=0;$j<$colcount;$j++){
					$singleslots[$j]=$valuesArray[$i][$j];
				}
				mysqli_stmt_execute($this->stmtpool_single[$tablename]);
				$rowindex++;
				$this->partial_inserts++;
			}
		}
		if($skipStatements){
			return;
		}
		//init duff statement
		if(!isset($this->stmtpool[$tablename])){
			$valph='('.implode(',',array_fill(0,$colcount,'?')).')';
			$full=implode(',',array_fill(0,$this->workload,$valph));
			$this->stmtpool[$tablename] = mysqli_prepare($this->dbconn,$sql.$full);
			$x=$this->workload*$colcount;
			$slots=array_fill(0,$x,'');
			$varlist=array();
			for($i=0;$i<$x;$i++){
				$varlist[]='$slots['.$i.']';
			}
			$varliststr=implode(',',$varlist);
			$bindcode="mysqli_stmt_bind_param(\$this->stmtpool[\$tablename], '".str_repeat($mask,$this->workload)."', $varliststr);";
			eval($bindcode);
		}
		//run duff statements
		$useTransactions=$commitEach>1;
		if($useTransactions){
			$this->startTransaction();
		}
		while(isset($valuesArray[$rowindex])){
			$s=0;
			for($u=0;$u<$this->workload;$u++){
				for($h=0;$h<$colcount;$h++){
					$slots[$s]=$valuesArray[$rowindex][$h];
					$s++;
				}
				$rowindex++;
			}
			$this->duffcounts++;
			mysqli_stmt_execute($this->stmtpool[$tablename]);
			if($useTransactions){
				$this->commitEach($this->commitEach);
			}
		}
		if($useTransactions){
			$this->commit();
		}
	}
}

?>
