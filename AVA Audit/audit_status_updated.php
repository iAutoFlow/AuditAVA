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

        //Format Hooks to be Added
        $HookURL1 = '&ref_type=space&ref_id='.$SpaceID.'&type=member.add&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL2 = '&ref_type=space&ref_id='.$SpaceID.'&type=member.remove&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL3 = '&ref_type=space&ref_id='.$SpaceID.'&type=app.create&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL4 = '&ref_type=space&ref_id='.$SpaceID.'&type=app.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL5 = '&ref_type=space&ref_id='.$SpaceID.'&type=app.delete&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL6 = '&ref_type=space&ref_id='.$SpaceID.'&type=space.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL7 = '&ref_type=space&ref_id='.$SpaceID.'&type=space.create&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL8 = '&ref_type=space&ref_id='.$SpaceID.'&type=space.delete&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL9 = '&ref_type=space&ref_id='.$SpaceID.'&type=status.create&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL10 = '&ref_type=space&ref_id='.$SpaceID.'&type=status.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        $HookURL11 = '&ref_type=space&ref_id='.$SpaceID.'&type=status.delete&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$SpaceID;
        

        $AddAvaToSpace = PodioSpaceMember::add($SpaceID, array(
            'role' => 'admin',
            'message' => 'Please allow Ava into this space to enable your new Audit Extension.  Ava will be your personal Audit Assistant.',
            array('users' => 1406952), //$AVAUserID,
            array('profiles' => 68718029), ///$AvaProfileID,
        ));

    }



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