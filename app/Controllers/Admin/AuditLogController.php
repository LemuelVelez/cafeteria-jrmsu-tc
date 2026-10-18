<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\AuditLogModel;

class AuditLogController extends BaseController
{
    public function index()
    {
        $model=new AuditLogModel();
        $query=$this->request->getGet();
        $filters=[
            'action'=>trim((string)($query['action']??'')),
            'entity'=>trim((string)($query['entity']??'')),
            'role'=>trim((string)($query['role']??'')),
            'user_id'=>(int)($query['user_id']??0),
            'from'=>trim((string)($query['from']??'')),
            'to'=>trim((string)($query['to']??'')),
        ];
        if (!in_array($filters['role'],['','admin','cashier','rider','customer'],true)) $filters['role']='';
        foreach (['from','to'] as $k) if ($filters[$k]!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$k])) $filters[$k]='';
        if ($filters['from']!=='' && $filters['to']!=='' && $filters['from']>$filters['to']) return redirect()->to('/admin/audit-logs')->with('error','Invalid date range.');
        $builder=$model->select('audit_logs.*, users.name AS user_name, users.role AS user_role')->join('users','users.id=audit_logs.user_id','left');
        if ($filters['action']!=='')$builder->where('audit_logs.action',$filters['action']);
        if ($filters['entity']!=='')$builder->where('audit_logs.entity_type',$filters['entity']);
        if ($filters['role']!=='')$builder->where('users.role',$filters['role']);
        if ($filters['user_id']>0)$builder->where('audit_logs.user_id',$filters['user_id']);
        if ($filters['from']!=='')$builder->where('audit_logs.created_at >=',$filters['from'].' 00:00:00');
        if ($filters['to']!=='')$builder->where('audit_logs.created_at <=',$filters['to'].' 23:59:59');
        if ((string)$this->request->getGet('format')==='csv') {
            $handle=fopen('php://temp','w+');
            fputcsv($handle,['Date','Actor','Role','Action','Entity','Entity ID','Changes','IP']);
            foreach ($builder->orderBy('audit_logs.id','DESC')->findAll(20000) as $row) {
                fputcsv($handle,[$row['created_at'],$row['user_name'],$row['user_role'],$row['action'],$row['entity_type'],$row['entity_id'],$row['details_json'],$row['ip_address']]);
            }
            rewind($handle);$csv=stream_get_contents($handle);fclose($handle);
            return $this->response->setHeader('Content-Type','text/csv; charset=UTF-8')->setHeader('Content-Disposition','attachment; filename="audit-logs.csv"')->setBody($csv?:'');
        }
        return $this->render('admin/audit_logs/index', [
            'title'=>'Audit Logs','logs'=>$builder->orderBy('audit_logs.id','DESC')->paginate(30),
            'pager'=>$model->pager,'filters'=>$filters,'userId'=>$filters['user_id'],'action'=>$filters['action'],
        ]);
    }
}
