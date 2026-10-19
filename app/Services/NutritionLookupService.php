<?php
namespace App\Services;
use CodeIgniter\Database\BaseConnection;

/** Public USDA FoodData Central DEMO_KEY is used only by the server; cache supports offline operation. */
final class NutritionLookupService
{
    private const ROOT='https://api.nal.usda.gov/fdc/v1/';
    public function __construct(private readonly ?BaseConnection $connection=null) {}
    private function db(): BaseConnection {return $this->connection ?? db_connect();}

    public function search(string $query): array
    {
        $query=mb_substr(trim($query),0,80);
        if (mb_strlen($query)<2)throw new \DomainException('Enter at least two characters.');
        $key='search:'.hash('sha256',mb_strtolower($query));
        $result=$this->fetch('foods/search', ['query'=>$query,'pageSize'=>15],$key);
        $foods=$result['foods']??[];
        if (!is_array($foods))return [];
        return array_map(static fn(array $food):array => [
            'fdcId'=>(int)($food['fdcId']??0),
            'description'=>mb_substr((string)($food['description']??''),0,220),
            'dataType'=>(string)($food['dataType']??'Unknown'),
        ],array_filter($foods,'is_array'));
    }

    public function details(int $fdcId): array
    {
        if($fdcId<1)throw new \DomainException('Invalid FoodData Central ID.');
        $data=$this->fetch('food/'.$fdcId, [],'food:'.$fdcId);
        if((int)($data['fdcId']??0)!==$fdcId)throw new \RuntimeException('FoodData Central food ID did not match.');
        return $data;
    }

    /** Normalize FDC nutrient numbers (not user-submitted nutrition values). */
    public static function nutrients(array $food): array
    {
        $map=['208'=>'calories','203'=>'protein_g','205'=>'carbohydrates_g','204'=>'fat_g','269'=>'sugar_g','291'=>'fiber_g','307'=>'sodium_mg',
            '1008'=>'calories','1003'=>'protein_g','1005'=>'carbohydrates_g','1004'=>'fat_g','2000'=>'sugar_g','1079'=>'fiber_g','1093'=>'sodium_mg'];
        $result = [];
        foreach (($food['foodNutrients'] ?? []) as $row) {
            if (! is_array($row)) continue;
            $nutrient = $row['nutrient'] ?? $row;
            if (! is_array($nutrient)) continue;
            $number = (string) ($nutrient['number'] ?? $nutrient['nutrientNumber'] ?? $nutrient['id'] ?? '');
            $field = $map[$number] ?? null;
            if ($field === null) continue;
            // An omitted nutrient is unknown, not automatically zero. Never insert
            // an invented zero or claim the source reported a value it did not.
            $raw = $row['amount'] ?? $row['value'] ?? null;
            if (! is_numeric($raw) || ! is_finite((float) $raw) || (float) $raw < 0) {
                throw new \DomainException('Invalid USDA nutrient value for ' . $field . '.');
            }
            $result[$field] = (float) $raw;
        }
        foreach (array_unique(array_values($map)) as $field) {
            if (! array_key_exists($field, $result)) {
                throw new \DomainException('USDA record is missing ' . $field .
                    '; find a complete sourced record before importing it.');
            }
        }
        return $result;
    }

    private function fetch(string $path,array $query,string $key): array
    {
        $row=$this->db()->table('nutrition_search_cache')->where('cache_key',$key)->get()->getRowArray();
        // Cached data remains a valid offline response, including stale data when network fails.
        if ($row && strtotime($row['cached_at'])>time()-30*86400)return json_decode($row['response_json'],true)?:[];
        try {
            // DEMO_KEY is a documented public test key; no private key or new .env variable is required.
            $response=service('curlrequest')->get(self::ROOT.$path,['query'=>array_merge($query,['api_key'=>'DEMO_KEY']),'timeout'=>6,'http_errors'=>false]);
            if($response->getStatusCode()!==200)throw new \RuntimeException('FoodData Central is unavailable.');
            $data=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new \RuntimeException('Invalid nutrition service response.');
            $table=$this->db()->table('nutrition_search_cache');
            if($row)$table->where('cache_key',$key)->update(['response_json'=>json_encode($data,JSON_THROW_ON_ERROR),'cached_at'=>date('Y-m-d H:i:s')]);
            else $table->insert(['cache_key'=>$key,'response_json'=>json_encode($data,JSON_THROW_ON_ERROR),'cached_at'=>date('Y-m-d H:i:s')]);
            return $data;
        } catch (\Throwable $e) {
            if($row)return json_decode($row['response_json'],true)?:[];
            throw new \RuntimeException('USDA lookup unavailable; try again online or enter a sourced ingredient manually.');
        }
    }
}
