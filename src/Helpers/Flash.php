<?php
class Flash
{
    public static function init()
    {
        // Session is already started by bootstrap.php
        if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
    }

    public static function add($type, $message)
    {
        self::init();
        $_SESSION['flash'][] = ['type'=>$type, 'message'=>$message];
    }

    public static function getAll()
    {
        self::init();
        $fl = $_SESSION['flash'];
        $_SESSION['flash'] = [];
        return $fl;
    }

    public static function render()
    {
        $msgs = self::getAll();
        $out = '';
        foreach ($msgs as $m) {
            $t = $m['type'] === 'danger' ? 'danger' : ($m['type'] === 'warning' ? 'warning' : 'success');
            $out .= "<div class=\"alert alert-$t alert-dismissible fade show\" role=\"alert\">" . htmlspecialchars($m['message']) . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button></div>";
        }
        return $out;
    }
}