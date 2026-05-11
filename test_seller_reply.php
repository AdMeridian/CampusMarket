<?php
session_start();
$_SESSION['user_id'] = 2; // Seller
$_POST['action'] = 'send';
$_POST['product_id'] = 1;
$_POST['receiver_id'] = 1; // Buyer
$_POST['body'] = 'reply message';

require 'd:\xampp\htdocs\CampusMarket\pages\api_messages.php';
