<?php

namespace app\admin\model;

use think\model\concern\SoftDelete;

class Channel extends BaseModel
{
    use SoftDelete;

    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }
}