<?php

namespace Platform\Auth;

class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    /**
     * Genere un secret aleatoire encode en Base32.
     */
    public static function genererSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);
        return self::base32Encode($bytes);
    }

    /**
     * Genere le code TOTP courant pour un secret donne.
     */
    public static function genererCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, self::PERIOD);
        $key = self::base32Decode($secret);
        $counterBytes = pack('J', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifie un code TOTP avec une fenetre de tolerance.
     */
    public static function verifier(string $secret, string $code, int $fenetre = 1): bool
    {
        $timestamp = time();
        for ($i = -$fenetre; $i <= $fenetre; $i++) {
            if (hash_equals(self::genererCode($secret, $timestamp + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Genere l'URI otpauth:// pour le QR code.
     */
    public static function genererUri(string $secret, string $email, string $issuer = 'LeLab SEO'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= $alphabet[bindec($chunk)];
        }
        return $result;
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(strtoupper($data)) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $result .= chr(bindec($byte));
        }
        return $result;
    }
}
