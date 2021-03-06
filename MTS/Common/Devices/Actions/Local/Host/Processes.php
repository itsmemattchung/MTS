<?php
//� 2016 Martin Madsen
namespace MTS\Common\Devices\Actions\Local\Host;
use MTS\Common\Devices\Actions\Local\Base;

class Processes extends Base
{
	public function isRunningPid($pid)
	{
		if (preg_match("/^[0-9]+$/", $pid)) {
			$this->_classStore['requestType']	= __FUNCTION__;
			$this->_classStore['pid']			= $pid;
			return $this->execute();
		} else {
			throw new \Exception(__METHOD__ . ">> Invalid Input ");
		}
	}
	public function sigTermPid($pid)
	{
		if (preg_match("/^[0-9]+$/", $pid)) {
			$this->_classStore['requestType']	= __FUNCTION__;
			$this->_classStore['pid']			= $pid;
			return $this->execute();
		} else {
			throw new \Exception(__METHOD__ . ">> Invalid Input ");
		}
	}
	private function execute()
	{
		$requestType		= $this->_classStore['requestType'];
		
		if ($requestType == 'isRunningPid') {
			$pid		= $this->_classStore['pid'];
			
			$osType			= $this->getLocalOsObj()->getType();
			if ($osType == 'Linux') {
				$cmdString	= "(kill -0 ".$pid." 2> /dev/null && echo \"Alive\" ) || echo \"Dead\"";
				$rData		= $this->shellExec($cmdString);
				
				if ($rData == "Alive") {
					return true;
				} elseif ($rData == "Dead") {
					return false;
				}
			}

		} elseif ($requestType == 'sigTermPid') {
			$pid		= $this->_classStore['pid'];
			$running	= $this->isRunningPid($pid);

			if ($running === true) {
				$osType			= $this->getLocalOsObj()->getType();
				if ($osType == 'Linux') {
					
					$killExe	= \MTS\Factories::getActions()->getLocalApplicationPaths()->getExecutionFile("kill");
					
					if ($killExe !== false) {
						$cmdString	= $killExe->getPathAsString() . " -SIGTERM " . $pid;
						$this->shellExec($cmdString);
						$running	= $this->isRunningPid($pid);
						
						if ($running === false) {
							return;
						} else {
							throw new \Exception(__METHOD__ . ">> Failed to SIGTERM PID: " . $pid . ", still running");
						}
					}
				}
			}
		}
		throw new \Exception(__METHOD__ . ">> Not Handled for Request Type: " . $requestType);
	}
}