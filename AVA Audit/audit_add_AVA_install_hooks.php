<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 10/19/2016
 * Time: 4:40 PM
 */


date_default_timezone_set('America/Denver');
class PodioSessionManager {
    private static $connection_id = 3;
    private static $connection;

    public function __construct() {
    }

    public static function getConnection() {
        if (!self::$connection) {
            self::$connection = \EnvireTech\OauthConnector\Models\OrganizationConnection::with('connectionService')->find(self::$connection_id);
        }
        return self::$connection;
    }

    public static function getClientId () {
        return self::getConnection()->connectionService->config['client_id'];
    }

    public static function getClientSecret () {
        return self::getConnection()->connectionService->config['client_secret'];
    }

    public function get($authtype = null){
        $connection = self::getConnection();
        return new PodioOAuth(
            $connection->access_token,
            $connection->refresh_token
        );
    }
    public function set($oauth, $auth_type = null){
        $connection = self::getConnection();
        $connection->access_token = $oauth->access_token;
        $connection->save();
        self::$connection = $connection;
    }


}

try {
    Podio::setup(PodioSessionManager::getClientId(), PodioSessionManager::getClientSecret(), array( //client id and secret from connection service config
        "session_manager" => "PodioSessionManager"
    ));

    $requestParams = $event['request']['parameters'];
    $itemID = $requestParams['item_id'];

    //Get Trigger Subscription Item
    $item = PodioItem::get($itemID);
    $CustomerOrgID = $item->fields['organization-id']->values;


    //Get All Spaces in Org to Add AVA
    $OrgSpaces = PodioSpace::get_available($CustomerOrgID);

    //Add Ava to each Workspace
    foreach ($OrgSpaces as $space) {
        $SpaceID = $space->space_id;

        $JoinSpace = PodioSpaceMember::join($SpaceID);

    }
        
//        $AddAvaToSpace = PodioSpaceMember::add($SpaceID, array(
//            'role' => 'admin',
//            'message' => 'Please allow Ava into this space to enable your new Audit Extension.  Ava will be your personal Audit Assistant.',
//            array('users' => 2741867), //$AVAUserID,
//            array('profiles' => 155583208), ///$AvaProfileID,
//            //'mails' => //////$AvaEmail,
//        ));
//
//    }
    


    return [
        'success' => true,
        'result' => $OrgSpaces,
    ];

}catch(Exception $e)
{

    $event['response'] = [
        'status_code' => 400,
        'content' => [
            'success' => false,
            'result' => $result,
            'message' => "Error: ".$e,

        ]
    ];

    return;

}