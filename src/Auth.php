<?php
// Very small auth helper - replace with real auth for production
class Auth
{
    public static function checkRole($needed, $user)
    {
        if (!$user) return false;
        return $user['role'] === $needed || $user['role'] === 'admin';
    }
}
