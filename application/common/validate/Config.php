<?php
// +------------------------------------------------------
// | Author: 王鹏 <1109337820@qq.com>
// +------------------------------------------------------
namespace app\common\validate;
use think\Validate;
class Config extends Validate {
    protected $rule = [
		'cat_id' => 'require',
		'type' => 'require',
		'name' => 'require',
		'var' => 'require',
		'value' => 'require',
	];
    protected $message = [
		'cat_id.require' => '分类ID不能为空',
		'type.require' => '类型不能为空',
		'name.require' => '名称不能为空',
		'var.require' => '变量名不能为空',
		'value.require' => '变量值不能为空',
	];

    protected $scene = [
        'add'=> ['cat_id','type','name','var','value'],
        'edit' =>['cat_id','type','name','var','value'],
    ];
}