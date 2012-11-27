<?php

class sspmod_sbcasserver_Auth_Process_UserRegistry extends SimpleSAML_Auth_ProcessingFilter {

      private $soapClient;
      private $activeUserRegistryStatuses;
      private $userRegistryRemoteSystems;
 
      public function __construct($config, $reserved) {
            parent::__construct($config, $reserved);

        if(!is_string($config['ws-userregistry'])) {
            throw new Exception('Missing or invalid ws-userregistry url option in config.');
        }

        $wsuserregistry = $config['ws-userregistry'];

        SimpleSAML_Logger::debug('SBUserRegistry: ws-userregistry url ' . var_export($wsuserregistry, TRUE) . '.');

        $this->soapClient = new SoapClient($wsuserregistry);

        $this->activeUserRegistryStatuses = $config['activeUserRegistryStatuses'];
        $this->userRegistryRemoteSystems = $config['userRegistryRemoteSystems'];
      }

      public function process(&$request) {
            $username = $this->getUserIdFromRequest($request);

            SimpleSAML_Logger::debug('SBUserRegistry: looking up user ' . var_export($username, TRUE) . '.');

            $userRegistryResponse = $this->soapClient->findUserAccount(array('accountId' => $username));

            if($userRegistryResponse->serviceStatus == 'AccountRetrieved') {
                  SimpleSAML_Logger::debug('SBUserRegistry: user has borrower id ' . var_export($userRegistryResponse->userAccount->borrowerId, TRUE) . '.');
            } else if($userRegistryResponse->serviceStatus == 'SystemError') {
                  SimpleSAML_Logger::error('SBUserRegistry: look up of user ' . var_export($username, TRUE) . ' failed with status '.var_export($userRegistryResponse->serviceStatus).'.');
            }
      }

      private function getUserIdFromRequest($request) {
            $id = $request['Attributes']['schacPersonalUniqueID'];

            if(!is_null($id)) {
                  $id = str_replace('urn:mace:terena.org:schac:personalUniqueID:dk:CPR:','',$id[0],$count);
                  
                  if($count > 0) {
                        return $id;      
                  }
            }

            return null;
      }
}
?>