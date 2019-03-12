<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 7/14/2016
 * Time: 4:29 PM
 */
//O-AUTH

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


//When a new Workspace Item is created in the Client's Audit Space Do this Script

try {
    Podio::setup(PodioSessionManager::getClientId(), PodioSessionManager::getClientSecret(), array( //client id and secret from connection service config
        "session_manager" => "PodioSessionManager"
    ));


    //Get Trigger Item
    $requestParams = $event['request']['parameters'];
    $itemID = $requestParams['item_id'];
    $item = PodioItem::get($itemID);
    $appID = $item->app->app_id;

    //Get Trigger App
    $GetApp = PodioApp::get($appID);
    $SpaceID = $GetApp->space_id;

    //Get Apps by TriggerSpace ID
    $SpaceApps = PodioApp::get_for_space((int)$SpaceID);
    foreach($SpaceApps as $appname){
        $ClientAppName = $appname->config['name'];
        if($ClientAppName == "Apps"){
            $ClientAppsAppID = $appname->app_id;
        }
    }


    //Get Trigger Workspace Item Info
    $WorkspaceID = $item->fields['workspace-id-2']->values;
    $LockDownStatus = $item->fields['status']->values[0]['text'];

    //Create New App Item Fields Array
    $NewAppItemFieldsArray = array('fields'=>array('workspace'=>(int)$itemID));

    //Get Apps by Space
    $WorkspaceAPPs = PodioApp::get_for_space($WorkspaceID);


    //For each Returned App, Create App Item
    foreach($WorkspaceAPPs as $apps){
        $AppID = $apps->app_id;
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
        $Tasks = $APP->config['tasks'];
        $Fields = $APP->fields;
        $NumberOfFields = count($Fields);

        //Get Permissions
        $AllowEdit = $APP->config['allow_edit'];
        $AllowComments = $APP->config['allow_comments'];
        $AllowAttachments = $APP->config['allow_attachments'];
        $DisableNotifications = $APP->config['disable_notifications'];
        $SilentCreates = $APP->config['silent_creates'];
        $SilentEdits = $APP->config['silent_edits'];
        $SilentComments = $APP->config['silent_comments'];


        //Get Voting
        $Thumbs = $APP->config['thumbs'];
        $ThumbsLabel = $APP->config['thumbs_label'];
        $Approved = $APP->config['approved'];
        $YESNO = $APP->config['yesno'];
        $YESNOLabel = $APP->config['yesno_label'];
        $FiveStar = $APP->config['fivestar'];
        $FiveStarLabel = $APP->config['fivestar_label'];
        $RSVP = $APP->config['rsvp'];
        $RSVPLabel = $APP->config['rsvp_label'];

        print_r($AppLink);
        exit;


        //Create Embed URL
        if ($AppLink) {
            $CreateEmbedFile = PodioEmbed::create(array('url' => $AppLink));
            $LinkEmbedID = $CreateEmbedFile->embed_id;
        }


        //Add Values to Fields Array
        if ($AppName) {$NewAppItemFieldsArray['fields']['title'] = $AppName;}
        if ($AppID) {$NewAppItemFieldsArray['fields']['app-id'] = (string)$AppID;}
        if ($ItemName) {$NewAppItemFieldsArray['fields']['item-name'] = $ItemName;}
        if ($AppStatus) {$NewAppItemFieldsArray['fields']['status'] = ucfirst($AppStatus);}
        if ($AppType) {$NewAppItemFieldsArray['fields']['type'] = ucfirst($AppType);}
        if ($DefaultView) {$NewAppItemFieldsArray['fields']['default-view-type'] = ucfirst($DefaultView);}
        if ($Description) {$NewAppItemFieldsArray['fields']['description'] = $Description;}
        if ($Usage) {$NewAppItemFieldsArray['fields']['usage'] = $Usage;}
        if ($NumberOfFields) {$NewAppItemFieldsArray['fields']['of-fields'] = (string)$NumberOfFields;}
        if ($Mailbox) {$NewAppItemFieldsArray['fields']['mailbox'] = $Mailbox;}
        if ($AppToken) {$NewAppItemFieldsArray['fields']['app-token'] = $AppToken;}
        if ($LinkEmbedID) {$NewAppItemFieldsArray['fields']['link'] = $LinkEmbedID;}
        if ($IconIMG) {$NewAppItemFieldsArray['fields']['icon-id'] = $IconIMG;}
        if ($OwnerProfileID) {$NewAppItemFieldsArray['fields']['owner'] = $OwnerProfileID;}
        if ($Original) {$NewAppItemFieldsArray['fields']['origional'] = (string)$Original;}
        if ($Tasks) {$NewAppItemFieldsArray['fields']['tasks'] = (string)$Tasks;}


        //Permissions
        if ($AllowEdit == 1) {$NewAppItemFieldsArray['fields']['allow-edit'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['allow-edit'] = 'False';}

        if ($AllowComments == 1) {$NewAppItemFieldsArray['fields']['allow-comments'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['allow-comments'] = 'False';}

        if ($AllowAttachments == 1) {$NewAppItemFieldsArray['fields']['allow-attachments'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['allow-attachments'] = 'False';}

        if ($DisableNotifications == 1) {$NewAppItemFieldsArray['fields']['disable-notifications'] ='True';}
        else{$NewAppItemFieldsArray['fields']['disable-notifications'] = 'False';}

        if ($SilentCreates == 1) {$NewAppItemFieldsArray['fields']['silent-creates'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['silent-creates'] = 'False';}

        if ($SilentEdits == 1) {$NewAppItemFieldsArray['fields']['silent-edits'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['silent-edits'] = 'False';}

        if ($SilentComments == 1) {$NewAppItemFieldsArray['fields']['silent-comments'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['silent-comments'] = 'False';}



        //Votings
        if ($Approved == 1) {$NewAppItemFieldsArray['fields']['approved'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['approved'] = 'False';}

        if ($Thumbs == 1) {$NewAppItemFieldsArray['fields']['thumbs'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['thumbs'] = 'False';}
        if ($ThumbsLabel) {$NewAppItemFieldsArray['fields']['thumbs-label'] = $ThumbsLabel;}

        if ($YESNO == 1) {$NewAppItemFieldsArray['fields']['yes-no'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['yes-no'] = 'False';}
        if ($YESNOLabel) {$NewAppItemFieldsArray['fields']['yes-no-label'] = $YESNOLabel;}

        if ($FiveStar == 1) {$NewAppItemFieldsArray['fields']['five-star'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['five-star'] = 'False';}
        if ($FiveStarLabel) {$NewAppItemFieldsArray['fields']['five-star-label'] = $FiveStarLabel;}

        if ($RSVP == 1) {$NewAppItemFieldsArray['fields']['rsvp'] = 'True';}
        else{$NewAppItemFieldsArray['fields']['rsvp'] = 'False';}
        if ($RSVPLabel) {$NewAppItemFieldsArray['fields']['rsvp-label'] = $RSVPLabel;}



        //Create APP Item
        $CreateAppItem = PodioItem::create($ClientAppsAppID, $NewAppItemFieldsArray);


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




