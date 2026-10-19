<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

/** Source-based checks work even when the application database is unavailable. */
final class RouteCoverageTest extends TestCase
{
    public function testCriticalRoutesAndProtectedRoleGroups(): void
    {
        $s=file_get_contents(dirname(__DIR__,2).'/app/Config/Routes.php');
        foreach(['notifications','products/barcode/(:segment)','orders/(:num)/reorder','products/labels',
            'inventory/stock-in','inventory/stock-out','inventory/count','audit-logs','reports/export/(:segment)',
            'customers/(:num)/reveal-phone','queue-board','menu','privacy','backups','costing','attendance'] as $endpoint) {
            self::assertStringContainsString("'".$endpoint."'",$s);
        }
        foreach(['admin','cashier','rider','customer','staff','api','attendance','admin/attendance'] as $prefix) {
            self::assertMatchesRegularExpression('~\$routes->group\(\x27'.preg_quote($prefix,'~').'\x27,\s*\[\x27filter\x27\s*=>\s*\[\x27auth\x27,\s*\x27active\x27,\s*\x27role:~',$s);
        }
    }
    public function testHardCodedViewPathsHaveMatchingRoutePrefixes(): void
    {
        $root=dirname(__DIR__,2);$routes=file_get_contents($root.'/app/Config/Routes.php');
        $paths=[]; $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/app/Views'));
        foreach($iterator as $file) {
            if(!$file->isFile()||$file->getExtension()!=='php')continue;
            preg_match_all('~base_url\(\s*[\x27\x22]([a-zA-Z0-9/_-]+)[\x27\x22]~',file_get_contents($file->getPathname()),$matches);
            foreach($matches[1] as $link) if($link!=='')$paths[$link]=true;
        }
        foreach(array_keys($paths) as $link) {
            $first=explode('/',$link)[0];
            if(in_array($first,['admin','cashier','rider','customer','staff','api','settings','attendance','menu','privacy','notifications','login','register','logout','forgot-password','reset-password','email-verification','verify-email'],true)) {
                self::assertTrue(str_contains($routes,"'".$first."'")||str_contains($routes,"'".$link."'"),$link);
            }
        }
    }
}
