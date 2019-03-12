<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 9/12/2016
 * Time: 1:32 PM
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


    //Get Trigger Item's Space Info
    $GetTriggerApp = PodioApp::get($appID);
    $SpaceID = $GetTriggerApp->space_id;

    //Get Trigger Item's Org ID
    $OrgID = $item->fields['podio-org-id']->values;
    $TriggerEvent = $item->fields['trigger-event']->values[0]['text'];

    //Set Default Trigger Value
    $TriggerValue = "...";



    //If Trigger Value == "Create Weekly Login Record"////////////////////////////////////////////////////////////////////////////////////////
    if($TriggerEvent == "Create Weekly Login Record") {

        //If Plan is Not Premium or Sponsored Create Comment
        $PlanValue = $item->fields['plan']->values[0]['text'];
        if($PlanValue == "Free" || $PlanValue == "Enterprise"){
            $AddComment = PodioComment::create('item',$itemID, array('value'=>"This Feature is only available for Premium or Enterprise Organizations"));
        }

        else {
            //Get Login Record AppID
            $GetSpace = PodioApp::get_for_space($SpaceID);
            foreach ($GetSpace as $app) {
                $AppName = $app->config['name'];
                if ($AppName == "Login Record") {
                    $LoginRecordAppID = $app->app_id;
                    continue;
                }
            }

            //Get Login Report
            $GetLoginReport = PodioOrganization::get_login_report($OrgID, array('limit' => 1));

            //Create Fields Array
            $FieldsArray = array();

            //Get Values from Login Report
            $Date = $GetLoginReport[0]['date'];
            $Total = $GetLoginReport[0]['total'];
            $Active = $GetLoginReport[0]['active'];

            //Format Date
            $dateTimeStamp = new DateTime((string)$Date, new DateTimeZone('America/Denver'));
            $FormatTimeStamp = $dateTimeStamp->format("Y-m-d H:i:s");

            //Create Login Report Item
            $CreateReportItem = PodioItem::create($LoginRecordAppID, array(
                'fields' => array(
                    'date' => array('start' => $FormatTimeStamp),
                    'active' => $Active,
                    'total' => $Total,
                    'org' => (int)$itemID,
                )
            ));
        }

        //Update Default Trigger Value
        $TriggerValue = "Done";

        //Update Trigger Item
        $UpdateTriggerItem = PodioItem::update($itemID, array(
            'fields' => array(
                'trigger-event' => $TriggerValue),
            array('hook' => false)
        ));
    }


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    //If Trigger Value == "Update my ORG Item Info"
    if($TriggerEvent == "Update my ORG Item Info") {

        //Get Users AppID
//        $GetSpace = PodioApp::get_for_space($SpaceID);
//        foreach ($GetSpace as $app) {
//            $AppName = $app->config['name'];
//            if ($AppName == "Users") {
//                $UsersAppID = $app->app_id;
//            }
//
//        }


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
//        $ImageFile = PodioFile::get((int)$logoID);
//        $ImageID = $ImageFile->file_id;
//
//        //Copy Image File
//        $CopyFile = PodioFile::copy($ImageID);
//        $NewImageFileID = $CopyFile->file_id;


        //Get Org Admins
//        $OrgAdmins = PodioOrganization::get_all_admins($OrgID);
//        $AdminIDsArray = array();
//
//        //Get Admins User ID and Add to AdminIDsArray
//        foreach ($OrgAdmins as $admin) {
//            $AdminUserID = $admin->profile_id;
//            array_push($AdminIDsArray, $AdminUserID);
//        }
//
//        $SizeofAdminArray = sizeof($AdminIDsArray);


        //Update Default Trigger Value
        $TriggerValue = "Done";


        //Create Fields Array
        $FieldsArray = array(
            'fields' => array(
                'trigger-event' => $TriggerValue),
            array('hook' => false)
        );


        //Add Values to Fields Array
        if ($NameofOrg) {$FieldsArray['fields']['title'] = $NameofOrg;}
        if ($created_on) {$FieldsArray['fields']['date-created'] = array('start' => $DateFormatted);}
        if ($status) {$FieldsArray['fields']['status'] = ucwords($status);}
        if ($Plan) {$FieldsArray['fields']['plan'] = ucwords($Plan);}
        if ($user_limit) {$FieldsArray['fields']['user-limit'] = (int)$user_limit;}
        if ($domains) {$FieldsArray['fields']['domains'] = $domains;}
       // if ($SizeofAdminArray > 0) {$FieldsArray['fields']['org-admins-2'] = $AdminIDsArray;}
        //if ($NewImageFileID) {$FieldsArray['fields']['image'] = $NewImageFileID;}
        if ($OrgURL) {
            $CreateEmbedFile = PodioEmbed::create(array('url' => $OrgURL));
            $LinkEmbedID = $CreateEmbedFile->embed_id;
            $FieldsArray['fields']['url'] = $LinkEmbedID;
        }

        //Update Trigger Item
        $UpdateTriggerItem = PodioItem::update($itemID, $FieldsArray);

    }


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //If Trigger Value == "Create Workspace Items for New Spaces"/////////////////////////////////////////////////////////////////
    if($TriggerEvent == 'Create / Update Workspace Items'){

        $GetSpace = PodioApp::get_for_space($SpaceID);
        foreach($GetSpace as $app){
            $AppName = $app->config['name'];
            if($AppName == "Workspaces"){
                $SpacesAppID = $app->app_id;
            }
        }


        //Hooks Array
        $HooksArray = array();


        //Get All Workspaces for Org
        $AllWorkSpaces = PodioSpace::get_for_org($OrgID);

        //Create Workspace Item for each Workspace
        foreach($AllWorkSpaces as $space) {
            $WorkspaceID = $space->space_id;

            //Filter for Existing Workspace Items
            $FilterWorkspaceApp = PodioItem::filter($SpacesAppID, array('filters' => array('workspace-id-2' => (string)$WorkspaceID)));
            $ExistingItemID = $FilterWorkspaceApp[0]->item_id;

            //If no Matching Space ID was found, Create New Workspace Item
            if (!$ExistingItemID) {


                //Create / Add Hooks for each Space Level Hook
                $HookURL1 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=member.add&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$WorkspaceID;
                $HookURL2 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=member.remove&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_member_removed='.$WorkspaceID;
                $HookURL3 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=app.create&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_app_created='.$WorkspaceID;
                $HookURL4 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=app.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$WorkspaceID;
                $HookURL5 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=app.delete&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$WorkspaceID;
                $HookURL6 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=space.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$WorkspaceID;
                $HookURL7 = '&ref_type=space&ref_id='.$WorkspaceID.'&type=space.delete&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_13_member_added_to_space='.$WorkspaceID;
                array_push($HooksArray, $HookURL1);

                //For Each Item in Hooks Array
                $HookBaseURL = 'https://hoist.thatapp.io/api/v2/PodioCreateHook?api_key=1e08b711302bcfa1096eb819b43737125e9550630ea0b98a7b59d9747e3bb634';
                foreach ($HooksArray as $hook) {
                    $GetAvailable = $Curl->get($HookBaseURL.$hook);
                }




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
                    'fields' => array(
                        'workspace-id-2' => (string)$WorkspaceID,
                        'date-created' => array('start' => $CreatedOnDateFormatted),
                        'last-activity' => array('start' => $LastActivityDateFormatted),
                        'status' => 'Locked Down',
                        'organization' => (int)$itemID,
                    ));


                //Create Embed Link with Space URL
                if ($WorkspaceURL) {
                    $CreateEmbedFile = PodioEmbed::create(array('url' => $WorkspaceURL));
                    $LinkEmbedID = $CreateEmbedFile->embed_id;
                    //Add Link to Fields Array
                    $NewWorkspaceItemArray['fields']['url'] = $LinkEmbedID;
                }


                //Add Space Name to Array
                if ($WorkspaceName) {
                    $NewWorkspaceItemArray['fields']['name'] = $WorkspaceName;
                }


                //Create Workspace Item in Audit Security Space
                $CreateSpaceItem = PodioItem::create($SpacesAppID, $NewWorkspaceItemArray);
            }

        }
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //Sleep 15 Seconds
    sleep(15);



    //Reset Trigger Value
    $TriggerValue = "...";
    $UpdateTriggerItem = PodioItem::update($itemID, array(
        'fields' => array('trigger-event' => $TriggerValue)),
        array('hook' => false));





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