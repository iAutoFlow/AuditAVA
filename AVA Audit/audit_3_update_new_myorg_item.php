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

    //Get Trigger Item
    $item = PodioItem::get($itemID);
    $appName = $item->app->name;
    $appID = $item->app->app_id;

    //Get Trigger ORG Item Values
    $OrgID = $item->fields['podio-org-id']->values;

    //Get Org
    $Org = PodioOrganization::get($OrgID);

    //Get Values
    $NameofOrg = $Org->name;
    $created_on = $Org->created_on;
    $status = $Org->status;
    $Plan = $Org->type;
    $user_limit = $Org->user_limit;
    $domains = $Org->domains;
    $OrgURL = $Org->url;
    $logoID = $Org->logo;


    //Format Date Created
    $dateStamp = new DateTime((string)$created_on, new DateTimeZone('America/Denver'));
    $DateFormatted = $dateStamp->format('Y-m-d H:i:s');


    //Save Org LOGO and Create Embed URL
    $ImageFile = PodioFile::get((int)$logoID);
    $ImageID = $ImageFile->file_id;
    
    //Copy Image File
    $CopyFile = PodioFile::copy($ImageID);
    $NewImageFileID = $CopyFile->file_id;


    //Create Fields Array
    $FieldsArray = array(
        'fields' => array(),
        array('hook' => false));



    //Add Values to Fields Array
    if ($NameofOrg) {$FieldsArray['fields']['title'] = $NameofOrg;}
    if ($created_on) {$FieldsArray['fields']['date-created'] = array('start' => $DateFormatted);}
    if ($status) {$FieldsArray['fields']['status'] = ucwords($status);}
    if ($Plan) {$FieldsArray['fields']['plan'] = ucwords($Plan);}
    if ($user_limit) {$FieldsArray['fields']['user-limit'] = $user_limit;}
    if ($domains) {$FieldsArray['fields']['domains'] = $domains;}


    if($NewImageFileID){$FieldsArray['fields']['image'] = $NewImageFileID;}

    if ($OrgURL) {
        $CreateEmbedFile = PodioEmbed::create(array('url' => $OrgURL));
        $LinkEmbedID = $CreateEmbedFile->embed_id;
        $FieldsArray['fields']['url'] = $LinkEmbedID;
    }


    //Update my Org Trigger Item
    $UpdateTriggerItem = PodioItem::update($itemID, $FieldsArray);


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