<?php

use App\Enums\OrderType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\SettingModel;

if (! function_exists('format_price')) {
    function format_price(float|int|string|null $amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}



if (! function_exists('payment_status_badge')) {
    /** Includes text and payment method; never rely only on badge color. */
    function payment_status_badge(string $status, string $method): string
    {
        $variant = match ($status) {
            'paid' => 'success', 'failed' => 'danger', default => 'warning',
        };
        $label = match ($status) {
            'paid' => 'Paid', 'failed' => 'Failed', default => 'Pending',
        };
        return '<span class="badge text-bg-' . $variant . '">' . esc($label)
            . ' · ' . esc(payment_method_label($method)) . '</span>';
    }
}

if (! function_exists('product_fallback_image')) {
    /** @return array{url:string,alt:string} */
    function product_fallback_image(array $product): array
    {
        $catalog = config(\Config\MenuImages::class);
        $slug = strtolower(trim((string) ($product['slug'] ?? '')));
        $category = (string) ($product['category_name'] ?? '');
        $entry = $catalog->products[$slug] ?? $catalog->categories[$category] ?? null;
        return ['url' => $entry['url'] ?? base_url('assets/img/jrmsu-cafeteria-logo.webp'),
                'alt' => $entry['alt'] ?? (string) ($product['name'] ?? 'Cafeteria product')];
    }
}

if (! function_exists('product_image')) {
    /** @return array{url:string,alt:string,fallback:string} */
    function product_image(array $product): array
    {
        $catalog = config(\Config\MenuImages::class);
        $category = $catalog->categories[(string) ($product['category_name'] ?? '')] ?? null;
        $logo = base_url('assets/img/jrmsu-cafeteria-logo.webp');
        $fallback = $category['url'] ?? $logo;
        $stored = trim((string) ($product['image'] ?? ''));
        if ($stored !== '') {
            return ['url' => media_url($stored), 'alt' => (string) ($product['name'] ?? 'Menu product'), 'fallback' => $fallback];
        }
        $catalogImage = $catalog->products[strtolower((string) ($product['slug'] ?? ''))] ?? $category;
        return ['url' => $catalogImage['url'] ?? $logo,
                'alt' => $catalogImage['alt'] ?? (string) ($product['name'] ?? 'Menu product'),
                'fallback' => $fallback];
    }
}

if (! function_exists('format_queue_number')) {
    function format_queue_number(?int $number, ?string $fallback = null): string
    {
        if ($number === null) return $fallback ?? '';
        $prefix = (string) (new SettingModel())->getValue('queue_number_prefix', '');
        return $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('payment_modes')) {
    /**
     * @return array<string, array{value: string, label: string}>
     */
    function payment_modes(): array
    {
        $modes = [];
        foreach (OrderType::cases() as $orderType) {
            $paymentMethod = PaymentMethod::forOrderType($orderType);
            $modes[$orderType->value] = [
                'value' => $paymentMethod->value,
                'label' => $paymentMethod->label(),
            ];
        }

        return $modes;
    }
}

if (! function_exists('payment_method_label')) {
    function payment_method_label(string $method): string
    {
        return PaymentMethod::tryFrom($method)?->label() ?? ucwords(str_replace('_', ' ', $method));
    }
}

if (! function_exists('generate_order_number')) {
    function generate_order_number(): string
    {
        $prefix = (string) env('CAFETERIA_ORDER_PREFIX', 'JRMSU');
        return sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }
}

if (! function_exists('order_status_badge')) {
    function order_status_badge(string $status): string
    {
        $orderStatus = OrderStatus::tryFrom($status);
        if (! $orderStatus) {
            return '<span class="order-status-badge order-status-badge--neutral"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' . esc(ucwords(str_replace('_', ' ', $status))) . '</span></span>';
        }

        return '<span class="order-status-badge order-status-badge--' . esc($orderStatus->badgeVariant(), 'attr') . '">'
            . '<i class="bi ' . esc($orderStatus->icon(), 'attr') . '" aria-hidden="true"></i>'
            . '<span>' . esc($orderStatus->label()) . '</span>'
            . '</span>';
    }
}


if (! function_exists('mask_phone')) {
    function mask_phone(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') return '—';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) return str_repeat('*', max(1, strlen($phone)));
        return substr($digits, 0, min(4, strlen($digits) - 2)) . ' *** **' . substr($digits, -2);
    }
}

if (! function_exists('mask_address')) {
    function mask_address(?string $address): string
    {
        $address = trim((string) $address);
        if ($address === '') return '—';
        $parts = preg_split('/[,\n]/', $address) ?: [];
        $last = trim((string) end($parts));
        return 'Address hidden after delivery' . ($last !== '' ? ' · ' . $last : '');
    }
}

if (! function_exists('time_ago')) {
    function time_ago(string|null $datetime): string
    {
        if (! $datetime) {
            return '—';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }
        $seconds = time() - $timestamp;
        if ($seconds < 60) {
            return 'just now';
        }
        $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
        foreach ($units as $value => $label) {
            if ($seconds >= $value) {
                $count = (int) floor($seconds / $value);
                return $count . ' ' . $label . ($count === 1 ? '' : 's') . ' ago';
            }
        }
        return 'just now';
    }
}

if (! function_exists('store_setting')) {
    function store_setting(string $key, mixed $default = null): mixed
    {
        static $settings = [];
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        try {
            $row = (new SettingModel())->where('setting_key', $key)->first();
            return $settings[$key] = $row['setting_value'] ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }
}


if (! function_exists('media_url')) {
    function media_url(?string $path): string
    {
        static $mediaStorage;

        $mediaStorage ??= new \App\Services\MediaStorageService();

        return $mediaStorage->url($path);
    }
}

if (! function_exists('user_avatar_url')) {
    function user_avatar_url(?string $path): string
    {
        return $path ? media_url($path) : base_url('assets/img/jrmsu-cafeteria-logo.webp');
    }
}

if (! function_exists('role_home')) {
    function role_home(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/dashboard',
            'cashier' => '/cashier/dashboard',
            'rider' => '/rider/dashboard',
            default => '/customer/dashboard',
        };
    }
}
