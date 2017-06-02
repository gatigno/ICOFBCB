<?php
    function botType($id,$token) {
        $data = array('recipient' => array('id' => $id),
            'sender_action' => 'typing_on');
        send($data,$token);
        //sleep(1);
    }

    function sendText($text,$id,$token,$optional_replies = null) {
        $data = array('recipient' => array('id' => $id),
            'message' => array('text' => $text));

        if ($optional_replies !== null) {
            $data['message']['quick_replies'] = $optional_replies;
        }
        send($data,$token);
    }


    function send($data,$token) {
        $url = "https://graph.facebook.com/v2.7/me/messages?access_token=" . $token;

        $data_string = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        curl_exec($ch);
        curl_close($ch);
    }


    function createRowDB($user_id,$name,$address) {
        $url = "https://sheetsu.com/apis/v1.0/13a8a99eb990";

        $data = array("userID" => "$user_id",
            "userName" => $name,
            "address" => $address);

        $data_string = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json')
        );
        curl_exec($ch);
        curl_close($ch);
    }