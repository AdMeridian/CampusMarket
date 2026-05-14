<?php
require_once __DIR__ . '/../config/constants.php';

// Keep a single canonical homepage implementation.
redirect(BASE_URL . 'index.php');
