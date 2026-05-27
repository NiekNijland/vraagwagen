<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Severity of a flashed toast. Backing values match the Sonner client method
 * names (`toast.success(...)`) and the frontend `FlashToast['type']` union, so
 * the wire payload stays a plain string.
 */
enum ToastType: string
{
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
