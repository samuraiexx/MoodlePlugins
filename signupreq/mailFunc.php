<?php

function send_mail($user, $type){
    global $CFG;
    global $string;
    global $USER;
    require_once('lang/'. $CFG->lang . '/auth_signupreq.php');

    $body = $string[$type . 'Body'];
    $sub = $string[$type . 'Sub'];

    email_to_user($user, $USER, $sub,  html_to_text($body), $body, '', '', true);
}

