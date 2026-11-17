<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/** Single source of truth for account field rules (mirrored in frontend/src/lib/validation.ts). */
class AuthRules
{
    public const NAME_MIN = 2;
    public const NAME_MAX = 100;
    public const EMAIL_MAX = 255;
    public const PASSWORD_MIN = 8;
    public const PASSWORD_MAX = 72; // bcrypt ignores bytes beyond 72

    public static function name(): array
    {
        return ['required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX, 'regex:/^[\pL\pM\s\'\.\-]+$/u'];
    }

    public static function email(): array
    {
        return ['required', 'string', 'email:rfc,strict', 'max:'.self::EMAIL_MAX];
    }

    public static function newPassword(): array
    {
        return [
            'required', 'string', 'confirmed', 'max:'.self::PASSWORD_MAX,
            Password::min(self::PASSWORD_MIN)->letters()->numbers(),
        ];
    }
}
