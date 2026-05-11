<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'send';
$_POST['product_id'] = 1;
$_POST['receiver_id'] = 2;
$_POST['body'] = 'test message';

// We need to bypass the session check to see the exact output of isValidProductConversation and other logic without triggering session notices
function isLoggedIn() { return true; }
function currentUserId() { return 1; }

require 'd:\xampp\htdocs\CampusMarket\pages\api_messages.php';
