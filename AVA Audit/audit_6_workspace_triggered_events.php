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

    //Get Trigger Item's Space Info
    $GetTriggerApp = PodioApp::get($appID);
    $TriggerSpaceID = $GetTriggerApp->space_id;

    //Get Trigger Items Values
    $TriggerEvent = $item->fields['trigger-event']->values[0]['text'];
    $WorkspaceID = $item->fields['workspace-id-2']->values;
    $WorkspaceName = $item->fields['name']->values;
    $SpaceURL = $item->fields['url']->values[0]->embed->embed_id;
    $CreatedOn = $item->fields['date-created']->start;
    $LastActivity = $item->fields['last-activity']->start;


    //Get Apps by TriggerSpace ID
    $SpaceApps = PodioApp::get_for_space($TriggerSpaceID);
    foreach($SpaceApps as $appname){
        $UsersAppName = $appname->config['name'];
        if($UsersAppName == "Users"){
            $UsersAppID = $appname->app_id;
        }
        if($UsersAppName == "Apps"){
            $ClientAppsAppID = $appname->app_id;
        }
    }




    //Update Workspace Info/////////////////////////////////////////////////////////////////////////////////////////////
    if($TriggerEvent == "Update Workspace Item Info"){

        //Update Workspace Item Fields Array
        $UpdateSpaceItemArray = array('fields'=>array());

        //Get Space and Values
        $Space = PodioSpace::get($WorkspaceID);
        $SpacePrivacy = $Space->privacy;
        $AutoJoin = $Space->auto_join;
        $URL = $Space->url;
        $URLLabel = $Space->url_label;
        $PostOnNewApp = $Space->post_on_new_app;
        $PostOnNewMember = $Space->post_on_new_member;
        $CreatedByName = $Space->created_by->name;
        $CreatedByUser = $Space->created_by->user_id;
        $CreatedOnDate = $Space->created_on;
        $spaceName = $Space->name;



        //Convert Numeric Values
        if ($SpacePrivacy) {$UpdateSpaceItemArray['fields']['privacy'] = ucfirst($SpacePrivacy);}

        if ($AutoJoin == 1) {$UpdateSpaceItemArray['fields']['auto-join'] = 'True';}
        else{$UpdateSpaceItemArray['fields']['auto-join'] = 'False';}

        if ($PostOnNewApp == 1) {$UpdateSpaceItemArray['fields']['post-on-new-app'] = 'True';}
        else{$UpdateSpaceItemArray['fields']['post-on-new-app'] = 'False';}

        if ($PostOnNewMember == 1) {$UpdateSpaceItemArray['fields']['post-on-new-member'] = 'True';}
        else{$UpdateSpaceItemArray['fields']['post-on-new-member'] = 'False';}


        //Add Values to Array
        if($URLLabel){$UpdateSpaceItemArray['fields']['url-label'] = $URLLabel;}
        //if Date Created on trigger item is BLANK
        if(!$CreatedOn){
            //Format Date Created
            $CreatedOnStamp = new DateTime((string)$CreatedOnDate, new DateTimeZone('America/Denver'));
            $CreatedOnDateFormatted = $CreatedOnStamp->format('Y-m-d H:i:s');
            //Add Date Created to Fields Array
            $UpdateSpaceItemArray['fields']['date-created'] = array('start'=>$CreatedOnDateFormatted);
        }
        //if No URL on Trigger Item
        if(!$SpaceURL){
            $CreateEmbedFile = PodioEmbed::create(array('url' => $URL));
            $LinkEmbedID = $CreateEmbedFile->embed_id;
            //Add Link to Fields Array
            $UpdateSpaceItemArray['fields']['url'] = $LinkEmbedID;
        }
        //if NO Name on Trigger Item
        if(!$WorkspaceName){$UpdateSpaceItemArray['fields']['name'] = $spaceName;}
        //Get User ProfileID by User ID
        if($CreatedByName){
            if($CreatedByUser) {
                $UpdateSpaceItemArray['fields']['created-by'] = (int)$CreatedByUser;
            }
        }



        //Update Trigger Item
        $UpdateTriggerItem = PodioItem::update($itemID, $UpdateSpaceItemArray);

    }//END FUNCTION






    //Delete Workspace//////////////////////////////////////////////////////////////////////////////////////////////////
    if($TriggerEvent == "Delete Workspace") {
        $DeleteSpace = PodioSpace::delete($WorkspaceID);

    }//END FUNCTION





    //Update Workspace//////////////////////////////////////////////////////////////////////////////////////////////////
    if($TriggerEvent == "Update Workspace") {

        //Get Trigger Item Values
        $SpacePrivacy = $item->fields['privacy']->values[0]['text'];
        $AutoJoin = $item->fields['auto-join']->values[0]['text'];
        $PostOnNewApp = $item->fields['post-on-new-app']->values[0]['text'];
        $PostOnNewMember = $item->fields['post-on-new-member']->values[0]['text'];
        $URLLabel = $item->fields['url-label']->values;

        //Create Update Space Array
        $UpdateSpaceArray = array();

        //Add Values to Array
        if($WorkspaceName){$UpdateSpaceArray['name'] = $WorkspaceName;}
        if($SpacePrivacy){$UpdateSpaceArray['privacy'] = $SpacePrivacy;}
        if($AutoJoin){$UpdateSpaceArray['auto_join'] = $AutoJoin;}
        if($URLLabel){$UpdateSpaceArray['url_label'] = $URLLabel;}
        if($PostOnNewApp){$UpdateSpaceArray['post_on_new_app'] = $PostOnNewApp;}
        if($PostOnNewMember){$UpdateSpaceArray['post_on_new_member'] = $PostOnNewMember;}


        //Update Space Settings
        $UpdateSpace = PodioSpace::update($WorkspaceID, $UpdateSpaceArray);

    }//END FUNCTION/////////////////////////////////////////////////////////////////////////////////////////////////////





    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //Create / Update Related App Items
    if($TriggerEvent == "Create / Update Related App Items") {

        //Create New App Item Fields Array
        $NewAppItemFieldsArray = array('fields' => array('workspace' => (int)$itemID));

        //Get Apps by Space
        $WorkspaceAPPs = PodioApp::get_for_space($WorkspaceID);


        //For each Returned App, Create App Item
        foreach ($WorkspaceAPPs as $apps) {
            $AppID = $apps->app_id;
            $FilterAppItems = PodioItem::filter($ClientAppsAppID, array('filters' => array('app-id' => (string)$AppID)));
            $ExistingAppItemID = $FilterAppItems[0]->item_id;
            if (!$ExistingAppItemID) {


                //Get App with App ID
                $APP = PodioApp::get((int)$AppID);

                //Get App Info
                $AppName = $APP->config['name'];
                $ItemName = $APP->config['item_name'];
                $AppToken = $APP->token;
                $AppStatus = $APP->status;
                $AppType = $APP->config['type'];
                $Description = $APP->config['description'];
                $AppLink = $APP->link;
                $DefaultView = $APP->config['default_view'];
                $OwnerProfileID = $APP->owner['profile_id'];
                $Original = $APP->original;
                $IconIMG = $APP->config['icon'];
                $AppIconID = $APP->config['icon_id'];
                $Usage = $APP->config['usage'];
                $Mailbox = $APP->mailbox;
                $Fields = $APP->fields;
                $NumberOfFields = count($Fields);

                //Get Permissions
                $AllowEdit = $APP->config['allow_edit'];
                $AllowComments = $APP->config['allow_comments'];
                $AllowAttachments = $APP->config['allow_attachments'];
                $SilentCreates = $APP->config['silent_creates'];
                $SilentEdits = $APP->config['silent_edits'];





                //Create Embed URL
                if ($AppLink) {
                    $CreateEmbedFile = PodioEmbed::create(array('url' => $AppLink));
                    $LinkEmbedID = $CreateEmbedFile->embed_id;
                }


                //Add Values to Fields Array
                if ($AppName) {
                    $NewAppItemFieldsArray['fields']['title'] = $AppName;
                }
                if ($AppID) {
                    $NewAppItemFieldsArray['fields']['app-id'] = (string)$AppID;
                }
                if ($ItemName) {
                    $NewAppItemFieldsArray['fields']['item-name'] = $ItemName;
                }
                if ($AppStatus) {
                    $NewAppItemFieldsArray['fields']['status'] = ucfirst($AppStatus);
                }
                if ($AppType) {
                    $NewAppItemFieldsArray['fields']['type'] = ucfirst($AppType);
                }
                if ($DefaultView) {
                    $NewAppItemFieldsArray['fields']['default-view-type'] = ucfirst($DefaultView);
                }
                if ($Description) {
                    $NewAppItemFieldsArray['fields']['description'] = $Description;
                }
                if ($Usage) {
                    $NewAppItemFieldsArray['fields']['usage'] = $Usage;
                }
                if ($NumberOfFields) {
                    $NewAppItemFieldsArray['fields']['of-fields'] = (string)$NumberOfFields;
                }
                if ($Mailbox) {
                    $NewAppItemFieldsArray['fields']['mailbox'] = $Mailbox;
                }
                if ($AppToken) {
                    $NewAppItemFieldsArray['fields']['app-token'] = $AppToken;
                }
                if ($LinkEmbedID) {
                    $NewAppItemFieldsArray['fields']['link'] = $LinkEmbedID;
                }
                if ($IconIMG) {
                    $NewAppItemFieldsArray['fields']['icon-name'] = $IconIMG;
                }
                if ($OwnerProfileID) {
                    $NewAppItemFieldsArray['fields']['owner'] = $OwnerProfileID;
                }
                if ($Original) {
                    $NewAppItemFieldsArray['fields']['origional'] = (string)$Original;
                }



                //Permissions
                if ($AllowEdit == 1) {
                    $NewAppItemFieldsArray['fields']['allow-edit'] = 'True';
                } else {
                    $NewAppItemFieldsArray['fields']['allow-edit'] = 'False';
                }

                if ($AllowComments == 1) {
                    $NewAppItemFieldsArray['fields']['allow-comments'] = 'True';
                } else {
                    $NewAppItemFieldsArray['fields']['allow-comments'] = 'False';
                }

                if ($AllowAttachments == 1) {
                    $NewAppItemFieldsArray['fields']['allow-attachments'] = 'True';
                } else {
                    $NewAppItemFieldsArray['fields']['allow-attachments'] = 'False';
                }

                if ($SilentCreates == 1) {
                    $NewAppItemFieldsArray['fields']['silent-creates'] = 'True';
                } else {
                    $NewAppItemFieldsArray['fields']['silent-creates'] = 'False';
                }

                if ($SilentEdits == 1) {
                    $NewAppItemFieldsArray['fields']['silent-edits'] = 'True';
                } else {
                    $NewAppItemFieldsArray['fields']['silent-edits'] = 'False';
                }


                //Create APP Item
                $CreateAppItem = PodioItem::create($ClientAppsAppID, $NewAppItemFieldsArray);
            }
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    if($TriggerEvent == "Create / Update User Items"){
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
            $FilterUsers = PodioItem::filter($UsersAppID, array('filters' => array('user-name' =>$MemName)));
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
                $CreateUserItem = PodioItem::create($UsersAppID, array(
                    'fields' => array(
                        'user-name' => (string)$MemName,
                        'contact'=>(int)$MemProfileID,
                        'email' => array('type' => 'work', 'value' => (string)$MemEmail),
                        //'image' => $ImageEmbedID,
                        'profile-id-2' => (string)$MemProfileID,
                        'user-id-2' => (string)$MemUserID,
                        $SpaceEXfieldID => (int)$itemID,
                    )),
                    array('hooks'=>'false')
                );
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
    }


    ///Update Trigger Value
    $UpdateTrigger = PodioItem::update($itemID, array(
        'fields'=>array(
            'trigger-event'=>"Done"
        )),
        array('hook'=>false)
    );








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