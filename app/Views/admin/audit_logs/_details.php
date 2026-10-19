<?php
/** Shared, escaped, compact JSON details for the audit screen and reports. */
$rawDetails = trim((string) ($detailsJson ?? ''));
$decoded = json_decode($rawDetails, true);
$entries = [];
$formatValue = static function ($value): string {
    if ($value === null) return 'null';
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_scalar($value)) return (string) $value;
    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
};
if (is_array($decoded)) {
    foreach ($decoded as $key => $value) {
        if ($key === 'changes' && is_array($value)) {
            foreach ($value as $field => $change) {
                if (is_array($change) && (array_key_exists('before', $change) || array_key_exists('after', $change))) {
                    $entries[] = [(string) $field, $formatValue($change['before'] ?? null) . ' → ' . $formatValue($change['after'] ?? null)];
                } else {
                    $entries[] = [(string) $field, $formatValue($change)];
                }
            }
        } else {
            $entries[] = [(string) $key, $formatValue($value)];
        }
    }
} elseif ($rawDetails !== '') {
    $entries[] = ['Details', $rawDetails];
}
?>
<?php if ($entries): ?>
<div class="audit-details">
    <?php foreach ($entries as [$detailKey, $detailValue]): ?>
        <?php $isCode = (bool) preg_match('/(?:hash|sha|checksum|token|uuid|id|signature)/i', $detailKey) || (bool) preg_match('/\b[a-f0-9]{32,}\b/i', $detailValue); ?>
        <div class="audit-detail-row">
            <span class="audit-detail-key" title="<?= esc($detailKey, 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $detailKey))) ?></span>
            <span class="audit-detail-value<?= $isCode ? ' audit-detail-code' : '' ?>" title="<?= esc($detailValue, 'attr') ?>"><?= esc($detailValue) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<span class="text-secondary small">—</span>
<?php endif; ?>
