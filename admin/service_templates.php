<?php
// admin/service_templates.php
// Legacy service templates have been superseded by AI-assisted listing creation and unified service categories.
require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/auth_check.php';

requireAdmin();

flash('info', 'Service templates have been superseded by AI-assisted onboarding and Service Categories.');
redirect(BASE_URL . 'admin/services.php');
exit;
