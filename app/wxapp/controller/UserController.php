<?php

namespace app\wxapp\controller;

use app\wxapp\model\User;
use app\wxapp\model\File as FileModel;
use app\wxapp\model\UserWechatMini;
use app\wxapp\model\UserChannel;
use app\wxapp\model\Order;
use app\wxapp\model\Channel;

class UserController extends BaseController
{
    protected $middleware = [
        'wxapp_api_auth:guest' => [
            'only' => [
                'improve',
                'statistics'
            ],
        ],
        'wxapp_api_auth:bound' => [
            'except' => [
                'improve',
                'statistics'
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
        $nextUid = $this->generateNextUidWithoutFour($lastUid);

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

    public function statistics()
    {
        $map = [
            'balance_limit' => 1,
        ];
        $param = $this->request->param();
        if (!empty($param['nickname'])) {
            $map[] = ['nickname', 'like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map[] = ['uid', '=', $param['uid']];
        }

        $users = User::where($map)->order('create_time', 'desc')->paginate();
        $pageUserIds = array_column($users->items(), 'id');

        $releaseCountMap = [];
        $takeCountMap = [];
        $userChannelMap = [];

        if (!empty($pageUserIds)) {
            $releaseCountRows = Order::where('user_id', 'in', $pageUserIds)
                ->field('user_id, channel_id, SUM(count) as release_count_sum')
                ->group('user_id, channel_id')
                ->select()
                ->toArray();

            foreach ($releaseCountRows as $row) {
                $releaseCountMap[$row['user_id']][$row['channel_id']] = (int) $row['release_count_sum'];
            }

            $takeCountRows = Order::where('target_user_id', 'in', $pageUserIds)
                ->field('target_user_id, channel_id, SUM(count) as take_count_sum')
                ->group('target_user_id, channel_id')
                ->select()
                ->toArray();

            foreach ($takeCountRows as $row) {
                $takeCountMap[$row['target_user_id']][$row['channel_id']] = (int) $row['take_count_sum'];
            }

            $userChannelRows = UserChannel::where('user_id', 'in', $pageUserIds)
                ->field('user_id, channel_id, audit_status')
                ->select()
                ->toArray();

            foreach ($userChannelRows as $row) {
                $userChannelMap[$row['user_id']][$row['channel_id']] = (int) $row['audit_status'];
            }
        }

        $channelList = Channel::where('status', 1)->select()->toArray();
        $todayStartTimestamp = strtotime(date('Y-m-d'));
        $users->each(function ($item) use ($releaseCountMap, $takeCountMap, $userChannelMap, $todayStartTimestamp, $channelList) {
            $channels = [];
            foreach ($channelList as $channel) {
                $channels[] = [
                    'id' => $channel['id'],
                    'title' => $channel['title'],
                    'audit_status' => $userChannelMap[$item->id][$channel['id']] ?? 0,
                    'count' => ($releaseCountMap[$item->id][$channel['id']] ?? 0) - ($takeCountMap[$item->id][$channel['id']] ?? 0),
                ];
            }
            $item->channels = $channels;
            $item->register_days = max(1, (int) (($todayStartTimestamp - strtotime(date('Y-m-d', $item->getData('create_time')))) / 86400) + 1);
            return $item;
        });

        $this->success(200, $users);
    }

    protected function generateNextUidWithoutFour($lastUid)
    {
        $nextUid = max(100, (int) $lastUid + 1);

        while (strpos((string) $nextUid, '4') !== false) {
            $nextUid++;
        }

        return (string) $nextUid;
    }
}
