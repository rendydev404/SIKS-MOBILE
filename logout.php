<?php
/**
 * Logout
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

session_start();
session_destroy();

header('Location: ' . '/sppsmkalamin/');
exit;
