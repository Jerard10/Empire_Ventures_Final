<?php

function generateOTP()
{
    return (string) random_int(100000, 999999);
}

function hashOTP($otp)
{
    return password_hash($otp, PASSWORD_DEFAULT);
}

function verifyOTP($otp, $hash)
{
    return password_verify($otp, $hash);
}