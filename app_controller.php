<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function app_controller()
{
    global $path,$session,$route,$mysqli,$user;

    $result = false;
    
    include "Modules/app/available_apps.php";
    include "Modules/app/AppConfig_model.php";
    $appconfig = new AppConfig($mysqli,$available_apps);
    
    // ------------------------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------------------------
    if ($route->action == "list" && $session['read']) {
        $route->format = "json";
        $result = $appconfig->applist($session['userid']);
    }
    else if ($route->action == "add" && $session['write']) {
        $route->format = "json";
        $appname = get("app");
        if (isset($available_apps[$appname])) {
            $result = $appconfig->add($session['userid'],$appname,get("name"));
        } else {
            $result = "Invalid app";
        }
    }
    else if ($route->action == "remove" && $session['write']) {
        $route->format = "json";
        $result = $appconfig->remove($session['userid'],get("name"));
    }
    else if ($route->action == "setconfig" && $session['write']) {
        $route->format = "json";
        $result = $appconfig->setconfig($session['userid'],get('name'),get('config'));    
    } 
    else if ($route->action == "getconfig" && $session['read']) {
        $route->format = "json";
        $result = $appconfig->getconfig($session['userid'],get('name'));
    }
    else if ($route->action == "dataremote") {
        $route->format = "json";
        $id = (int) get("id");
        $start = (float) get("start");
        $end = (float) get("end");
        $interval = (int) get("interval");
        $result = json_decode(file_get_contents("http://emoncms.org/feed/data.json?id=$id&start=$start&end=$end&interval=$interval&skipmissing=0&limitinterval=0"));
    }
    else if ($route->action == "valueremote") {
        $route->format = "json";
        $id = (int) get("id");
        $result = (float) json_decode(file_get_contents("http://emoncms.org/feed/value.json?id=$id"));
    }
    else if ($route->action == "ukgridremote") {
        $route->format = "json";
        $start = (float) get("start");
        $end = (float) get("end");
        $interval = (int) get("interval");
        $result = json_decode(file_get_contents("https://openenergymonitor.org/ukgrid/api.php?q=data&id=1&start=$start&end=$end&interval=$interval"));
    }
    else if ($route->action == "new" && $session['write']) {
        $applist = $appconfig->applist($session['userid']);
        $route->format = "html";
        $result = "<link href='".$path."Modules/app/css/pagenav.css?v=1' rel='stylesheet'>";
        $result .= "<div id='wrapper'>";
        $result .= view("Modules/app/sidebar.php",array("applist"=>$applist));
        $result .= view("Modules/app/list_view.php",array("available_apps"=>$available_apps));
        $result .= "</div>";
    }
    // ------------------------------------------------------------------------------------
    // APP LOAD
    // For general viewing and loading etc, this is where it pretty much starts
    // ------------------------------------------------------------------------------------
    
    else if ($route->action == "view") {
    
        // enable apikey read access
        $userid = false;
        if (isset($session['write']) && $session['write']) {             // Check for an existing logged in session
            $userid = $session['userid'];                                // Get a userid from the session data
            $apikey = $user->get_apikey_write($session['userid']);       // Get apikey from userid for app to make api calls 
        } else if (isset($_GET['readkey'])) {
            $apikey = $_GET['readkey'];                                  // Otherwise use a supplied "readkey" as apikey
            $userid = $user->get_id_from_apikey($apikey);                // and get the userid from that
        } else if (isset($_GET['apikey'])) {
            $apikey = $_GET['apikey'];                                   // or if an "apikey" is provided in the url
            $userid = $user->get_id_from_apikey($apikey);                // use that
        }
        
        if ($userid)                                                     // In other words "if valid session or apikey" was found
        {
            $applist = $appconfig->applist($userid);                     // get a list of that users existing apps
            
            if ($route->subaction) {                                     // get the app name from the url
                $userappname = $route->subaction;                        // either from a subaction
            } else {
                $userappname = get("name");                              // or passed using "name="
            }
            
            if (!isset($applist->$userappname)) {                        // if requested app is NOT in the users list of apps
                foreach ($applist as $key=>$val) { $userappname = $key; break; }
            }                                                            // JUST USE THE FIRST APP NAME IN THE LIST (IF THERE IS ONE) ???
            
            $route->format = "html";
            if ($userappname!=false) {                                   // If the user has a pre-existing app to load
                $app = $applist->$userappname->app;                      // Get the type of app from the applist using the app name
                $config = $applist->$userappname->config;                // Get the existing configuration for the app by name
            }
            
            // Construct the webpage to be returned starting with a link to "app/css/pagenav.css" stylesheet
            $result = "<link href='".$path."Modules/app/css/pagenav.css?v=1' rel='stylesheet'>";
            $result .= "<div id='wrapper'>";                             // to that add opening the main "wrapper" div
            
            // Include the collapsible sidebar if user is logged in (displayed open by default if window size big enough)
            if ($session['write']) $result .= view("Modules/app/sidebar.php",array("applist"=>$applist));
            
            // Load the app's page (apps/$app.php) if app name is known passing the app's $name and $config (and the $apikey)
            if ($userappname!=false) {                                   
                if (!file_exists("Modules/app/apps/$app.php")) $app = "blank"; // fallback to "blank.php" if app's file not found
                $result .= view("Modules/app/apps/$app.php",array("name"=>$userappname, "config"=>$config, "apikey"=>$apikey));
            } else {   // if app name not known (eg no pre-existing apps - new user) just list available type templates
                $result .= view("Modules/app/list_view.php",array("available_apps"=>$available_apps));
            }
            $result .= "</div>";                                         // close the "wrapper" div
        }
    }

    global $fullwidth;                                                   
    $fullwidth = true;
    return array('content'=>$result, 'fullwidth'=>true);
}

