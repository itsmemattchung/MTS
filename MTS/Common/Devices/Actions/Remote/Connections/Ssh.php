<?php
//� 2016 Martin Madsen
namespace MTS\Common\Devices\Actions\Remote\Connections;
use MTS\Common\Devices\Actions\Remote\Base;

class Ssh extends Base
{
	public function connectByUsername($shellObj, $username, $password, $ipAddress, $port=22)
	{
		//return the name of the user php is executed as
		$this->_classStore['requestType']	= __FUNCTION__;
		$this->_classStore['shellObj']		= $shellObj;
		$this->_classStore['username']		= $username;
		$this->_classStore['password']		= $password;
		$this->_classStore['ipaddress']		= $ipAddress;
		$this->_classStore['port']			= $port;
		return $this->execute();
	}
	private function execute()
	{
		$requestType		= $this->_classStore['requestType'];
		$shellObj			= $this->_classStore['shellObj'];
		
		if ($requestType == 'connectByUsername') {
			
			$ipaddress		= $this->_classStore['ipaddress'];
			$username		= $this->_classStore['username'];
			$password		= $this->_classStore['password'];
			$port			= $this->_classStore['port'];
			
			//remove the username and password, they are too sensetive to keep around in case the object is dumped
			unset($this->_classStore['username']);
			unset($this->_classStore['password']);

			if ($shellObj instanceof \MTS\Common\Devices\Shells\Bash) {

				$connCmd		= "ssh -p ".$port." -o \"StrictHostKeyChecking no\" -o \"GSSAPIAuthentication=no\" ".$username."@".$ipaddress."";

				$regExConn		= "(".$ipaddress."'s password:|No route to host|Could not resolve hostname)";
				$connReturn		= $shellObj->exeCmd($connCmd, $regExConn);
				
				preg_match("/".$regExConn."/", $connReturn, $returnConn);
				
				if (!isset($returnConn[1])) {
					//let this pass through it is not handled
				} elseif ($returnConn[1] == $ipaddress."'s password:") {
					
					$regExPass	= "(MikroTik RouterOS|Permission denied|".$username."@)";
					$passReturn	= $shellObj->exeCmd($password, $regExPass);
					preg_match("/".$regExPass."/", $passReturn, $returnPass);
					
					if (!isset($returnPass[1])) {
						//let this pass through it is not handled
						$success	= false;
					} elseif ($returnPass[1] == "MikroTik RouterOS") {
						
						//we have to log out, since we cutoff the return
						//we have to make sure the welcome text is done
						$shellObj->exeCmd("", "\>");
						
						//logged in, make sure the username includes disabling colors
						$validLogin	= true;
						preg_match("/(.*?)\+(.*)/", $username, $addName);

						if (isset($addName[2]) === false) {
							//username does not include any options
							$username	= $username . "+ct";
							$validLogin	= false;
						} else if ($addName[2] != "ct") {
							//username has the wrong options
							$username	= $addName[1] . "+ct";
							$validLogin	= false;
						}

						if ($validLogin === false) {
							
							$shellObj->exeCmd("/quit", false, 0);
							$shellObj->exeCmd("");

							//then back in with a properly formatted username
							$this->connectByUsername($shellObj, $username, $password, $ipaddress, $port);
							return;
							
						} else {

							$childShell			= new \MTS\Common\Devices\Shells\RouterOS();
							$shellObj->setChildShell($childShell);
							return;
						}

					} elseif ($returnPass[1] == $username."@") {
						//logged in, now figure out what type of shell we got on the other side
						$strCmd			= "ps hp $$ | awk '{print $5}'";
						$regExShell		= "(".$username."@)";
						$shellName		= strtolower(trim($shellObj->exeCmd($strCmd, $regExShell)));
			
						if (preg_match("/-bash/", $shellName)) {
							
							$childShell			= new \MTS\Common\Devices\Shells\Bash();
							$shellObj->setChildShell($childShell);
								
							//we must issue at least one command to initialize the new shell, because it is already running
							\MTS\Factories::getActions()->getRemoteOperatingSystem()->getUsername($shellObj);
							return;
						} else {
							//let this pass through it is not handled
							$success	= false;
						}

					} elseif ($returnPass[1] == "Permission denied") {
						$shellObj->killLastProcess();
						throw new \Exception(__METHOD__ . ">> User: " . $username . ", incorrect password");
					}
					
				} elseif ($returnConn[1] == "No route to host") {
					throw new \Exception(__METHOD__ . ">> SSH: No route to host");
				} elseif ($returnConn[1] == "Could not resolve hostname") {
					throw new \Exception(__METHOD__ . ">> SSH: Could not resolve hostname: " . $ipaddress);
				}
			}
		}

		throw new \Exception(__METHOD__ . ">> Not Handled for Request Type: " . $requestType);
	}
}