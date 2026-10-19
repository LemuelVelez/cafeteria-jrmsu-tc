<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Customer subject-access export: never serialize a complete database/model row. */
final class CustomerDataExportService
{
    private const PROFILE_FIELDS = [
        'name', 'email', 'phone', 'address', 'privacy_consent_at',
        'email_verified_at', 'deletion_requested_at',
    ];
    private const ORDER_FIELDS = [
        'id', 'order_number', 'order_type', 'status', 'subtotal', 'discount',
        'delivery_fee', 'total', 'payment_method', 'payment_status',
        'delivery_address', 'notes', 'created_at', 'updated_at',
    ];
    private const ITEM_FIELDS = [
        'order_id', 'product_name', 'quantity', 'unit_price', 'addon_total',
        'line_total', 'addons_json', 'notes',
    ];
    private const PAYMENT_FIELDS = ['order_id', 'method', 'amount', 'status', 'paid_at'];
    private const REVIEW_FIELDS = [
        'order_id', 'product_id', 'rating', 'comment', 'is_visible', 'created_at',
    ];

    public function __construct(private readonly ?BaseConnection $connection = null)
    {
    }

    /** Used by every export query to make internal identifiers and secret fields opt-in. */
    public static function project(array $row, string $section): array
    {
        $fields = match ($section) {
            'profile' => self::PROFILE_FIELDS,
            'orders' => self::ORDER_FIELDS,
            'items' => self::ITEM_FIELDS,
            'payments' => self::PAYMENT_FIELDS,
            'reviews' => self::REVIEW_FIELDS,
            default => throw new \InvalidArgumentException('Invalid export section.'),
        };
        $public = array_intersect_key($row, array_flip($fields));
        foreach (match ($section) {
            'profile' => ['phone', 'address'],
            'orders' => ['delivery_address'],
            default => [],
        } as $field) {
            if (isset($public[$field])) {
                $public[$field] = FieldEncryption::open((string) $public[$field]);
            }
        }
        if ($section === 'items' && isset($public['addons_json'])) {
            $public['addons'] = json_decode((string) $public['addons_json'], true);
            unset($public['addons_json']);
        }
        return $public;
    }

    /**
     * Reads a consistent account snapshot, and audits the access on the same connection.
     * Every order-related read is scoped to the authenticated customer by SQL, not by UI.
     */
    public function export(int $customerId): ?array
    {
        if ($customerId < 1) {
            throw new \DomainException('Invalid account.');
        }
        $db = $this->connection ?? db_connect();
        $db->transBegin();
        try {
            $user = $db->table('users')->select(implode(',', self::PROFILE_FIELDS))
                ->where('id', $customerId)->where('role', 'customer')->get()->getRowArray();
            if (! $user) {
                $db->transRollback();
                return null;
            }
            $orders = $db->table('orders')->select(implode(',', self::ORDER_FIELDS))
                ->where('customer_id', $customerId)->orderBy('created_at', 'DESC')->get()->getResultArray();
            $items = $db->table('order_items i')
                ->select('i.' . implode(',i.', self::ITEM_FIELDS))
                ->join('orders o', 'o.id=i.order_id')
                ->where('o.customer_id', $customerId)->orderBy('i.id')->get()->getResultArray();
            $payments = $db->table('payments p')
                ->select('p.' . implode(',p.', self::PAYMENT_FIELDS))
                ->join('orders o', 'o.id=p.order_id')
                ->where('o.customer_id', $customerId)->orderBy('p.id')->get()->getResultArray();
            $reviews = $db->table('reviews')->select(implode(',', self::REVIEW_FIELDS))
                ->where('customer_id', $customerId)->orderBy('created_at', 'DESC')->get()->getResultArray();
            $result = [
                'profile' => self::project($user, 'profile'),
                'orders' => array_map(static fn (array $row): array => self::project($row, 'orders'), $orders),
                'items' => array_map(static fn (array $row): array => self::project($row, 'items'), $items),
                'payments' => array_map(static fn (array $row): array => self::project($row, 'payments'), $payments),
                'reviews' => array_map(static fn (array $row): array => self::project($row, 'reviews'), $reviews),
            ];
            (new AuditLogService($db))->record('personal_data_export', 'user', $customerId, [], $customerId);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to audit personal data export.');
            }
            $db->transCommit();
            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
