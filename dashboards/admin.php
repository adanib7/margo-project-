<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
requireRole('admin', 'superadmin');
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css" />
<?php require_once '../includes/nav.php'; ?>

<main class="dashboard-main role-admin">
  <div class="dashboard-header">
    <h1>Panel de Administración</h1>
    
</main>

<?php require_once '../includes/footer.php'; ?>
