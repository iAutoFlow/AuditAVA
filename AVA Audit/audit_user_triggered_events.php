<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 9/12/2016
 * Time: 1:32 PM
 */

$Curl = new\Curl\Curl();
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

    $item = PodioItem::get($itemID);
    $appName = $item->app->name;
    $appID = $item->app->app_id;

    //Get Trigger Item's Space Info
    $GetTriggerApp = PodioApp::get($appID);
    $TriggerSpaceID = $GetTriggerApp->space_id;

    //Get Trigger Items Values
    $TriggerEvent = $item->fields['trigger-event']->values[0]['text'];

    $OrgID = 786333;



    //Update Workspace Info/////////////////////////////////////////////////////////////////////////////////////////////
    if($TriggerEvent == "End Organization Membership") {

        $UserID = $item->fields['user-id-2']->values;
        $EndUsersMembership = PodioOrganizationMember::delete($OrgID ,$UserID);

    }

    ///Update Trigger Value
//    $UpdateTrigger = PodioItem::update($itemID, array(
//        'fields'=>array(
//            'trigger-event'=>"Done"
//        )),
//        array('hook'=>false)
//    );








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