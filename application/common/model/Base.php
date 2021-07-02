<?php
// +------------------------------------------------------
// | Author: 
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
class Base extends Model {

    public function checkPassword($value,$password){
        $pwd = $this->password($value);
        if($pwd!=$password) {
            return false;
        }
        return true;
    }

    public function password($value)
    {   
        return md5(substr(md5(md5($value).config('extend.password_halt')),8));
    }
}
