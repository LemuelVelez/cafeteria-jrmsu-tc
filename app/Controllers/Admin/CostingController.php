<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Services\CostingService;
use App\Services\AuditLogService;
use App\Models\ProductModel;

class CostingController extends BaseController
{
    public function index(): string
    {
        $db=db_connect(); $products=(new ProductModel())->withCategory();
        $service=new CostingService($db);
        foreach ($products as &$p) $p['costing']=$service->calculate((int)$p['id']);
        unset($p);
        return $this->render('admin/costing/index',[
            'title'=>'Food costing and nutrition', 'products'=>$products,
            'ingredients'=>$db->table('ingredients')->orderBy('name')->get()->getResultArray(),
            'recipes'=>$db->table('product_recipes')->get()->getResultArray(),
            'ingredientMovements'=>$db->table('ingredient_movements m')->select('m.*,i.name')->join('ingredients i','i.id=m.ingredient_id')->orderBy('m.id','DESC')->limit(30)->get()->getResultArray(),
        ]);
    }
    public function saveIngredient()
    {
        if (! $this->validate(['name'=>'required|max_length[160]','cost_per_unit'=>'required|decimal|greater_than_equal_to[0]','purchase_unit'=>'required|in_list[g,kg,ml,l,pcs]'])) return redirect()->back()->with('errors',$this->validator->getErrors());
        $db=db_connect();$db->transBegin();
        try {
            $name=trim((string)$this->request->getPost('name'));
            $prior=$db->table('ingredients')->where('name',$name)->get()->getRowArray();
            $density=filter_var($this->request->getPost('density_g_per_ml')??1,FILTER_VALIDATE_FLOAT);
            if($density===false || $density<=0 || $density>25)throw new \DomainException('Enter a valid liquid density (0 to 25 g/mL).');
            $gramsValue = $this->request->getPost('grams_per_piece');
            $gramsPerPiece = $gramsValue === null || $gramsValue === '' ? null : filter_var($gramsValue, FILTER_VALIDATE_FLOAT);
            if ($gramsPerPiece === false || ($gramsPerPiece !== null && ($gramsPerPiece <= 0 || $gramsPerPiece > 100000))) {
                throw new \DomainException('Grams per piece must be a positive measured value.');
            }
            $fields=['grams_per_piece'=>$gramsPerPiece,'name'=>$name,'cost_per_unit'=>(float)$this->request->getPost('cost_per_unit'),'purchase_unit'=>$this->request->getPost('purchase_unit'),
                'density_g_per_ml'=>(float)$density,
                'nutrient_basis'=>(string)($this->request->getPost('nutrient_basis') ?: '100g'),
                'supplier'=>trim((string)$this->request->getPost('supplier')),'source_reference'=>trim((string)$this->request->getPost('source_reference'))];
            if (! in_array($fields['nutrient_basis'], ['100g', 'unit'], true)) {
                throw new \DomainException('Nutrient basis must be 100g or unit.');
            }
            if ($fields['purchase_unit'] === 'pcs' && $fields['nutrient_basis'] === '100g'
                && $gramsPerPiece === null) {
                throw new \DomainException('A measured grams-per-piece value is required for 100g-based piece nutrition.');
            }
            foreach (['calories','protein_g','carbohydrates_g','fat_g','sugar_g','fiber_g','sodium_mg'] as $field) {
                $num=filter_var($this->request->getPost($field)??0,FILTER_VALIDATE_FLOAT);
                if ($num === false || $num < 0) throw new \DomainException('Invalid nutrient quantity.');
                $fields[$field]=$num;
            }
            if ($prior) $db->table('ingredients')->where('id',$prior['id'])->update($fields);
            else {$db->table('ingredients')->insert($fields);}
            $id=(int)($prior['id']??$db->insertID());
            if (! $prior || (float)$prior['cost_per_unit'] !== (float)$fields['cost_per_unit']) {
                $db->table('ingredient_cost_history')->insert(['ingredient_id'=>$id,'unit_cost'=>$fields['cost_per_unit'],'effective_at'=>date('Y-m-d H:i:s'),'changed_by'=>session()->get('user')['id']]);
            }
            foreach ($db->table('product_recipes')->where('ingredient_id',$id)->get()->getResultArray() as $recipe) (new CostingService($db))->refreshNutrition((int)$recipe['product_id']);
            (new AuditLogService($db))->recordChange('ingredient_saved','ingredient',$id,$prior??[],$fields);
            if (! $db->transStatus()) throw new \RuntimeException('Unable to save ingredient.');
            $db->transCommit();return redirect()->to('/admin/costing')->with('success','Ingredient saved and affected nutrition recalculated.');
        } catch (\Throwable $e) {$db->transRollback();return redirect()->to('/admin/costing')->with('error',$e->getMessage());}
    }
    public function stockIngredient()
    {
        $ingredientId=filter_var($this->request->getPost('ingredient_id'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $quantity=filter_var($this->request->getPost('quantity'),FILTER_VALIDATE_FLOAT);
        $note=trim((string)$this->request->getPost('note'));
        if($ingredientId===false||$quantity===false||$quantity<=0||$note==='')return redirect()->to('/admin/costing')->with('error','Valid ingredient, quantity, and reference required.');
        $db=db_connect();$db->transBegin();
        try {
            (new \App\Services\IngredientInventoryService($db))->stockIn((int)$ingredientId,(float)$quantity,(int)session()->get('user')['id'],$note);
            if (!$db->transStatus())throw new \RuntimeException('Ingredient stock-in failed.');
            $db->transCommit();return redirect()->to('/admin/costing')->with('success','Ingredient stock was received.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/costing')->with('error',$e->getMessage());}
    }
    public function saveRecipe()
    {
        $productId=(int)$this->request->getPost('product_id');$ingredientId=(int)$this->request->getPost('ingredient_id');
        $qty=filter_var($this->request->getPost('quantity'), FILTER_VALIDATE_FLOAT);
        $unit=(string)$this->request->getPost('unit');
        if ($productId<1||$ingredientId<1||$qty===false||$qty<=0||!in_array($unit,['g','kg','ml','l','pcs'],true)) return redirect()->to('/admin/costing')->with('error','Invalid recipe values.');
        $db=db_connect();$db->transBegin();
        try {
            $locked=$db->query('SELECT * FROM products WHERE id=? FOR UPDATE',[$productId])->getRowArray();
            $ingredient=$db->table('ingredients')->where('id',$ingredientId)->get()->getRowArray();
            if (!$locked||!$ingredient) throw new \DomainException('Missing product or ingredient.');
            CostingService::ingredientUnits((float)$qty,$unit,(string)$ingredient['purchase_unit'],
                (float)$ingredient['density_g_per_ml'], $ingredient['grams_per_piece']===null ? null : (float)$ingredient['grams_per_piece']);
            if ($unit === 'pcs' && $ingredient['nutrient_basis'] === '100g'
                && (!isset($ingredient['grams_per_piece']) || (float)$ingredient['grams_per_piece'] <= 0)) {
                throw new \DomainException('Enter measured grams per piece for 100g-based ingredient nutrition.');
            }
            $previous=$db->table('product_recipes')->where(['product_id'=>$productId,'ingredient_id'=>$ingredientId])->get()->getRowArray();
            $values=['product_id'=>$productId,'ingredient_id'=>$ingredientId,'quantity'=>$qty,'unit'=>$unit,'wastage_percent'=>min(100,max(0,(float)$this->request->getPost('wastage_percent')))];
            if ($previous) $db->table('product_recipes')->where('id',$previous['id'])->update($values);
            else $db->table('product_recipes')->insert($values);
            (new CostingService($db))->refreshNutrition($productId);
            (new AuditLogService($db))->recordChange('recipe_changed','product',$productId,$previous??[],$values);
            if (!$db->transStatus()) throw new \RuntimeException('Unable to save recipe.');
            $db->transCommit();return redirect()->to('/admin/costing')->with('success','Recipe saved; nutrition returned to Estimated.');
        } catch (\Throwable $e) {$db->transRollback();return redirect()->to('/admin/costing')->with('error',$e->getMessage());}
    }
    public function verify(int $productId)
    {
        $db=db_connect();$db->transBegin();
        try {
            $row=$db->query('SELECT * FROM products WHERE id=? FOR UPDATE',[$productId])->getRowArray();
            if (!$row) throw new \DomainException('Product not found.');
            if (!$db->table('product_recipes')->where('product_id',$productId)->countAllResults()) throw new \DomainException('A recipe is required before verification.');
            $sources = $db->table('product_recipes r')->select('i.name,i.source_reference')
                ->join('ingredients i', 'i.id=r.ingredient_id')->where('r.product_id', $productId)
                ->get()->getResultArray();
            foreach ($sources as $ingredient) {
                if (! \App\Services\SourcedRecipeCatalog::validReference(
                    (string) ($ingredient['source_reference'] ?? ''))) {
                    throw new \DomainException('Source reference required before verifying nutrition for: ' . $ingredient['name']);
                }
            }
            // This remains an explicit human sign-off; automatic recipe calculation
            // never changes nutrition_status to verified.
            $db->table('products')->where('id',$productId)->update(['nutrition_status'=>'verified','nutrition_verified_by'=>(int)session()->get('user')['id'],'nutrition_verified_at'=>date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('nutrition_verified','product',$productId,['source'=>'recipe']);
            $db->transCommit();return redirect()->to('/admin/costing')->with('success','Nutrition verified.');
        } catch (\Throwable $e){$db->transRollback();return redirect()->to('/admin/costing')->with('error',$e->getMessage());}
    }
}
