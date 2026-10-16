<?php
// pages/api_seller_verification.php
// AJAX endpoints to send and verify seller student email OTP

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'You must be logged in to verify seller status.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

verifyCsrfToken();

$action = trim((string)($_POST['action'] ?? ''));
$userId = currentUserId();
$username = currentUsername();

if ($action === 'send_otp') {
    $studentEmail = trim((string)($_POST['student_email'] ?? ''));
    if ($studentEmail === '') {
        echo json_encode(['ok' => false, 'error' => 'Please provide your university student email.']);
        exit;
    }

    $res = createAndSendStudentEmailOtp($pdo, $userId, $studentEmail, $username);
    echo json_encode($res);
    exit;
}

if ($action === 'verify_otp') {
    $studentEmail = trim((string)($_POST['student_email'] ?? ''));
    $otpCode = trim((string)($_POST['otp_code'] ?? ''));

    if ($studentEmail === '' || $otpCode === '') {
        echo json_encode(['ok' => false, 'error' => 'Please enter both the student email and the 6-digit verification code.']);
        exit;
    }

    $res = verifyStudentEmailOtp($pdo, $userId, $studentEmail, $otpCode);
    echo json_encode($res);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
exit;
