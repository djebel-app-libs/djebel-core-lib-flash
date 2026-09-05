<?php
/*
lib_id: djebel-core-lib-flash
lib_name: Djebel Core Flash
version: 1.0.0
description: Read-once flash storage (Dj_App_Core_Lib_Flash) — the payload a POST leaves for the GET it redirects to, so a Post/Redirect/Get flow can report what it wrote without putting any of it in the url. Values ride the session under this lib's own namespace. Lazily loaded on demand.
min_php_ver: 7.4
*/

// Not a plugin — it registers no hooks. Singleton — grab the instance, then call instance
// methods:
//   $flash_obj = Dj_App_Core_Lib_Flash::getInstance();
//   $flash_obj->set('display_fields', $fields);       // before the redirect
//   $fields = $flash_obj->pull('display_fields', []);  // on the screen redirected to — consumes
//   $fields = $flash_obj->get('display_fields', []);   // look without consuming
// Storage is the session lib, under THIS lib's own namespace, so a flash value can never
// collide with an app's own session keys. A pre-defined / custom impl wins: if the class
// exists, bail.
if (class_exists('Dj_App_Core_Lib_Flash')) {
    return;
}

class Dj_App_Core_Lib_Flash
{
    // This lib keeps every flash value under its OWN session namespace — never at the
    // $_SESSION root and never mixed into a consumer's namespace, so an app clearing its
    // own session keys cannot take the flash payload with it, and vice versa.
    const SESSION_NAMESPACE = 'djebel_core_flash';

    public static function getInstance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new static();
        }

        return $instance;
    }

    /**
     * Leave a value for the NEXT request. Writing is also how a caller CLEARS what the
     * previous request left — an operation that has nothing to report still overwrites,
     * so a stale payload can never be read as a report of the one now on screen. Write
     * the empty value of the type the reader asks for ([] for an array reader), since
     * get() casts what it finds to the default's type.
     *
     * @param string $key
     * @param mixed $val
     * @return bool
     */
    public function set($key, $val)
    {
        if (empty($key)) {
            return false;
        }

        $session_obj = Dj_App_Core_Lib_Session::getInstance();
        $session_obj->set(static::SESSION_NAMESPACE, $key, $val);

        return true;
    }

    /**
     * Read a flash value and LEAVE it in place, so the same request can look at it more
     * than once — a listener deciding whether to render, ahead of whoever consumes it.
     * Reading without consuming is the exception here: pull() is what a screen wants.
     *
     * The DEFAULT'S TYPE is the cast, the same contract as Dj_App_Util::getField — pass []
     * and an array comes back whatever was stored, pass 0 and an int does. A caller gets
     * usable data and never has to check the type of what it just asked for.
     *
     * @param string $key
     * @param mixed $default_val Returned when nothing was left for this key; its type casts
     * @return mixed
     */
    public function get($key, $default_val = '')
    {
        if (empty($key)) {
            return $default_val;
        }

        $session_obj = Dj_App_Core_Lib_Session::getInstance();
        $val = $session_obj->get(static::SESSION_NAMESPACE, $key, $default_val);

        if (is_array($default_val)) {
            $val = (array) $val;
        } elseif (is_int($default_val)) {
            $val = (int) $val;
        }

        return $val;
    }

    /**
     * Read a flash value AND clear it — the normal way to consume one. That the value
     * survives exactly one read is what makes it a flash rather than a session key, so
     * refreshing the page it landed on cannot re-report something that happened once.
     *
     * @param string $key
     * @param mixed $default_val Returned when nothing was left for this key
     * @return mixed
     */
    public function pull($key, $default_val = '')
    {
        $val = $this->get($key, $default_val);

        $session_obj = Dj_App_Core_Lib_Session::getInstance();
        $session_obj->remove(static::SESSION_NAMESPACE, $key);

        return $val;
    }
}
