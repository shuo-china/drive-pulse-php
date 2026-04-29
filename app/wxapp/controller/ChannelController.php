<?php

namespace app\wxapp\controller;

use app\wxapp\model\Channel;
use app\wxapp\model\UserChannel;

class ChannelController extends BaseController
{
    public function list()
    {
        $channels = Channel::where('status', 1)->field('id,title')->select();
        $userChannels = UserChannel::where('user_id', $this->request->userId)->column('audit_status,refuse_reason', 'channel_id');

        foreach ($channels as $channel) {
            $channel->audit_status = $userChannels[$channel->id]['audit_status'] ?? 0;
            $channel->refuse_reason = $userChannels[$channel->id]['refuse_reason'] ?? null;
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
}
