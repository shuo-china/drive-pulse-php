<?php

namespace app\wxapp\model;

use think\model\concern\SoftDelete;

class User extends BaseModel
{
    use SoftDelete;

    public function getAvatarPathAttr($value)
    {
        return get_full_path($value);
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class);
    }
}