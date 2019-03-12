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

    $item = PodioItem::get($itemID);
    $appName = $item->app->name;
    $appID = $item->app->app_id;

    //Get Workspace AppID and Space ID
    $GetTriggerApp = PodioApp::get($appID);
    $SpaceID = $GetTriggerApp->space_id;
    $GetSpace = PodioApp::get_for_space($SpaceID);
    foreach($GetSpace as $app){
        $AppName = $app->config['name'];
        if($AppName == "Workspaces"){
            $SpacesAppID = $app->app_id;
        }
    }


    //Get Org & Workspace Info
    $OrgID = $item->fields['podio-org-id']->values;

    //Get All Workspaces for Org
    $AllWorkSpaces = PodioSpace::get_for_org($OrgID);

    //Create Workspace Item for each Workspace
    foreach($AllWorkSpaces as $space) {
        $WorkspaceID = $space->space_id;
        $WorkspaceName = $space->name;
        $WorkspaceURL = $space->url;
        $WorkspaceCreatedOn = $space->created_on;
        $MemberCount = $space->member_count;
        $LastActivity = $space->last_activity_on;

        //Format Date Created
        $CreatedOnStamp = new DateTime((string)$WorkspaceCreatedOn, new DateTimeZone('America/Denver'));
        $CreatedOnDateFormatted = $CreatedOnStamp->format('Y-m-d H:i:s');
        //Format Last Activity
        $LastActivityStamp = new DateTime((string)$LastActivity, new DateTimeZone('America/Denver'));
        $LastActivityDateFormatted = $LastActivityStamp->format('Y-m-d H:i:s');


        //Create Array For new Workspace Item
        $NewWorkspaceItemArray = array(
            'fields'=>array(
                'workspace-id-2' => (string)$WorkspaceID,
                'date-created' => array('start' => $CreatedOnDateFormatted),
                'last-activity' => array('start' => $LastActivityDateFormatted),
                'status' => 'Active',
                'organization' => (int)$itemID,
            ));


        //Create Embed Link with Space URL
        if($WorkspaceURL) {
            $CreateEmbedFile = PodioEmbed::create(array('url' => $WorkspaceURL));
            $LinkEmbedID = $CreateEmbedFile->embed_id;

            //Add Link to Fields Array
            $NewWorkspaceItemArray['fields']['url'] = $LinkEmbedID;
        }


        //Add Space Name to Array
        if($WorkspaceName){$NewWorkspaceItemArray['fields']['name'] = $WorkspaceName;}


        //Create Workspace Item in Audit Security Space
        $CreateSpaceItem = PodioItem::create($SpacesAppID, $NewWorkspaceItemArray);
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