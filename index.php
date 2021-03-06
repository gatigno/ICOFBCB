<?php
//test link: www.m.me/1422846014463338?ref=mid=1
$verify_token = "gnosis"; // Verify token
if (!empty($_REQUEST['hub_mode']) && $_REQUEST['hub_mode'] == 'subscribe' && $_REQUEST['hub_verify_token'] == $verify_token) {
    echo $_REQUEST['hub_challenge'];

} else {
    //get config values
    $config_array = parse_ini_string(file_get_contents('config.ini'));
    $token = $config_array['token'];
    $version = $config_array['version'];
    $env = $config_array['env'];

    //get input json
    $fb = file_get_contents("php://input");
    //saving all inputs
    $logs = file_get_contents("fb.txt");
    $logs .= "\n\n".$fb;
    file_put_contents("fb.txt",$logs);
    //decode input json
    $fb = json_decode($fb);
    //get fields, but id id not found - stop
    if (isset($fb->entry[0]->messaging[0]->sender->id)) {
        $user_id = $fb->entry[0]->messaging[0]->sender->id;
    } else {
        exit();
    }
    if (isset($fb->entry[0]->messaging[0]->referral->ref)) $ref = $fb->entry[0]->messaging[0]->referral->ref;
    if (isset($fb->entry[0]->messaging[0]->message->text)) $message = $fb->entry[0]->messaging[0]->message->text;
    if (isset($fb->entry[0]->messaging[0]->postback->payload)) $payload = $fb->entry[0]->messaging[0]->postback->payload;
    if (isset($fb->entry[0]->messaging[0]->message->quick_reply->payload)) $payload_quick = $fb->entry[0]->messaging[0]->message->quick_reply->payload;
    if (isset($fb->entry[0]->messaging[0]->timestamp)) $timestamp = $fb->entry[0]->messaging[0]->timestamp;

    //get last message timestamp
    $old_timestamp = file_get_contents("timestamp.txt");
    //if current timestamp == last timestamp
    if ($old_timestamp == $timestamp) exit();
    //put current timestamp to txt file on server
    file_put_contents("timestamp.txt",$timestamp);

    //storage for store user status
    $storage = file_get_contents("storage.json");
    $storage = json_decode($storage, true);
    if (isset($storage[$user_id])) {
        $status = $storage[$user_id]['status'];
        $stored_market_id = $storage[$user_id]['market_id'];
    } else {
        $storage[$user_id] = array("status" => "0",
            "market_id" => "0");
        $storage = json_encode($storage);
        file_put_contents("storage.json", $storage);
    }

    //get user profile info
    $user_info_link = "https://graph.facebook.com/v2.6/" . $user_id . "?access_token=" . $token;
    $user_info = file_get_contents($user_info_link);
    $user_info = json_decode($user_info);
    //get user name
    $first_name = $user_info->first_name;
    $last_name = $user_info->last_name;

    require('functions.php');

    //send version
    if (strtolower($message) == "version") {
        sendText($version,$user_id,$token);
        exit(200);
    } elseif (strpos($payload_quick, "notification_answer") === 0) {
        $payload_quick = explode("_",$payload_quick);
        $notificationID = $payload_quick[2];
        $answer = $payload_quick[3];

        updateValueDB('notificationID',$notificationID,'qrUserResponse',$answer);
        updateValueDB('notificationID',$notificationID,'dateTimeResponse',date("Y/m/d H:i:s"));
        exit(200);
    }
    //get started
    if ($payload == "GET_STARTED_PAYLOAD") {
        if (isset($fb->entry[0]->messaging[0]->postback->referral->ref)) {
            $ref = $fb->entry[0]->messaging[0]->postback->referral->ref;
        } else {
            $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4";
            $result_string = file_get_contents($link);
            $result_json = json_decode($result_string);

            for($i = 0; $i < count($result_json); $i++) {
                $button = array('type' => "postback",
                    'title' => "Go To Market",
                    'payload' => "mid=".$result_json[$i]->id);
                $buttons = [$button];

                $elements[$i] = json_encode(array("title" => $result_json[$i]->title,
                    "image_url" => $result_json[$i]->imageURL,
                    "subtitle" => "Current prediction: ".$result_json[$i]->prediction,
                    "buttons" => $buttons));
            }

            $payload = array('template_type' => "generic",
                'elements' => $elements);
            $attachment = array('type' => "template",
                'payload' => $payload);
            $data = array('recipient' => array('id' => $user_id),
                'message' => array('attachment' => $attachment));

            send($data,$token);
            exit();
        }
    }
    //flow
    if (isset($ref) || (int)$message == (1 || 2 || 3 || 4 || 5 || 6) && $status != "userAddress" || strpos($payload, "mid=") === 0) {
        if(isset($payload)) $ref = $payload;
        if(isset($ref)) {
            botType($user_id, $token);
            $ref = explode("=", $ref);
            $market_id = $ref[1];
        } else {
            $market_id = $message;
        }

        $text = "Hi $first_name!";
        sendText($text,$user_id,$token);
        botType($user_id,$token);

        $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4/search?id=".$market_id;
        $result_string = file_get_contents($link);
        $result_json = json_decode($result_string);

        if (!empty($result_json[0]->id)) {
            $text = "Welcome. I’m the Gnosis Bot. I’m here to help you review and participate in prediction markets related to the most exciting ICOs that will take place in the near future.";
            sendText($text,$user_id,$token);
            botType($user_id,$token);

            $market_id = $result_json[0]->id;
            $title = $result_json[0]->title;
            $prediction = $result_json[0]->prediction;
            $projectName = $result_json[0]->projectName;
            $projectToken = $result_json[0]->token;

            $button_1 = array(
                "content_type" => "text",
                "title" => "Yes, please",
                "payload" => "B01_1_".$market_id
            );

            $button_2 = array(
                "content_type" => "text",
                "title" => "Review Markets",
                "payload" => "B01_2_".$market_id
            );

            $button_3 = array(
                "content_type" => "text",
                "title" => "I need help",
                "payload" => "B01_3_".$market_id
            );
            $buttons = [$button_1,$button_2,$button_3];

            $text = "So if I got you right, you’re here regarding the ICO of ".$projectName." (".$projectToken."), correct?";
            sendText($text,$user_id,$token,$buttons);
        } else {
            botType($user_id,$token);
            $text = "I’m this is version ".$version.". On a future version I’ll show you the active markets here.";
            sendText($text,$user_id,$token);
        }
    } elseif (strpos($payload_quick, "B01") === 0) {
        $payload_quick = explode("_",$payload_quick);
        $choose = $payload_quick[1];
        $market_id = $payload_quick[2];

        if ($choose == "1") {
            $text = "Great.";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4/search?id=".$market_id;
            $result_string = file_get_contents($link);
            $result_json = json_decode($result_string);

            $market_id = $result_json[0]->id;
            $title = $result_json[0]->title;
            $prediction = $result_json[0]->prediction;
            $projectName = $result_json[0]->projectName;
            $projectToken = $result_json[0]->token;
            $votesTotal = $result_json[0]->votesTotal;
            $ethTotal = $result_json[0]->ethTotal;
            $resolutionDate = $result_json[0]->resolutionDate;
            $chart = str_replace("\u0026", "&", $result_json[0]->chart);
            $resolutionCountDown = round((strtotime($resolutionDate) - time())/86400);

            $text = "So the we’re talking about the ICO of ".$projectName." (".$projectToken."), and the market is predicting the following question: ";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = $title;
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = "YES / NO ?";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = $votesTotal." investors have backed their prediction by ".$ethTotal." ETH in this market.";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = "Current market prediction is ".$prediction." YES.";
            sendText($text,$user_id,$token);
            botType($user_id,$token);

            $payload = array('url' => $chart);
            $attachment = array('type' => 'image',
                'payload' => $payload);
            $data = array('recipient' => array('id' => $user_id),
                'message' => array('attachment' => $attachment));
            send($data,$token);
            botType($user_id,$token);
            $text = "This market will be resolved in ".$resolutionCountDown." days, on ".$resolutionDate.".";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $button_1 = array(
                "content_type" => "text",
                "title" => "Yes",
                "payload" => "B02_yes_".$market_id
            );

            $button_2 = array(
                "content_type" => "text",
                "title" => "No",
                "payload" => "B02_no_".$market_id
            );

            $buttons = [$button_1,$button_2];

            $text = "What do you think?";
            sendText($text,$user_id,$token,$buttons);
        } else {
            botType($user_id,$token);
            $text = "Sorry - this part of the bot is not implemented yet.";
            sendText($text,$user_id,$token);
        }
    } elseif (strpos($payload_quick, "B02") === 0) {
        $payload_quick = explode("_",$payload_quick);
        $choose = $payload_quick[1];
        $market_id = $payload_quick[2];

        $text = "OK, cool.";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $text = "Are you ready to back your prediction with real ETH?";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4/search?id=".$market_id;
        $result_string = file_get_contents($link);
        $result_json = json_decode($result_string);

        $market_id = $result_json[0]->id;
        $prediction = $result_json[0]->prediction;
        $yesL = $result_json[0]->yesL;
        $yesM = $result_json[0]->yesM;
        $yesH = $result_json[0]->yesH;
        $noL = $result_json[0]->noL;
        $noM = $result_json[0]->noM;
        $noH = $result_json[0]->noH;

        if ($choose == "yes") {
            $userPrediction = "YES";
            $userTablePrediction = "Yes";

            $roiL = abs((int)$prediction - ($yesL*100));
            $roiM = abs((int)$prediction - ($yesM*100));
            $roiH = abs((int)$prediction - ($yesH*100));
        } elseif ($choose == "no") {
            $userPrediction = "NO";
            $userTablePrediction = "No";

            $roiL = abs((int)$prediction - ($noL*100));
            $roiM = abs((int)$prediction - ($noM*100));
            $roiH = abs((int)$prediction - ($noH*100));
        }

        $data = array("marketID" => $market_id,
            "timestamp" => time(),
            "userID" => "",
            "prediction" => $userTablePrediction,
            "ethBet" => "",
            "ethAddress" => "");
        dynamicCreateRowDB("https://sheetsu.com/apis/v1.0/02eb4bdf06d4/sheets/predictions",$data);

        $text = "Potential revenue in current market state is:\n+".$roiL."% for 0.1 ETH\n+".$roiM."% for 0.5 ETH\n+".$roiH."% for 1 ETH";

        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $button_0 = array(
            "content_type" => "text",
            "title" => "Voting Only (0 ETH)",
            "payload" => "B03_voting_".$market_id
        );

        $button_1 = array(
            "content_type" => "text",
            "title" => "Low (0.1 ETH)",
            "payload" => "B03_0.1_".$market_id
        );

        $button_2 = array(
            "content_type" => "text",
            "title" => "Medium (0.5 ETH)",
            "payload" => "B03_0.5_".$market_id
        );

        $button_3 = array(
            "content_type" => "text",
            "title" => "High (1.0 ETH)",
            "payload" => "B03_1.0_".$market_id
        );

        $buttons = [$button_0,$button_1,$button_2,$button_3];

        $text = "What’s your level of certainty with your prediction?";
        sendText($text,$user_id,$token,$buttons);
    } elseif (strpos($payload_quick, "B03") === 0) {
        $payload_quick = explode("_",$payload_quick);
        $choose = $payload_quick[1];
        $market_id = $payload_quick[2];

        if ($choose == "voting") {
            $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4/search?id=" . $market_id;
            $result_string = file_get_contents($link);
            $result_json = json_decode($result_string);

            $market_id = $result_json[0]->id;
            $resolutionDate = $result_json[0]->resolutionDate;
            $resolutionCountDown = round((strtotime($resolutionDate) - time()) / 86400);

            $text = "Congratulations! Market resolution is in ".$resolutionCountDown." days. I’ll remind you about this and give you an update 24h before the due date. ";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = "Good luck!";
            sendText($text,$user_id,$token);
            exit(200);
        }

        $link = "https://sheetsu.com/apis/v1.0/0a1abb92d1d5";
        $result_string = file_get_contents($link);
        $result_json = json_decode($result_string);
        $systemWallet = $result_json[0]->systemWallet;
        $qrCode = str_replace("\u0026", "&", $result_json[0]->qrCode);

        $text = "Please send ".$choose." ETH to the following address:";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        sendText($systemWallet,$user_id,$token);
        botType($user_id,$token);
        $text = "You can also scan this code with your wallet (i.e Jaxx):";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $payload = array('url' => $qrCode);
        $attachment = array('type' => 'image',
            'payload' => $payload);
        $data = array('recipient' => array('id' => $user_id),
            'message' => array('attachment' => $attachment));
        send($data,$token);
        botType($user_id,$token);
        $text = "Go send the ETH now. I’m waiting.";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $button_1 = array(
            "content_type" => "text",
            "title" => "Yes, I sent the ETH",
            "payload" => "B04_yes_".$market_id
        );

        $button_2 = array(
            "content_type" => "text",
            "title" => "I need some help",
            "payload" => "B04_no_".$market_id
        );

        $buttons = [$button_1,$button_2];

        $text = "Let me know if you managed to work this out.";
        sendText($text,$user_id,$token,$buttons);

    } elseif (strpos($payload_quick, "B04") === 0) {
        $payload_quick = explode("_", $payload_quick);
        $choose = $payload_quick[1];
        $market_id = $payload_quick[2];

        if ($choose == "yes") {
            botType($user_id,$token);
            $text = "What’s the ethereum address you sent the ETH from, please? I’ll let you know when I see the transaction:";
            sendText($text,$user_id,$token);

            //storage for store user status
            $storage = file_get_contents("storage.json");
            $storage = json_decode($storage, true);
            $storage[$user_id] = array("status" => "userAddress",
                "market_id" => $market_id);
            $storage = json_encode($storage);
            file_put_contents("storage.json", $storage);

        } elseif ($choose == "no") {
            $text = "OK. Sorry to hear that. I’ll ask our community manager to text you on messenger and see what’s the issue.";
            sendText($text,$user_id,$token);
            botType($user_id,$token);
            $text = "Good luck!";
            sendText($text,$user_id,$token);
        }
    } elseif ($status == "userAddress" && isset($message)) {
        //storage for store user status
        $storage = file_get_contents("storage.json");
        $storage = json_decode($storage, true);
        $storage[$user_id] = array("status" => "0",
            "market_id" => "0");
        $storage = json_encode($storage);
        file_put_contents("storage.json", $storage);

        $text = "Got it. Thanks. I’ll save this address so you won’t have to paste it again.";
        sendText($text,$user_id,$token);
        botType($user_id,$token);

        $name = $first_name." ".$last_name;
        $address = $message;
        //write to db
        createRowDB($user_id,$name,$address,$env);

        $link = "https://sheetsu.com/apis/v1.0/02eb4bdf06d4/search?id=" . $stored_market_id;
        $result_string = file_get_contents($link);
        $result_json = json_decode($result_string);

        $market_id = $result_json[0]->id;
        $resolutionDate = $result_json[0]->resolutionDate;
        $resolutionCountDown = round((strtotime($resolutionDate) - time()) / 86400);

        $text = "Congratulations! Market resolution is in ".$resolutionCountDown." days. I’ll remind you about this and give you an update 24h before the due date. ";
        sendText($text,$user_id,$token);
        botType($user_id,$token);
        $text = "Good luck!";
        sendText($text,$user_id,$token);
    }
}