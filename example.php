<?php
echo '<pre>' ;

require_once 'NikeplusFuelbandAPI.php' ;

$object = new NikeplusFuelbandAPI('your_email', 'your_password')  ;
$object->login() ;

$friend_full_activity = $object->getFriendLastFullActivity('your_friend_username') ;
print_r($friend_full_activity) ;

$my_full_activity = $object->getMyLastFullActivity() ;
print_r($my_full_activity) ;
