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
    

    //Get Users & Workpaces AppID and Space ID
    $GetTriggerApp = PodioApp::get($appID);
    $SpaceID = $GetTriggerApp->space_id;
    $GetSpace = PodioApp::get_for_space($SpaceID);
    foreach($GetSpace as $app){
        $AppName = $app->config['name'];
        if($AppName == "Users"){
            $UserAppID = $app->app_id;
        }
        if($AppName == "Workspaces"){
            $WorkspaceAppID = $app->app_id;
        }
    }

    
    $WorkspaceID = $item->fields['workspace-id-2']->values;


    //Get Workspace Members
    $WorkpaceMembers = PodioSpaceMember::get_all($WorkspaceID);


    //For each Member in Workspace
    foreach ($WorkpaceMembers as $member) {
        $MemUserID = $member->profile->user_id;
        $MemName = $member->profile->name;
        $MemProfileID = $member->profile->profile_id;
        $MemImage = $member->profile->image->link;
        $MemEmail = $member->user->mail;
        $MemRole = $member->role;



        //Filter User Items for Existing
        $FilterUsers = PodioItem::filter($UserAppID, array('filters' => array('user-name' =>$MemName)));
        $ExistingUserItemID = $FilterUsers[0]->item_id;

        //Set User Role
        if($MemRole == "admin"){$SpaceEXfieldID = "space-access-admin";}
        if($MemRole == "regular"){$SpaceEXfieldID = "space-access-regular";}
        if($MemRole == "light"){$SpaceEXfieldID = "space-access-light";}
        if ($MemImage) {
            $CreateEmbed = PodioEmbed::create(array('url' => $MemImage));
            $ImageEmbedID = $CreateEmbed->files->file_id;
        }




        //Create User Item if Does not Exist
        if(!$ExistingUserItemID) {
            $CreateUserItem = PodioItem::create($UserAppID, array(
                'fields' => array(
                    'user-name' => (string)$MemName,
                    'contact'=>(int)$MemProfileID,
                    'email' => array('type' => 'work', 'value' => (string)$MemEmail),
                    //'image' => $ImageEmbedID,
                    'profile-id-2' => (string)$MemProfileID,
                    'user-id-2' => (string)$MemUserID,
                    $SpaceEXfieldID => (int)$itemID,
                ),
                'hooks'=>'false',
            ));
        }

        else {
            $IncludedSpaceArray = array();
            $UserItem = PodioItem::get($ExistingUserItemID);

            if($MemRole == "admin"){$Related = $UserItem->fields['space-access-admin']->values;
                foreach($Related as $workspace){
                    $WorkspaceItemID = $workspace->item_id;
                    array_push($IncludedSpaceArray, (int)$WorkspaceItemID);
                }
            }
            if($MemRole == "regular"){$Related = $UserItem->fields['space-access-regular']->values;
                foreach($Related as $workspace){
                    $WorkspaceItemID = $workspace->item_id;
                    array_push($IncludedSpaceArray, (int)$WorkspaceItemID);
                }
            }
            if($MemRole == "light"){$Related = $UserItem->fields['space-access-light']->values;
                foreach($Related as $workspace){
                    $WorkspaceItemID = $workspace->item_id;
                    array_push($IncludedSpaceArray, (int)$WorkspaceItemID);
                }
            }

            array_push($IncludedSpaceArray, (int)$itemID);

            $UpdateExistingUser = PodioItem::update($ExistingUserItemID, array(
                'fields' => array(
                    $SpaceEXfieldID => $IncludedSpaceArray
                ),
                'hooks'=>false
            ));
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