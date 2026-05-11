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

        $lastUid = User::where('is_hidden', 0)->orderRaw('CAST(uid AS UNSIGNED) DESC')->value('uid');
        $nextUid = $this->generateNextUidWithoutFour($lastUid);

        $user = User::create([
            'uid' => $nextUid,
            'nickname' => $post['nickname'],
            'avatar_key' => $post['avatarKey'],
            'avatar_path' => $avatar->getData('path'),
            'is_hidden' => 0,
            'min_balance' => -5,
            'initial_balance' => 0,
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
        $userId = $this->request->userId;
        $diffCount = Order::where('channel_id', $channel_id)
            ->value("COALESCE(SUM(CASE WHEN user_id = {$userId} THEN `count` ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN target_user_id = {$userId} THEN `count` ELSE 0 END), 0)");
        $initialBalance = User::where('id', $userId)->value('initial_balance');

        $balanceCount = $initialBalance + $diffCount;

        $this->success(200, $balanceCount);
    }

    public function currentUser()
    {
        $user = User::where('id', $this->request->clientId)->find();

        $this->success(200, $user);
    }

    public function statistics()
    {
        $userIds = UserChannel::where('audit_status', 2)->column('user_id');

        $map = [
            ['is_hidden', '=', 0],
            ['id', 'in', $userIds],
        ];
        $param = $this->request->param();
        if (!empty($param['nickname'])) {
            $map[] = ['nickname', 'like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map[] = ['uid', '=', $param['uid']];
        }

        $userQuery = User::where($map);
        if (!empty($this->request->userId)) {
            $userQuery = $userQuery->orderRaw("CASE WHEN id = {$this->request->userId} THEN 0 ELSE 1 END ASC, create_time DESC");
        } else {
            $userQuery = $userQuery->order('create_time', 'desc');
        }
        $users = $userQuery->paginate();
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
                    'count' => $item->initial_balance + ($releaseCountMap[$item->id][$channel['id']] ?? 0) - ($takeCountMap[$item->id][$channel['id']] ?? 0),
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
        $nextUid = max(10, (int) $lastUid + 1);

        // while (strpos((string) $nextUid, '4') !== false) {
        //     $nextUid++;
        // }

        return (string) $nextUid;
    }
}
