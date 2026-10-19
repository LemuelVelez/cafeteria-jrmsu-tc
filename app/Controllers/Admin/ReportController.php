<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\ReportService;

class ReportController extends BaseController
{
    private const TYPES = ['sales', 'orders', 'inventory', 'popular', 'deliveries', 'profit', 'lowstock', 'audit', 'attendance'];

    private function isAttendanceEnabled(): bool
    {
        return (string)(db_connect()->table('settings')->where('setting_key','attendance_enabled')->get()->getRow('setting_value')??'0') === '1';
    }

    public function index(): string
    {
        [$from, $to] = $this->dateRange();
        $active = (string) ($this->request->getGet('type') ?: 'sales');
        if (! in_array($active, self::TYPES, true) || ($active==='attendance' && !$this->isAttendanceEnabled())) $active = 'sales';
        $service = new ReportService();
        // Run only the selected report; historical orders/ledger exports can be large.
        $data = [
            'title' => 'Reports', 'from' => $from, 'to' => $to, 'active' => $active,
            'sales' => [], 'ordersReport' => [], 'inventoryReport' => [],
            'popularReport' => [], 'deliveryReport' => [], 'profitReport' => [],
            'lowStockReport' => [], 'auditReport' => [], 'attendanceReport' => [],
            'attendanceEnabled' => $this->isAttendanceEnabled(),
        ];
        switch ($active) {
            case 'sales': $data['sales'] = $service->sales($from,$to); break;
            case 'orders': $data['ordersReport'] = $service->orders($from,$to); break;
            case 'inventory': $data['inventoryReport'] = $service->inventory($from,$to); break;
            case 'popular': $data['popularReport'] = $service->popular($from,$to); break;
            case 'deliveries': $data['deliveryReport'] = $service->deliveries($from,$to); break;
            case 'profit': $data['profitReport'] = $service->profit($from,$to); break;
            case 'lowstock': $data['lowStockReport'] = $service->lowStock($from,$to); break;
            case 'audit': $data['auditReport'] = $service->audit($from,$to); break;
            case 'attendance': $data['attendanceReport'] = $service->attendance($from,$to); break;
        }
        return $this->render('admin/reports/index', $data);
    }

    public function export(string $type)
    {
        if (! in_array($type, self::TYPES, true) || ($type==='attendance' && !$this->isAttendanceEnabled())) return redirect()->to('/admin/reports')->with('error', 'Invalid report type.');
        [$from, $to] = $this->dateRange(false);
        $service = new ReportService();
        $summary = [];
        $data = match ($type) {
            'sales' => (static function () use ($service, $from, $to): array {
                $sales = $service->sales($from, $to);
                $rows = array_map(static fn (array $day): array => [
                    'metric' => $day['sale_date'], 'orders' => $day['orders'], 'amount' => $day['total'],
                ], $sales['daily']);
                foreach (['gross_sales','discounts','delivery_fees','net_sales'] as $metric) {
                    $rows[] = ['metric' => $metric, 'orders' => '', 'amount' => $sales['summary'][$metric]];
                }
                return $rows;
            })(),
            'orders' => (static function () use ($service, $from, $to, &$summary): array {
                $report = $service->orders($from, $to);
                $summary = [
                    'orders' => count($report['rows']),
                    'cancellation_rate_percent' => $report['cancellationRate'],
                    'issue_rate_percent' => $report['issueRate'],
                    'average_preparation_minutes' => $report['averagePreparationMinutes'],
                ];
                return array_map(static fn (array $row): array => array_intersect_key($row, array_flip([
                    'order_number','created_at','order_type','status','payment_method','payment_status',
                    'subtotal','discount','delivery_fee','total',
                ])), $report['rows']);
            })(),
            'inventory' => (static function () use ($service,$from,$to): array {
                $report = $service->inventory($from,$to);
                return array_map(static function (array $p) use ($report): array {
                    $m = $report['movements'][(int)$p['id']] ?? [];
                    return ['product'=>$p['name'], 'opening_stock'=>$p['opening_stock'],
                        'stock_in'=>$m['stock_in']??0,'sold'=>abs($m['sale']??0),
                        'restocked'=>$m['cancel_restock']??0,'adjustments'=>$m['adjustment']??0,
                        'waste'=>abs($m['waste']??0),'closing_stock'=>$p['closing_stock'],
                        'current_stock'=>$p['stock'],'reorder_level'=>$p['reorder_level'],
                        'stock_value'=>$p['stock_value']];
                },$report['products']);
            })(),
            'popular' => (static function () use ($service,$from,$to): array {
                $p=$service->popular($from,$to);
                return array_merge(array_map(static fn($x)=>['group'=>'top']+$x,$p['top']),
                    array_map(static fn($x)=>['group'=>'slow']+$x,$p['slow']));
            })(),
            'deliveries' => (static function () use ($service, $from, $to, &$summary): array {
                $report = $service->deliveries($from, $to);
                $summary = ['average_delivery_minutes' => $report['averageDeliveryMinutes']];
                return $report['riders'];
            })(),
            'profit' => (static function () use ($service, $from, $to, &$summary): array {
                $report = $service->profit($from, $to);
                // Export exactly the on-screen totals, including uncosted sales.
                $summary = [
                    'gross_item_revenue' => $report['revenue'],
                    'order_discounts' => $report['discounts'],
                    'delivery_fees' => $report['deliveryFees'],
                    'paid_net_sales' => $report['netSales'],
                    'costed_item_cogs' => $report['cost'],
                    'costed_item_gross_profit' => $report['profit'],
                    'uncosted_item_net_revenue' => $report['uncostedRevenue'],
                    'costed_food_cost_percent' => $report['foodCostPercent'] ?? 'N/A',
                    'costed_margin_percent' => $report['marginPercent'] ?? 'N/A',
                    'target_food_cost_percent' => $report['targetFoodCostPercent'],
                ];
                return $report['rows'];
            })(),
            'lowstock' => $service->lowStock($from,$to),
            'audit' => $service->audit($from,$to),
            'attendance' => $service->attendance($from,$to),
        };
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF"); // UTF-8 BOM for spreadsheet software.
        self::putCsv($stream, ['Report', ucfirst($type)]);
        self::putCsv($stream, ['Date range', $from . ' to ' . $to]);
        self::putCsv($stream, ['Generated at', date('Y-m-d H:i:s') . ' Asia/Manila']);
        self::putCsv($stream, ['Generated by', (string) (session()->get('user')['name'] ?? 'Staff')]);
        self::putCsv($stream, []);
        if ($data !== []) {
            self::putCsv($stream, array_keys($data[0]));
            foreach ($data as $row) self::putCsv($stream, array_values($row));
        } else {
            self::putCsv($stream, ['No records for selected period']);
        }
        if ($summary !== []) {
            self::putCsv($stream, []);
            self::putCsv($stream, ['Summary metric', 'Value']);
            foreach ($summary as $name => $value) {
                self::putCsv($stream, [$name, $value]);
            }
        }
        rewind($stream); $csv = stream_get_contents($stream) ?: ''; fclose($stream);
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="'.$type.'-'.$from.'-to-'.$to.'.csv"')
            ->setBody($csv);
    }

    /** Quoting is not enough: Excel interprets several leading characters as formulas. */
    public static function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'" . $value : $value;
    }

    private static function putCsv($stream, array $cells): void
    {
        fputcsv($stream, array_map([self::class, 'safeCsvCell'], $cells), ',', '"', '');
    }

    private function dateRange(bool $flash = true): array
    {
        $quick=(string)$this->request->getGet('quick');
        $start=match($quick){'today'=>date('Y-m-d'),'week'=>date('Y-m-d',strtotime('monday this week')),'month'=>date('Y-m-01'),default=>null};
        $from = (string) ($start ?? $this->request->getGet('from') ?: date('Y-m-01'));
        $to = (string) ($start !== null ? date('Y-m-d') : ($this->request->getGet('to') ?: date('Y-m-d')));
        if (! $this->isValidDate($from) || ! $this->isValidDate($to) || $from > $to) {
            $from = date('Y-m-01'); $to = date('Y-m-d');
            if ($flash) session()->setFlashdata('error', 'Invalid report date range. The current month is shown instead.');
        }
        return [$from, $to];
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false && (!is_array($errors) || ($errors['warning_count']===0 && $errors['error_count']===0)) && $date->format('Y-m-d') === $value;
    }
}
