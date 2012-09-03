<?php

require_once 'NikeplusFuelbandAPI.php';

$object = new NikeplusFuelbandAPI('your_email', 'your_password');
$object->login();
$data = $object->getFriendLastFullActivity('your_friend_username');
echo '<pre>'; print_r($data);
