<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Services\NutritionLookupService;
use App\Services\AuditLogService;
use App\Services\CostingService;

final class NutritionLookupController extends BaseController
{
    public function index()
    {
        $query=trim((string)$this->request->getGet('q'));
        $rows=[];$error='';
        if($query!=='')try {$rows=(new NutritionLookupService())->search($query);}catch(\Throwable $e){$error=$e->getMessage();}
        return $this->render('admin/costing/nutrition_search', ['title'=>'USDA nutrition lookup','query'=>$query,'rows'=>$rows,'error'=>$error]);
    }

    public function import()
    {
        $fdcId=filter_var($this->request->getPost('fdc_id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $cost=filter_var($this->request->getPost('cost_per_unit'),FILTER_VALIDATE_FLOAT);
        $unit=(string)$this->request->getPost('purchase_unit');
        if($fdcId===false||$cost===false||$cost<0||$unit!=='g')return redirect()->to('/admin/costing/nutrition-search')->with('error','Import as grams with a valid cost per gram.');
        try {$food=(new NutritionLookupService())->details((int)$fdcId);}catch(\Throwable $e){return redirect()->to('/admin/costing/nutrition-search')->with('error',$e->getMessage());}
        $nutrients=NutritionLookupService::nutrients($food);
        $name=mb_substr(trim((string)($food['description']??'')),0,160);
        if(!$name)return redirect()->to('/admin/costing/nutrition-search')->with('error','The selected USDA food has no name.');
        $db=db_connect();$db->transBegin();
        try {
            $old=$db->query('SELECT * FROM ingredients WHERE name=? FOR UPDATE',[$name])->getRowArray();
            $fields=array_merge(['name'=>$name,'purchase_unit'=>'g','cost_per_unit'=>$cost,'nutrient_basis'=>'100g','source_reference'=>'USDA FoodData Central #'.(int)$fdcId],$nutrients);
            if($old)$db->table('ingredients')->where('id',$old['id'])->update($fields);
            else $db->table('ingredients')->insert($fields);
            $id=(int)($old['id']??$db->insertID());
            $db->table('ingredient_cost_history')->insert(['ingredient_id'=>$id,'unit_cost'=>$cost,'effective_at'=>date('Y-m-d H:i:s'),'changed_by'=>session()->get('user')['id']]);
            foreach($db->table('product_recipes')->where('ingredient_id',$id)->get()->getResultArray() as $recipe)(new CostingService($db))->refreshNutrition((int)$recipe['product_id']);
            (new AuditLogService($db))->recordChange('ingredient_usda_import','ingredient',$id,$old??[],$fields);
            if(!$db->transStatus())throw new \RuntimeException('Could not import ingredient.');
            $db->transCommit();return redirect()->to('/admin/costing')->with('success','USDA ingredient imported; associated nutrition estimates recalculated.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/costing/nutrition-search')->with('error',$e->getMessage());}
    }
}
