<?php

namespace app\wxapp\controller;

use app\wxapp\model\User;
use app\wxapp\model\File as FileModel;
use app\wxapp\model\UserWechatMini;
use app\wxapp\model\UserChannel;
use app\wxapp\model\Order;

class UserController extends BaseController
{
    protected $middleware = [
        'wxapp_api_auth:guest' => [
            'only' => [
                'improve'
            ],
        ],
        'wxapp_api_auth:bound' => [
            'except' => [
                'improve'
            ],
        ],
    ];

    public function improve()
    {
        $post = $this->request->post();
        $has = UserWechatMini::where('id', $this->request->userWxappId)->value('user_id');
        if ($has) {
            $this->error(403, '该账号已完善信息，请勿重复提交', 'NO_NEED_IMPROVE');
        }

        $avatar = FileModel::where('key', $post['avatarKey'])->find();

        $lastUid = User::orderRaw('CAST(uid AS UNSIGNED) DESC')->value('uid');
        $nextUid = (string) max(100, (int) $lastUid + 1);

        $user = User::create([
            'uid' => $nextUid,
            'nickname' => $post['nickname'],
            'avatar_key' => $post['avatarKey'],
            'avatar_path' => $avatar->getData('path'),
        ]);

        UserWechatMini::where('id', $this->request->userWxappId)->update([
            'user_id' => $user->id,
        ]);

        $this->success(201);
    }

    public function getOptionsByChannelId($channel_id)
    {
        $userIds = UserChannel::where('channel_id', $channel_id)
            ->where('audit_status', 2)
            ->where('user_id', '<>', $this->request->userId)
            ->column('user_id');

        $users = User::field('id,uid,nickname,avatar_path')->where('id', 'in', $userIds)->select();

        $this->success(200, $users);
    }

    public function getBananceCountByChannelId($channel_id)
    {
        $releaseCount = Order::where('channel_id', $channel_id)->where('user_id', $this->request->userId)->sum('count');
        $takeCount = Order::where('channel_id', $channel_id)->where('target_user_id', $this->request->userId)->sum('count');
        $balanceCount = $releaseCount - $takeCount;
        $this->success(200, $balanceCount);
    }

    public function currentUser()
    {
        $user = User::where('id', $this->request->clientId)->find();

        $this->success(200, $user);
    }
}
