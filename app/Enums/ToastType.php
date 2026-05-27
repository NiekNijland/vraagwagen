<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backing values must match the Sonner client method names and frontend `FlashToast['type']` union.
 */
enum ToastType: string
{
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
