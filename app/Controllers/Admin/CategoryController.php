<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use Throwable;
use App\Services\AuditLogService;

class CategoryController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/categories/index', [
            'categories' => (new CategoryModel())->orderBy('sort_order')->findAll(),
        ]);
    }

    public function save(?int $id = null)
    {
        $model = new CategoryModel();
        if ($id !== null && ! $model->find($id)) {
            return redirect()->to('/admin/categories')->with('error', 'Category not found.');
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => url_title((string) $this->request->getPost('name'), '-', true),
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order' => $this->request->getPost('sort_order') ?: 0,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $db=db_connect();$db->transBegin();
        try {
            $before=$id!==null?$db->query('SELECT * FROM categories WHERE id=? FOR UPDATE',[$id])->getRowArray():[];
            if($id!==null && !$before)throw new \DomainException('Record not found.');
            $ok=$id!==null?$model->update($id,$data):$model->insert($data,true);
            if(!$ok)throw new \DomainException(implode(' ',$model->errors())?:'Invalid record.');
            $savedId=(int)($id??$ok);
            $after=$model->find($savedId)?:$data;
            (new AuditLogService($db))->recordChange('category_saved','category',$savedId,$before?:[],$after);
            if(!$db->transStatus())throw new \RuntimeException('Database transaction failed.');
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Category save failed for {id}', ['id'=>(int)($id??0)]);
            return redirect()->to('/admin/categories')->withInput()->with('error', $exception->getMessage());
        }

        return $ok
            ? redirect()->to('/admin/categories')->with('success', 'Category saved.')
            : redirect()->to('/admin/categories')->withInput()->with('errors', $model->errors());
    }

    public function delete(int $id)
    {
        $model = new CategoryModel();
        if (! $model->find($id)) {
            return redirect()->to('/admin/categories')->with('error', 'Category not found.');
        }

        $db=db_connect();$db->transBegin();
        try {
            $before=$db->query('SELECT * FROM categories WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if(!$before)throw new \DomainException('Category not found.');
            $deleted=$model->delete($id);
            if(!$deleted)throw new \RuntimeException('Category removal failed.');
            (new AuditLogService($db))->record('category_deleted','category',$id,['category_id'=>$id]);
            $db->transCommit();
        }catch(Throwable $exception){$db->transRollback();$deleted=false;}

        return $deleted
            ? redirect()->to('/admin/categories')->with('success', 'Category removed.')
            : redirect()->to('/admin/categories')->with('error', 'The category could not be removed.');
    }
}
