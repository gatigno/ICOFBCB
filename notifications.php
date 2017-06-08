<?php
require ('functions.php');
//get config values
$config_array = parse_ini_string(file_get_contents('config.ini'));
$token = $config_array['token'];
$env = $config_array['env'];

function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}


if(get_http_response_code('https://sheetsu.com/apis/v1.0/7eaa4e913947/search?status=pending&env='.$env) != "200"){
    exit(200);
}else{
    $notification_json = file_get_contents("https://sheetsu.com/apis/v1.0/7eaa4e913947/search?status=pending&env=".$env);
    $notification_array = json_decode($notification_json, true);

    updateValueDB('env',$env,'status','sent');

    for ($i = 0; $i < count($notification_array); $i++) {
        $notificationID = $notification_array[$i]['notificationID'];
        $user_id = $notification_array[$i]['fbUserID'];
        $message = $notification_array[$i]['message'];

        if (!empty($notification_array[$i]['qrA'])) {
            $keyboard[] = array(
                "content_type" => "text",
                "title" => $notification_array[$i]['qrA'],
                "payload" => "notification_answer_".$notificationID."_qrA"
            );
        }

        if (!empty($notification_array[$i]['qrB'])) {
            $keyboard[] = array(
                "content_type" => "text",
                "title" => $notification_array[$i]['qrB'],
                "payload" => "notification_answer_".$notificationID."_qrB"
            );
        }

        if (!empty($notification_array[$i]['qrC'])) {
            $keyboard[] = array(
                "content_type" => "text",
                "title" => $notification_array[$i]['qrC'],
                "payload" => "notification_answer_".$notificationID."_qrC"
            );
        }

        if (isset($keyboard)) {
            sendText($message, $user_id, $token, $keyboard);
        } else {
            sendText($message, $user_id, $token);
        }

        updateValueDB('notificationID',$notificationID,'dateTimeSent', date("Y/m/d H:i:s"));

    }
}


