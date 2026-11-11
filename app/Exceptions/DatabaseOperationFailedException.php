<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DatabaseOperationFailedException extends RuntimeException
{
    private const SAFE_MESSAGE = 'Terjadi kesalahan saat memproses data. Silakan coba beberapa saat lagi.';

    public function __construct(Throwable $previous)
    {
        parent::__construct('A database operation failed.', previous: $previous);
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => self::SAFE_MESSAGE], 500);
        }

        return response(
            '<!doctype html><html lang="id"><meta charset="utf-8"><title>Kesalahan server</title>'
                .'<body><main><h1>Kesalahan server</h1><p>'.e(self::SAFE_MESSAGE).'</p></main></body></html>',
            500,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
