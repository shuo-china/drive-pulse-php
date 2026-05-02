<?php

namespace app\wxapp\model;

class Notice extends BaseModel
{
    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }

    public function getUpdateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }
}