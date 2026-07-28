<?php
/**
 * Portal Siswa - Logout
 */
session_start();
unset($_SESSION['siswa_id']);
unset($_SESSION['siswa_nis']);
unset($_SESSION['siswa_nama']);
unset($_SESSION['is_siswa']);

header('Location: index.php');
exit;
