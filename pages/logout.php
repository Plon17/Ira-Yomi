<?php
session_start();

// Destroy session
session_unset();
session_destroy();

// Redirect to index
header('Location: ../index.php');
exit;
?>