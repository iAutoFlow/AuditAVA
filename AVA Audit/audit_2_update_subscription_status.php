<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 9/20/2016
 * Time: 11:51 AM
 */

date_default_timezone_set('America/Denver');
$Curl = new\Curl\Curl();
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
    $itemID = $requestParams['item_id'];

    $item = PodioItem::get($itemID);
    $appName = $item->app->name;
    $appID = $item->app->app_id;

    //Set Know Variables
    $AuditExtensionItemID = 483906135;
    $AuditPlanItemID = 483919569;


    //Get Subscription Values
    $CustomerItemID = $item->fields['customer']->values[0]->item_id;
    $SubscriptionPlanItemID = $item->fields['plan']->values[0]->item_id;
    $SubscriptionStatus = $item->fields['status']->values[0]['text'];

    //Format Current Date/Time
    $todaysDate = date("Y-m-d H:i:s", strtotime("now"));
    $dateStamp = new DateTime((string)$todaysDate, new DateTimeZone('America/Denver'));
    $DateFormatted = $dateStamp->format('Y-m-d H:i:s');



    //If Subscription Is not For Audit, END
    if($SubscriptionPlanItemID !== $AuditPlanItemID || $SubscriptionStatus == "Active"){
        exit;
    }


    //Get Related Customer Item
    $CustomerItem = PodioItem::get($CustomerItemID);
    $CompanyName = $CustomerItem->fields['company-name']->values;
    $CustomerOrgID = $CustomerItem->fields['organization-id']->values;
    $CustomerUserID = $CustomerItem->fields['text-3']->values;
    $CustomerProfileID = $CustomerItem->fields['profile-id']->values;
    $HoistConnectionID = $CustomerItem->fields['hoist-connection-id']->values;
    $AuditSpaceID = $CustomerItem->fields['extension-space-id']->values;



    //If Status == "Canceled"
    if($SubscriptionStatus == "Canceled" ||$SubscriptionStatus == "Expired"){
        //Delete Audit Space
        $DeleteClientsAuditSpace = PodioSpace::delete($AuditSpaceID);
        //Update Subscription Trigger Item
        $AddComment = PodioComment::create('item', $itemID, array('value'=>"The Audit Workspace for".$CompanyName."has been deleted."));
        $UpdateSubscriptionItem = PodioItem::update($itemID, array('fields'=>array("end-date-2"=>array('start'=>$DateFormatted))));

        //Update Customer Item
        $UpdateCustomer = PodioItem::update($CustomerItemID, array('fields'=>array('extension-space-id'=>[])));
    }


    //If Status == "Past Due"
    if($SubscriptionStatus == "Past Due") {
        //Add new Status to Clients Audit Workspace. @Mention the Org Admin.
        $CreateWorkspaceStatus = PodioStatus::create($AuditSpaceID, array("value"=>"https://podio.com/users/".$CustomerUserID.", The payment for your Audit Subscription is past due.
        Please reach out to your personal assistant AVA if you have any questions about how to utilize the functionality available with this workspace.
        If the payment is not made, the service will be discontinued."));
    }


    return [
        'success' => true,
        'result' => $SubscriptionStatus,
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