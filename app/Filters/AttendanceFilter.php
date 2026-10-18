<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class AttendanceFilter implements FilterInterface
{
    public function before(RequestInterface $request,$arguments=null)
    {
        $flag=db_connect()->table('settings')->where('setting_key','attendance_enabled')->get()->getRow('setting_value');
        if((string)$flag!=='1')return service('response')->setStatusCode(404)->setBody('Not found');
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null):void{}
}
