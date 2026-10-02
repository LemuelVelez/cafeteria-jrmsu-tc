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



if (! function_exists('product_fallback_image')) {
    /**
     * Return an accurate fallback menu image when a product has no uploaded image.
     * Uploaded product images must always take priority in the view.
     *
     * @param array<string, mixed> $product
     * @return array{url: string, alt: string}
     */
    function product_fallback_image(array $product): array
    {
        $name = strtolower(trim((string) ($product['name'] ?? '')));
        $slug = strtolower(trim((string) ($product['slug'] ?? '')));
        $category = trim((string) ($product['category_name'] ?? ''));

        $specific = [
            'chicken-adobo-rice' => [
                'url' => 'https://images.unsplash.com/photo-1538974463296-4d76476ffeb2?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Chicken adobo served with steamed rice',
            ],
            'pork-sisig-rice' => [
                'url' => 'https://images.unsplash.com/photo-1673518415903-c243d36cd269?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Pork sisig served hot on a plate',
            ],
            'fried-chicken-meal' => [
                'url' => 'https://images.unsplash.com/photo-1580835267448-536d00f9cd4c?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Fried chicken served with rice',
            ],
            'cheese-burger' => [
                'url' => 'https://images.unsplash.com/photo-1568732547279-42ecb5239cbf?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Cheeseburger served with French fries',
            ],
            'crispy-fries' => [
                'url' => 'https://images.unsplash.com/photo-1529259266118-cf22737f713f?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Golden crispy French fries',
            ],
            'iced-spanish-latte' => [
                'url' => 'https://images.unsplash.com/photo-1645243370554-62fa18b1485b?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Iced milk coffee served in a glass',
            ],
            'hot-americano' => [
                'url' => 'https://images.unsplash.com/photo-1771250852008-eb5a5d124165?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Freshly brewed hot Americano coffee',
            ],
            'calamansi-juice' => [
                'url' => 'https://images.unsplash.com/photo-1507281549113-040fcfef650e?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Chilled citrus juice with fresh citrus slices',
            ],
        ];

        $key = $slug !== '' ? $slug : str_replace(' ', '-', preg_replace('/[^a-z0-9 ]+/', '', $name) ?? $name);
        if (isset($specific[$key])) {
            return $specific[$key];
        }

        $keywordMatches = [
            'adobo' => $specific['chicken-adobo-rice'],
            'sisig' => $specific['pork-sisig-rice'],
            'fried chicken' => $specific['fried-chicken-meal'],
            'burger' => $specific['cheese-burger'],
            'fries' => $specific['crispy-fries'],
            'spanish latte' => $specific['iced-spanish-latte'],
            'americano' => $specific['hot-americano'],
            'calamansi' => $specific['calamansi-juice'],
        ];
        foreach ($keywordMatches as $keyword => $image) {
            if ($name !== '' && str_contains($name, $keyword)) {
                return $image;
            }
        }

        $categoryFallbacks = [
            'Rice Meals' => [
                'url' => 'https://images.unsplash.com/photo-1625477811233-044633d10dd1?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Rice meal served in the cafeteria',
            ],
            'Snacks' => [
                'url' => 'https://images.unsplash.com/photo-1537495988501-f9cd94a78f3e?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Assorted cafeteria snacks',
            ],
            'Coffee' => [
                'url' => 'https://images.unsplash.com/photo-1527156231393-7023794f363c?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Coffee drink served in the cafeteria',
            ],
            'Cold Drinks' => [
                'url' => 'https://images.unsplash.com/photo-1497534446932-c925b458314e?fm=webp&q=75&w=800&h=600&fit=crop',
                'alt' => 'Chilled fruit drinks served with ice',
            ],
        ];

        return $categoryFallbacks[$category] ?? $categoryFallbacks['Rice Meals'];
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
