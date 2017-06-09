<?php
require ('functions.php');
//get config values
$config_array = parse_ini_string(file_get_contents('config.ini'));
$token = $config_array['token'];
$env = $config_array['env'];
//function for check page status (200 = good)
function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}
//get rows with status=pending for current bot
if(get_http_response_code('https://sheetsu.com/apis/v1.0/7eaa4e913947/search?status=pending&env='.$env) != "200"){
    echo "Error 404: page not found.";
}else{
    $notification_json = file_get_contents("https://sheetsu.com/apis/v1.0/7eaa4e913947/search?status=pending&env=".$env);
    $notification_array = json_decode($notification_json, true);
    //update status to "sent"
    updateValueDB('env',$env,'status','sent');
    //loop with all notifications rows
    for ($i = 0; $i < count($notification_array); $i++) {
        $notificationID = $notification_array[$i]['notificationID'];
        $user_id = $notification_array[$i]['fbUserID'];
        $message = $notification_array[$i]['message'];
        //if qrA,qrB,qrC are not empty - create buttons
        $button_names = array('qrA','qrB','qrC');
        for ($y = 0; $y < count($button_names); $y++) {
            if (!empty($notification_array[$i][$button_names[$y]])) {
                $keyboard[] = array(
                    "content_type" => "text",
                    "title" => $notification_array[$i][$button_names[$y]],
                    "payload" => "notification_answer_".$notificationID."_".$button_names[$y]
                );
            }
        }
        //sent text or text with buttons
        if (isset($keyboard)) {
            sendText($message, $user_id, $token, $keyboard);
        } else {
            sendText($message, $user_id, $token);
        }
        //set dateTimeSent fot notification row
        updateValueDB('notificationID',$notificationID,'dateTimeSent', date("Y/m/d H:i:s"));
    }
}
//get skip value
$skip = (int)file_get_contents('skip_transactions.txt');
//wallet notice
$wallet_json = file_get_contents("https://etherchain.org/api/account/0xcdd3f40ab11Ec46C7054085085d8EF148A69975E/tx/0");
$wallet_array = json_decode($wallet_json);
//if we have new transactions
if (count($wallet_array->data) > $skip) {
    //set skip value
    file_put_contents('skip_transactions.txt', count($wallet_array->data));
    //loop for sent notifications about new transactions
    for ($i = 0; $i < (count($wallet_array->data)-$skip); $i++) {
        sendText("1 new transaction: ".$wallet_array->data[$i]->hash, 1276816069083488, $token);
    }
}






