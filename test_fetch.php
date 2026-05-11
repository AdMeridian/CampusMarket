<?php
session_start();
$_SESSION['user_id'] = 1;
$_GET['action'] = 'fetch';
$_GET['product_id'] = 1;
$_GET['other_user_id'] = 2;

require 'd:\xampp\htdocs\CampusMarket\pages\api_messages.php';
