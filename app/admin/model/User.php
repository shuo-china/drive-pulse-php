<?php

namespace app\admin\model;

use app\admin\model\File as FileModel;

class User extends BaseModel
{
    public function getAvatarPathAttr($value)
    {
        return get_full_path($value);
    }

    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }

    public function getLastLoginTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }

    public function avatar()
    {
        return $this->hasOne(FileModel::class, 'key', 'avatar_key');
    }
}
