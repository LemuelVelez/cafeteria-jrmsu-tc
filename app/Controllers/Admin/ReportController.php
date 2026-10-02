<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\ReportService;

class ReportController extends BaseController
{
    private const TYPES = ['sales', 'orders', 'inventory', 'popular', 'deliveries'];

    public function index(): string
    {
        [$from, $to] = $this->dateRange();
        $active = (string) ($this->request->getGet('type') ?: 'sales');
        if (! in_array($active, self::TYPES, true)) $active = 'sales';
        $service = new ReportService();
        return $this->render('admin/reports/index', [
            'title' => 'Reports', 'from' => $from, 'to' => $to, 'active' => $active,
            'sales' => $service->sales($from, $to),
            'ordersReport' => $service->orders($from, $to),
            'inventoryReport' => $service->inventory($from, $to),
            'popularReport' => $service->popular($from, $to),
            'deliveryReport' => $service->deliveries($from, $to),
        ]);
    }

    public function export(string $type)
    {
        if (! in_array($type, self::TYPES, true)) return redirect()->to('/admin/reports')->with('error', 'Invalid report type.');
        [$from, $to] = $this->dateRange(false);
        $service = new ReportService();
        $data = match ($type) {
            'sales' => $service->sales($from, $to)['daily'],
            'orders' => $service->orders($from, $to)['rows'],
            'inventory' => $service->inventory($from, $to)['products'],
            'popular' => $service->popular($from, $to)['top'],
            'deliveries' => $service->deliveries($from, $to)['riders'],
        };
        $stream = fopen('php://temp', 'r+');
        if ($data !== []) {
            fputcsv($stream, array_keys($data[0]));
            foreach ($data as $row) fputcsv($stream, array_values($row));
        } else {
            fputcsv($stream, ['message']); fputcsv($stream, ['No records for selected period']);
        }
        rewind($stream); $csv = stream_get_contents($stream) ?: ''; fclose($stream);
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="'.$type.'-'.$from.'-to-'.$to.'.csv"')
            ->setBody($csv);
    }

    private function dateRange(bool $flash = true): array
    {
        $from = (string) ($this->request->getGet('from') ?: date('Y-m-01'));
        $to = (string) ($this->request->getGet('to') ?: date('Y-m-d'));
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
