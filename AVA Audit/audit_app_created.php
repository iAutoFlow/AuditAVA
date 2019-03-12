<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 10/21/2016
 * Time: 2:04 PM
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

    //Set Current Date and Time
    $todaysDate = date_create("now");
    $dateTimeStamp = new DateTime((string)$todaysDate, new DateTimeZone('America/Denver'));
    $FormatTimeStamp = $dateTimeStamp->format("Y-m-d H:i:s");

    $requestParams = $event['request']['parameters'];
    $appID = $requestParams['app_id'];

    //Get App
    $NewApp = PodioApp::get($appID);
    $NewAppName = $NewApp->config['name'];
    $AppSpaceID = $NewApp->space_id;
    $AppStatus = $NewApp->status;
    $ItemName = $NewApp->config['item_name'];
    $AppDescription = $NewApp->config['description'];
    $Usage = $NewApp->config['usage'];
    $AppExternalID = $NewApp->config['external_id'];
    $AppType = $NewApp->config['type'];
    $DefaultView = $NewApp->config['default_view'];
    $Fields = $NewApp->fields;
    $NumberOfFields = count($Fields);
    $IconName = $NewApp->config['icon'];
    $IconImageID = $NewApp->config['icon_id'];
    $NewAppLink = $NewApp->link;
    $OwnerProfileID = $NewApp->owner->profile_id;
    $AppOwnerUserID = $NewApp->owner->user_id;
    $AppOwnerName = $NewApp->owner->name;
    $AppOrigin = $NewApp->original;
    $AppOriginRevision = $NewApp->original_revision;
    $CurrentRevision = $NewApp->current_revision;
    $AppMailBox = $NewApp->mailbox;
    $AppToken = $NewApp->token;
    $AllowEdit = $NewApp->config['allow_edit'];
    $AllowComments = $NewApp->config['allow_comments'];
    $SilentCreates = $NewApp->config['silent_creates'];
    $SilentEdits = $NewApp->config['silent_edits'];




    //Get Space & ORG ID's
    $TriggerSpace = PodioSpace::get($AppSpaceID);
    $SpaceName = $TriggerSpace->name;
    $AppORDID = $TriggerSpace->org_id;

    //Filter Customers App in Audit DB to get Customers Apps App ID
    $FilterAuditCustomers = PodioItem::filter(15229543, array('filters'=>array('organization-id'=>(string)$AppORDID)));
    $AuditCustomerItemID = $FilterAuditCustomers[0]->item_id;
    if($AuditCustomerItemID){
        $CustomerItem = PodioItem::get($AuditCustomerItemID);
        $myORGAppID = $CustomerItem->fields['my-org-app-id']->value;
        $WorkspaceAppID = $CustomerItem->fields['workspaces-app-id']->value;
        $AppsAppID = $CustomerItem->fields['apps-app-id']->values;
        $UsersAppID = $CustomerItem->fields['users-app-id']->value;
        $ActionLogsAppID = $CustomerItem->fields['action-logs-app-id']->value;
    }


    //Create Embed URL
    if ($NewAppLink) {
        $CreateEmbedFile = PodioEmbed::create(array('url' => $NewAppLink));
        $LinkEmbedID = $CreateEmbedFile->embed_id;
    }


    //Add Values to Fields Array
    if ($NewAppName) {$NewAppItemFieldsArray['fields']['title'] = $NewAppName;}
    if ($AppSpaceID) {$NewAppItemFieldsArray['fields']['workspace'] = $AppSpaceID;}
    if ($AppStatus) {$NewAppItemFieldsArray['fields']['status'] = ucfirst($AppStatus);}
    if ($AppID) {$NewAppItemFieldsArray['fields']['app-id'] = (string)$AppID;}
    if ($ItemName) {$NewAppItemFieldsArray['fields']['item-name'] = $ItemName;}
    if ($AppDescription) {$NewAppItemFieldsArray['fields']['description'] = $AppDescription;}
    if ($Usage) {$NewAppItemFieldsArray['fields']['usage'] = $Usage;}
    if ($AppExternalID) {$NewAppItemFieldsArray['fields']['app-external-id'] = $AppExternalID;}
    if ($AppType) {$NewAppItemFieldsArray['fields']['type'] = ucfirst($AppType);}
    if ($DefaultView) {$NewAppItemFieldsArray['fields']['default-view-type'] = ucfirst($DefaultView);}
    if ($NumberOfFields) {$NewAppItemFieldsArray['fields']['of-fields'] = (string)$NumberOfFields;}
    if ($IconName) {$NewAppItemFieldsArray['fields']['icon-name'] = $IconName;}
    if ($IconImageID) {$NewAppItemFieldsArray['fields']['icon'] = $IconImageID;}
    if ($LinkEmbedID) {$NewAppItemFieldsArray['fields']['link'] = $LinkEmbedID;}
    if ($OwnerProfileID) {$NewAppItemFieldsArray['fields']['owner'] = $OwnerProfileID;}
    if ($AppOrigin) {$NewAppItemFieldsArray['fields']['origional'] = (string)$AppOrigin;}
    if ($AppOriginRevision) {$NewAppItemFieldsArray['fields']['original-revision'] = (string)$AppOriginRevision;}
    if ($CurrentRevision) {$NewAppItemFieldsArray['fields']['current-revision-2'] = (string)$CurrentRevision;}
    if ($AppMailBox) {$NewAppItemFieldsArray['fields']['mailbox'] = $AppMailBox;}
    if ($AppToken) {$NewAppItemFieldsArray['fields']['app-token'] = $AppToken;}

    //Permissions
    if ($AllowEdit == 1) {$NewAppItemFieldsArray['fields']['allow-edit'] = 'True';}
    else {$NewAppItemFieldsArray['fields']['allow-edit'] = 'False';}

    if ($AllowComments == 1) {$NewAppItemFieldsArray['fields']['allow-comments'] = 'True';}
    else {$NewAppItemFieldsArray['fields']['allow-comments'] = 'False';}

    if ($SilentEdits == 1) {$NewAppItemFieldsArray['fields']['silent-edits'] = 'True';}
    else {$NewAppItemFieldsArray['fields']['silent-edits'] = 'False';}

    if ($SilentCreates == 1) {$NewAppItemFieldsArray['fields']['silent-creates'] = 'True';}
    else {$NewAppItemFieldsArray['fields']['silent-creates'] = 'False';}




    //Filter Customers Users App for Created By User ITem ID
   // $FilterUsersApp = PodioItem::filter($UsersAppID, array('filters'=>array('user-id-2'=>$AppOwnerUserID)));
    //$CreatedByUserItemID = $FilterUsersApp[0]->item_id;

    //Filter Customers Spaces App for Related Space Item ID
    $FilterSpacesApp = PodioItem::filter($WorkspaceAppID, array('filters'=>array('workspace-id-2'=>(string)$AppSpaceID)));
    $RelatedSpaceItemID = $FilterSpacesApp[0]->item_id;






    //Create APP Item
    if($AppsAppID) {
        $CreateAppItem = PodioItem::create($AppsAppID, $NewAppItemFieldsArray);
        $NewAppItemID = $CreateAppItem->item_id;
    }




    //Assemble Action Log Item
    $ActionLogFieldsArray = array(
        'fields'=>array(
            'time-stamp' => $FormatTimeStamp,
            'summary-of-action' => "A new app named ".$NewAppName." was created in ".$SpaceName." by ".$AppOwnerName.".",
            'type' => "App",
            'action' => 'app.create',
        ));

    //Add Values to Action Log Fields Array
    if($CreatedByUserItemID){$ActionLogFieldsArray['fields']['related-user'] = $CreatedByUserItemID;}
    if($RelatedSpaceItemID){$ActionLogFieldsArray['fields']['workspace'] = $RelatedSpaceItemID;}
    if($NewAppItemID){$ActionLogFieldsArray['fields']['app'] = $NewAppItemID;}
    if($CurrentRevision){$ActionLogFieldsArray['fields']['revision-id'] = $CurrentRevision;}
    $CreateActionLogItem = PodioItem::create($ActionLogsAppID, $ActionLogFieldsArray);

























    return [
        'success' => true,
        'result' => $ActionLogFieldsArray,
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