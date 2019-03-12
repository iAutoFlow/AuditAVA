<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 9/30/2016
 * Time: 11:25 AM
 */
$Curl = new\Curl\Curl();
date_default_timezone_set('America/Denver');
//<?php
//First you need create Connection and copy id to connection_id variable
//Now you can show a log activity in the synapp_activity table
class PodioSessionManager {
    private static $connection_id = 191;
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
    $SpaceID = $requestParams['space_id'];
    $UserID = $requestParams['user_id'];


    //Get Trigger Item Space & Org info
    $Space = PodioSpace::get($SpaceID);
    $SpaceName = $Space->name;
    $SpaceID = $Space->space_id;
    $OrgName = $Space->org->name;
    $OrgID = $Space->org->org_id;


    //Get Space Member
    $SpaceMemberAddedInfo = PodioSpaceMember::get($SpaceID, $UserID);
    $UserName = $SpaceMemberAddedInfo->user->name;
    $SpaceUserRole = $SpaceMemberAddedInfo->role;
    $InvitedOn = $SpaceMemberAddedInfo->invited_on;
    $StartedOn = $SpaceMemberAddedInfo->started_on;


    //Filter Database Customers by Org ID to get Customers Audit Space ID
    $FilterCustomers = PodioItem::filter(15229543, array('filters'=>array('organization-id'=>(string)$OrgID)));
    $CustomerItemID = $FilterCustomers[0]->item_id;

    //Get Customer Item
    $CustomerItem = PodioItem::get($CustomerItemID);
    $SubscriptionStatus = $CustomerItem->fields['subscription-status']->values;
    $AuditSpaceID = $CustomerItem->fields['extension-space-id']->values;

    //End if Subscription is not Active
    if($SubscriptionStatus !== "Active"){exit;}


    //Get Clients Audit Space && App ID's
    $AuditSpaceApps = PodioApp::get_for_space($AuditSpaceID);
    foreach($AuditSpaceApps as $app) {
        $AppName = $app->config['name'];
        if ($AppName == "my ORG") {
            $myORGAppID = $app->app_id;
        }
        if ($AppName == "Apps") {
            $AppsAppID = $app->app_id;
        }
        if ($AppName == "Workspaces") {
            $WorkspacesAppID = $app->app_id;
        }
        if ($AppName == "Users") {
            $UsersAppID = $app->app_id;
        }
        if ($AppName == "Action Logs") {
            $ActionLogsAppID = $app->app_id;
        }
    }






    //Filter Workspaces app by Trigger Item's Space ID
    $FilterWorkspaces = PodioItem::filter($WorkspacesAppID, array('filters'=>array('workspace-id-2'=>(string)$SpaceID)));
    $SpaceItemID = $FilterWorkspaces[0]->item_id;

    //Get Space Item
    $SpaceItem = PodioItem::get($SpaceItemID);
    $LockDownStatus = $SpaceItem->fields['status']->values[0]['text'];



    //If LockDown Status is "Hard"
    if($LockDownStatus == "Hard"){
        $CreateComment = PodioComment::create('item', $SpaceItemID,array('value'=>$UserName." has been added to".$SpaceName));
    }














    //If LockDown Status is "Light"














    //If LockDown Status is "Open"









































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