<?php

namespace App\Http\Requests\Auth;

/**
 * Shared validation for e-mail addresses that end up in MAIL headers
 * (password-reset flow).
 *
 * `email:rfc,strict` validates the address properly. The additional regex
 * rejects CR, LF and NUL outright as defence in depth against header
 * injection: Laravel 11 no longer receives framework security fixes (see the
 * security notes in docs/SECURITY.md), so we do not rely on one validator.
 * (\A...\z, not ^...$, because `$` would still match before a trailing "\n".)
 */
final class EmailField
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/\A[^\r\n\0]+\z/', 'email:rfc,strict'];
    }
}
