<?php

namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'uid|用户编号' => 'require|unique:user',
    ];
}
