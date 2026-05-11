<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_POST['action'] = 'send';
$_POST['product_id'] = 1;
$_POST['receiver_id'] = 2;
$_POST['body'] = 'test message';

require 'd:\xampp\htdocs\CampusMarket\pages\api_messages.php';
