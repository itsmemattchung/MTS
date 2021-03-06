<?php
//� 2016 Martin Madsen
namespace MTS\Common\Devices\Shells;

class Base
{
	protected $_childShell=null;
	protected $_parentShell=null;
	protected $_initialized=null;
	public $debug=false;
	public $debugData=array();
	
	public function __construct()
	{
		//on uncaught exception __destruct is not called, this leaves the shell as a zombie running on the system we cant have that.
		register_shutdown_function(array($this, '__destruct'));
	}
	public function __destruct()
	{
		if ($this->_parentShell === null) {
			//destruction should only be triggered in the initial shell.
			//that way we get an orderly shutdown of nested shells
			$this->terminate();
		}
	}
	public function setDebug($bool)
	{
		$this->debug	= $bool;
		$childShell		= $this->getChildShell();
		if ($childShell !== null && $childShell->debug !== $bool) {
			$childShell->setDebug($bool);
		}
	}
	public function addDebugData($debugData)
	{
		$parentShell		= $this->getParentShell();
		if ($parentShell === null) {
			$this->debugData[]	= $debugData;
		} else {
			$parentShell->addDebugData($debugData);
		}
	}
	
	public function exeCmd($strCmd, $delimitor=null, $timeout=null)
	{
		//$strCmd: string command to execute
		
		//$delimitor: regex when matched ends the command and returns data.
		//defaults to a custom shell prompt determined by the shell class
		//You should only override the default if the command does not end in a regular prompt, or you want only a partial return from the command.
		//If you do not want to use a delimitor at all set to false, this will force a read until $timeout is exceeded

		//$timeout: the absolute longest the command is allowed to run
		//set to 0 if you do not wish the receive a return from the command
		//You should only override if a command takes a very long time or for a command that continues to return data
		//i.e ping. Without a timeout on a ping the command would never finish and return
		//default is determined by the shell class
		
		//if you know a command will continue to return output and hold up the shell
		//you should issue another command to stop it or use the killLastProcess() function

		try {
			$childShell	= $this->getChildShell();
			if ($childShell !== null) {
				//must execute on child as it rides on top of this shell
				return $childShell->exeCmd($strCmd, $delimitor, $timeout);
			} else {
				return $this->shellStrExecute($strCmd, $delimitor, $timeout);
			}
			
		} catch (\Exception $e) {
			switch($e->getCode()){
				default;
				throw $e;
			}
		}
	}
	public function killLastProcess()
	{
		$childShell	= $this->getChildShell();
		if ($childShell !== null) {
			//must execute on child as it rides on top of this shell
			$childShell->killLastProcess();
		} else {
			$this->shellKillLastProcess();
		}
	}
	public function terminate()
	{
		$childError	= null;
		$ownError	= null;
		try {
			//child shells must be shutdown before this
			$childShell	= $this->getChildShell();
			if ($childShell !== null) {
				$childShell->terminate();
			}
		} catch (\Exception $e) {
			switch($e->getCode()){
				default;
				$childError = $e;
			}
		}
		
		try {
			
			$this->shellTerminate();
		} catch (\Exception $e) {
			switch($e->getCode()){
				default;
				$ownError = $e;
			}
		}

		//tell the parent we are shutdown
		$parentShell	= $this->getParentShell();
		if ($parentShell !== null) {
			$parentShell->setChildShell(null);
			$parentShell->exeCmd("");
		}
		
		//finish by throwing the errors. It is crucial that the parent
		//is informed it no longer has a child, even though it may have failed 
		//some part of the termination process. otherwise commands are still passed upstream
		//and the initial shell will never get a chance to terminate
		if ($childError !== null) {
			throw $childError;
		} elseif ($ownError !== null) {
			throw $ownError;
		}
	}
	public function setChildShell($shellObj)
	{
		if ($shellObj === null) {
			//this is a child destructing it self and letting its parent know it is done
			$this->_childShell = null;
		} else {
			$childShell	= $this->getChildShell();
			if ($childShell !== null) {
				$childShell->setChildShell($shellObj);
			} else {
				$this->_childShell 			= $shellObj;
				$this->_childShell->setDebug($this->debug);
				$this->_childShell->setParentShell($this);
			}
		}
	}
	public function getChildShell()
	{
		return $this->_childShell;
	}
	public function setParentShell($shellObj)
	{
		//this should only be set by the parent shell itself
		$this->_parentShell	= $shellObj;
	}
	public function getParentShell()
	{
		return $this->_parentShell;
	}
	public function getInitialized()
	{
		return $this->_initialized;
	}
}