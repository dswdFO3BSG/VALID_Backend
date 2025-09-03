<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class PasswordDecryptionService
{
    // Use the same secret key as frontend - in production, store in .env file
    private static $secretKey = 'VALID_System_2024_SecretKey';

    /**
     * Decrypt password received from frontend
     * 
     * @param string $encryptedPassword - The encrypted password from frontend
     * @return string - The decrypted plain text password
     */
    public static function decryptPassword($encryptedPassword)
    {
        try {
            // Using Laravel's built-in encryption (recommended)
            // Note: This requires the encrypted password to be created with Laravel's Crypt facade
            // return Crypt::decryptString($encryptedPassword);
            
            // Using OpenSSL for AES decryption to match frontend crypto-js
            $key = hash('sha256', self::$secretKey, true);
            $data = base64_decode($encryptedPassword);
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                throw new \Exception('Decryption failed');
            }
            
            return $decrypted;
            
        } catch (\Exception $e) {
            throw new \Exception('Password decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Alternative decryption using Base64 decoding (simpler)
     * Use this if AES decryption causes issues
     * 
     * @param string $encodedPassword - The base64 encoded password from frontend
     * @return string - The decoded plain text password
     */
    public static function decryptPasswordBase64($encodedPassword)
    {
        try {
            $decoded = base64_decode($encodedPassword);
            if ($decoded === false) {
                throw new \Exception('Base64 decoding failed');
            }
            return $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Password decoding failed: ' . $e->getMessage());
        }
    }

    /**
     * Simple method using Laravel's built-in encryption
     * This is the most secure and recommended approach
     * 
     * @param string $encryptedPassword - Password encrypted with Laravel Crypt
     * @return string - The decrypted password
     */
    public static function decryptPasswordLaravel($encryptedPassword)
    {
        try {
            return Crypt::decryptString($encryptedPassword);
        } catch (\Exception $e) {
            throw new \Exception('Laravel decryption failed: ' . $e->getMessage());
        }
    }
}
