<?php
/**
 * Created by PhpStorm.
 * User: Isaac
 * Date: 8/3/2016
 * Time: 1:41 PM
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

    //Get Trigger Subscription Item
    $item = PodioItem::get($itemID);
    $appName = $item->app->name;
    $appID = $item->app->app_id;


    //0 - Hoist Database Space Apps
    $PlansAppID = 15229287;
    $CustomersAppID = 15229543;
    $SubscriptionsAppID = 15066465;


    //0 - Hoist Database Space Audit Item ID's
    $AuditExtensionItemID = 483906135;
    $AuditBasicPlanItemID = 483919569;


    //Set Current Date and Time
    $todaysDate = date_create("now");
    $dateTimeStamp = new DateTime((string)$todaysDate, new DateTimeZone('America/Denver'));
    $FormatTimeStamp = $dateTimeStamp->format("Y-m-d H:i:s");



    //Get Trigger Subscription Item
    $SubscriptionCustomerItemID = $item->fields['customer']->values[0]->item_id;
    $PlanItemID = $item->fields['plan']->values[0]->item_id;
    $SubscriptionStatus = $item->fields['status']->values[0]['text'];



    //If Subscriptions related Plan Item ID is not Audit, End
    if((int)$PlanItemID !== (int)$AuditBasicPlanItemID){exit;}

    //Get Subscriptions Customer Item & Field Values
    $CustomerItem = PodioItem::get($SubscriptionCustomerItemID);
    $CompanyName = $CustomerItem->fields['company-name']->values;
    $FullName = $CustomerItem->fields['full-name']->values;
    $CustomerOrgID = $CustomerItem->fields['organization-id']->values;
    $CustomerUserID = $CustomerItem->fields['text-3']->values;
    $CustomerProfileID = $CustomerItem->fields['profile-id']->values;
    $AuditSpaceID = $CustomerItem->fields['extension-space-id']->values;
    $HoistConnectionID = $CustomerItem->fields['hoist-connection-id']->values;

    //Get All Active Extentions for Customer ///////////////Add to Customer if not Already/////////////////////////
//    $ActiveExtensionsArray = array();
//    $ActiveExtensions = $CustomerItem->fields['active-extensions']->values;
//    foreach($ActiveExtensions as $extension){
//        $ExtensionItemID = $extension->item_id;
//        array_push($ActiveExtensionsArray, $ExtensionItemID);
//    }
//
//    //Check if Audit is Part of Customers Active Extensions
//    if(in_array((int)$AuditExtensionItemID, $ActiveExtensions)){$InArray = "True";}
//    else{$InArray = "False";}
//
//    //Add Audit Extension to Customer if Not already there.
//    if($InArray == "False"){
//        array_push($ActiveExtensionsArray, $AuditExtensionItemID);
//        $UpdateActiveExtensions = PodioItem::update($SubscriptionCustomerItemID, array(
//            'fields'=>array(
//                'active-extensions'=>$ActiveExtensionsArray,
//            )
//        ));
//    }




    //If Subscription Status is not Active, End
    if($SubscriptionStatus !== "New"){
        exit;
    }


    //Set Variables
    $AVAUserID = 1406952;
    $AvaProfileID = 68718029;
    $AvaEmail = 'support@techego.com';


    //Create Audit Workspace
    $ClientSpace = PodioSpace::create(array('org_id' => 10685, 'name' => 'AVA Audit - ' . $CompanyName));
    $AuditSpaceID = $ClientSpace['space_id'];
    $ClientSpaceLink = $ClientSpace['url'];
    $result = $AuditSpaceID;

    //Add User to Space
    if ($CustomerUserID) {
        PodioSpaceMember::add($AuditSpaceID, array('role' => 'admin', 'users' => array((int)$CustomerUserID, $AVAUserID)));
    }


    //Get Template Apps
    $templateApps = PodioApp::get_for_space(4901693);




    //Create Fields Array
    $CustomerFieldsArray = array(
        'fields' => array(
            'extension-space-id' => (string)$AuditSpaceID
        ));



    //Hooks Array
    $HooksArray = array();





    //Loop Each App and Install
    foreach ($templateApps as $templateapp) {
        $AppID = $templateapp->app_id;
        $AppName = $templateapp->config['name'];

        //Install Each App
        $newApp = PodioApp::install($AppID, array('space_id' => $AuditSpaceID, 'type'=>'standard','features' =>array('filters', 'widgets')));

        //Create Hooks for new "my ORG" app/////////////////////////////////////////////////////////////
        if ($AppName == "my ORG") {
            $MyORGAppID = $newApp;
            $CustomerFieldsArray['fields']['my-org-app-id'] = (string)$MyORGAppID;

            //Get New App
            $MYorgApp = PodioApp::get($MyORGAppID);
            foreach ($MYorgApp->fields as $field) {
                $FieldName = $field->label;
                if ($FieldName == 'Trigger Events') {
                    $OrgFieldID = $field->field_id;
                    //Add Hooks for My ORG App to Hooks Array
                    $HookURL1 = '&ref_type=app_field&ref_id='.$OrgFieldID.'&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_5_myorg_triggered_events';
                    array_push($HooksArray, $HookURL1);
                }

                //Get External ID for Customer Field
                if ($FieldName == 'Customer') {
                    $CustomerFieldID = $field->field_id;
                    $UpdateCustomerRelationshipField = PodioAppField::update($MyORGAppID, $CustomerFieldID,  array('label' => "Customer", 'delta'=>11, 'settings'=>array('referenced_apps'=>array(array("app_id" => 15229543)))));
                }
            }
        }



        //Get APP ID & Create Hooks for "Workspaces" app/////////////////////////////////////
        if ($AppName == "Workspaces") {
            $WorkspacesAppID = $newApp;
            $CustomerFieldsArray['fields']['workspaces-app-id'] = (string)$WorkspacesAppID;

            //Get New Workspace App
            $WorkspaceApp = PodioApp::get($WorkspacesAppID);
            foreach ($WorkspaceApp->fields as $field) {
                $FieldName = $field->label;
                if ($FieldName == 'Trigger Events') {
                    $WorkspaceTriggerFieldID = $field->field_id;

                    //Add Hooks for Workspaces App to Hooks Array
                    $HookURL2 = '&ref_type=app_field&ref_id='.$WorkspaceTriggerFieldID.'&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_6_workspace_triggered_events';
                    array_push($HooksArray, $HookURL2);
                }

                //Get Name Field ID
                if ($FieldName == 'Name') {$WorkspaceNameFieldID = $field->field_id;}
                if ($FieldName == 'Organization') {$WorkspaceOrgRelateFieldID = $field->field_id;}
            }
        }

        //Get APP ID & Create Hooks for "Apps" app/////////////////////////////////////
        if ($AppName == "Apps") {
            $AppsAppID = $newApp;
            $CustomerFieldsArray['fields']['apps-app-id'] = (string)$AppsAppID;

            //Get New Workspace App
            $AppsApp = PodioApp::get($AppsAppID);
            foreach ($AppsApp->fields as $field) {
                $FieldName = $field->label;
                if ($FieldName == 'Trigger Events') {
                    $AppsTriggerFieldID = $field->field_id;

                    //Add Hooks for Workspaces App to Hooks Array
                    $HookURL3 = '&ref_type=app_field&ref_id='.$AppsTriggerFieldID.'&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_8_app_triggered_events';
                    array_push($HooksArray, $HookURL3);
                }
                if ($FieldName == 'App Name') {
                    $AppNameFieldID = $field->field_id;
                }
                if ($FieldName == 'Workspace') {
                    $WorkspaceRelateFieldID = $field->field_id;
                }
            }
        }


        //Get APP ID & Create Hooks for "Users" app/////////////////////////////////////
        if ($AppName == "Action Logs") {
            $ActionLogsAppID = $newApp;
            $CustomerFieldsArray['fields']['action-logs-app-id'] = (string)$ActionLogsAppID;

            //Get New Workspace App
            $ActionLogsApp = PodioApp::get($ActionLogsAppID);
            foreach ($ActionLogsApp->fields as $field) {
                $FieldName = $field->label;
                if ($FieldName == 'Trigger Events') {
                    $ActionLogsTriggerFieldID = $field->field_id;

                    //Add Hooks for Workspaces App to Hooks Array
                    $HookURL4 = '&ref_type=app_field&ref_id='.$ActionLogsTriggerFieldID.'&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_9_actionlogs_triggered_events';
                    array_push($HooksArray, $HookURL4);
                }
            }
        }

        //Get APP ID & Create Hooks for "Users" app/////////////////////////////////////
        if ($AppName == "Users") {
            $UsersAppID = $newApp;
            $CustomerFieldsArray['fields']['users-app-id'] = (string)$UsersAppID;

            $UserApp = PodioApp::get($UsersAppID);
            foreach ($UserApp->fields as $field) {
                $UserTriggerFieldID = $field->field_id;
                $FieldName = $field->label;

                //Add Hooks for My ORG App to Hooks Array
                if ($FieldName == 'Space Access - Admin') {
                    $HookURL5 = '&ref_type=app_field&ref_id=' . $UserTriggerFieldID . '&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_4_admin_access_change';
                    array_push($HooksArray, $HookURL5);
                }
                if ($FieldName == 'Space Access - Regular') {
                    $HookURL6 = '&ref_type=app_field&ref_id=' . $UserTriggerFieldID . '&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_4_regular_access_change';
                    array_push($HooksArray, $HookURL6);
                }
                if ($FieldName == 'Space Access - Light') {
                    $HookURL7 = '&ref_type=app_field&ref_id=' . $UserTriggerFieldID . '&type=item.update&url=https://hoist.thatapp.io/podio_catcher.php?service=audit_4_light_access_change';
                    array_push($HooksArray, $HookURL7);
                }
            }

        }
    }



    //Update my ORG App Org Admin Field






    //Create Calc Field in Org App for # of Workspaces
    $CreateTotalWorkspacesField = PodioAppField::create($MyORGAppID,  array('type'=>'calculation','config'=>array(
        'label'=>"Total Workspace",
        'delta'=>5,
        'settings'=>array(
            'script'=>"@[All of Name](in_".$WorkspaceNameFieldID.'_'.$WorkspaceOrgRelateFieldID.")".".length"),
    )
    ));




    //Create Calc Field in Workspaces App for # of Apps
    $CreateTotalAppssField = PodioAppField::create($WorkspacesAppID,  array('type'=>'calculation','config'=>array(
        'label'=>"# of Apps",
        'delta'=>4,
        'settings'=>array(
            'script'=>"@[All of App Name](in_".$AppNameFieldID.'_'.$WorkspaceRelateFieldID.")".".length"),
    )
    ));






    //For Each Item in Hooks Array
    $HookBaseURL = 'https://hoist.thatapp.io/api/v2/PodioCreateHook?api_key=1e08b711302bcfa1096eb819b43737125e9550630ea0b98a7b59d9747e3bb634';
    foreach ($HooksArray as $hook) {
        $GetAvailable = $Curl->get($HookBaseURL.$hook);
    }




    //Update New Subscription Customer Item with Space & App ID's
    $UpdateCustomerItem = PodioItem::update($SubscriptionCustomerItemID, $CustomerFieldsArray);

    //Create Org Item
    $CreateMyOrgItem = PodioItem::create($MyORGAppID, array(
        'fields' => array(
            'title' => $CompanyName,
            'podio-org-id' => $CustomerOrgID,
            'customer'=>(int)$SubscriptionCustomerItemID,
        )
    ));











    return [
        'success' => true,
        'result' => $HooksArray,
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





