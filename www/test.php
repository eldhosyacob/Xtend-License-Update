<?php
$hash = '$2y$10$dTR9HW0NRb3GgTerZhdUnupr5VhtHJJg4BjYylkrzRM7xHp4/9vL2';

if (password_verify("admin", $hash)) {
    echo "Correct password";
} else {
    echo "Wrong password";
}

// $password = "admin";

// $hash = password_hash($password, PASSWORD_BCRYPT);

// echo $hash;
?>