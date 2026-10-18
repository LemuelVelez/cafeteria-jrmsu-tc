<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
class MenuPosterController extends BaseController
{
    public function index(): string
    {
        $size=strtoupper((string)$this->request->getGet('size'));
        if(!in_array($size,['A4','A6'],true))$size='A4';
        return $this->render('admin/menu/poster',['title'=>'Menu QR poster','paper'=>$size,'url'=>base_url('menu')]);
    }
}
