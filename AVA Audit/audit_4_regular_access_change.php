<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 9/12/2016
 * Time: 1:32 PM
 */

date_default_timezone_set('America/Denver');
//<?php
//First you need create Connection and copy id to connection_id variable
//Now you can show a log activity in the synapp_activity table
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
    $revision_id = $requestParams['item_revision_id'];



    $previousRevisionID = $revision_id - 1;
    $revisionDifference = PodioItemDiff::get_for($itemID, $previousRevisionID, $revision_id);

    $item = PodioItem::get($itemID);
    $UserID = $item->fields['user-id-2']->values;

    //If Triggered From Admin Field

    $MembershipRole = "regular";

    $From = $revisionDifference[0]->from;
    $To = $revisionDifference[0]->to;

    $FromIDsArray = array();
    $ToIDsArray = array();

    foreach($From as $thing){
        array_push($FromIDsArray, $thing['value']['item_id']);
    }

    foreach($To as $thing){
        array_push($ToIDsArray, $thing['value']['item_id']);
    }



    $SizeofFrom = sizeof($FromIDsArray);
    $SizeofTo = sizeof($ToIDsArray);

    if($SizeofFrom > $SizeofTo){
        $BiggerArray = $FromIDsArray;
        $SmallArray = $ToIDsArray;
        $Action = "Remove";
    }
    if($SizeofFrom < $SizeofTo){
        $BiggerArray = $ToIDsArray;
        $SmallArray = $FromIDsArray;
        $Action = "Add";
    }

    $Difference = array_diff($BiggerArray, $SmallArray);

    foreach($Difference as $value){
        $GetWorkspaceItem = PodioItem::get($value);
        $WorkspaceID = $GetWorkspaceItem->fields['workspace-id-2']->values;

        if($Action == "Add"){
            $AddMembertoSpace = PodioSpaceMember::add($WorkspaceID, array('role'=>$MembershipRole, 'users' => array((int)$UserID)));
        }
        if($Action == "Remove"){
            PodioSpaceMember::delete($WorkspaceID, $UserID);
        }

    }

    


//RETURN / CATCH
    return [
        'success' => true,
        'result' => $result,
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