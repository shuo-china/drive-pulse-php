<?php

namespace app\wxapp\controller;

use app\wxapp\model\Channel;
use app\wxapp\model\UserChannel;
use app\wxapp\model\Order;
use app\wxapp\model\User;

class ChannelController extends BaseController
{
    protected $middleware = [
        'wxapp_api_auth:guest' => [
            'only' => [
                'list'
            ],
        ],
        'wxapp_api_auth:bound' => [
            'except' => [
                'list'
            ],
        ],
    ];

    public function list()
    {
        $channels = Channel::where('status', 1)->field('id,title')->select();

        if ($this->request->userId) {
            $userChannels = UserChannel::where('user_id', $this->request->userId)->column('audit_status,refuse_reason', 'channel_id');
            foreach ($channels as $channel) {
                $channel->audit_status = $userChannels[$channel->id]['audit_status'] ?? 0;
                $channel->refuse_reason = $userChannels[$channel->id]['refuse_reason'] ?? null;
            }
        }
        return $this->success(200, $channels);
    }

    public function apply()
    {
        $post = $this->request->post();
        $userChannel = UserChannel::where('user_id', $this->request->userId)->where('channel_id', $post['channel_id'])->find();
        if ($userChannel) {
            $userChannel->audit_status = 1;
            $userChannel->refuse_reason = null;
            $userChannel->submit_audit_time = time();
            $userChannel->save();
        } else {
            UserChannel::create([
                'user_id' => $this->request->userId,
                'channel_id' => $post['channel_id'],
                'audit_status' => 1,
                'submit_audit_time' => time(),
            ]);
        }
        return $this->success(201);
    }

    public function getChannels()
    {
        $userId = $this->request->userId;
        $initialBalance = User::where('id', $userId)->value('initial_balance');
        $channels = Channel::where('status', 1)->field('id,title')->select()->toArray();
        $diffCountRows = Order::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)->whereOr('target_user_id', $userId);
        })
            ->field("channel_id, COALESCE(SUM(CASE WHEN user_id = {$userId} THEN `count` ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN target_user_id = {$userId} THEN `count` ELSE 0 END), 0) AS diff_count")
            ->group('channel_id')
            ->select()
            ->toArray();

        $diffCountMap = [];
        foreach ($diffCountRows as $row) {
            $diffCountMap[$row['channel_id']] = (int) $row['diff_count'];
        }

        $userChannelStatusMap = UserChannel::where('user_id', $userId)
            ->column('audit_status', 'channel_id');

        foreach ($channels as &$channel) {
            $channel['audit_status'] = $userChannelStatusMap[$channel['id']] ?? 0;
            $channel['balance_count'] = ($userChannelStatusMap[$channel['id']] ?? null) == 2
                ? $initialBalance + ($diffCountMap[$channel['id']] ?? 0)
                : null;
        }
        unset($channel);

        $this->success(200, $channels);
    }
}
