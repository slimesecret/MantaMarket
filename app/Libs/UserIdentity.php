<?php
class app_Libs_UserIdentity
{
    public $username;
    public $password;
    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }








    public function login($data)
    {
        $_SESSION["userId"]   = $data["id"];
        $_SESSION["username"] = $data["username"];
        $_SESSION["role"]     = $data["role"];   // LƯU ROLE VÀO SESSION
        $_SESSION["avatar"]   = $data["avatar"]; // ← THÊM DÒNG NÀY

        return true;
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();
    }

    public function getSESSION($name)
    {
        if ($name !== NULL) {
            return isset($_SESSION[$name]) ? $_SESSION[$name] : NULL;
        }
        return $_SESSION;
    }

    public function isLogin()
    {
        if ($this->getSESSION("userId")) {
            return true;
        }
        return false;
    }

    public function getId()
    {
        return $this->getSESSION("userId");
    }
}