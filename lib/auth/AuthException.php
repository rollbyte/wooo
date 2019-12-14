<?php

namespace wooo\lib\auth;

class AuthException extends \Exception
{
    public const OAUTH_INVALID_STATE = 6001;
    public const OAUTH_NO_EMAIL = 6002;
    public const INVALID_CREDENTIALS = 6003;
    public const NO_PASSPORT = 6004;
    
    private static $messages = [
        self::OAUTH_INVALID_STATE => 'OAuth state is invalid.',
        self::OAUTH_NO_EMAIL => 'User e-mail was not obtained from external application.',
        self::INVALID_CREDENTIALS => 'Invalid user credentials.',
        self::NO_PASSPORT => 'No passport associated with authentication type \'%s\'.'
    ];
    
    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(
            is_int($code) ? vsprintf(self::$messages[$code] ?? 'Unknown authentication error', $params) : $code,
            is_int($code) ? $code : 6000,
            $cause
        );
    }
}
