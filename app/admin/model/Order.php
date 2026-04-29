<?php

namespace app\admin\model;

use think\model\concern\SoftDelete;

class Order extends BaseModel
{
    use SoftDelete;

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function targetUser()
    {
        return $this->hasOne(User::class, 'id', 'target_user_id');
    }

    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }
}