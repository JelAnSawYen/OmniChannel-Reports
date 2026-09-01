<?php

namespace Database\Seeders;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use Illuminate\Database\Seeder;

class ChannelAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = require database_path('data/channel_allocations.php');

        foreach ($campaigns as $index => $campaign) {
            $record = ChannelAllocationCampaign::query()->updateOrCreate(
                ['name' => $campaign['name']],
                [
                    'media_gateway' => $campaign['media_gateway'] ?: null,
                    'total_channels_allocated' => $campaign['total_channels_allocated'],
                    'fte' => $campaign['fte'],
                    'caller_id' => $campaign['caller_id'] !== '' ? $campaign['caller_id'] : null,
                    'prefix' => $campaign['prefix'] !== '' ? $campaign['prefix'] : null,
                    'remarks' => $campaign['remarks'] !== '' ? $campaign['remarks'] : null,
                    'sort_order' => $index + 1,
                ]
            );

            $record->allocations()->delete();

            foreach ($campaign['allocations'] as $allocIndex => $allocation) {
                ChannelAllocation::query()->create([
                    'campaign_id' => $record->id,
                    'media_gateway' => $allocation['media_gateway'] !== '' ? $allocation['media_gateway'] : $record->media_gateway,
                    'channel_allocation' => $allocation['channel_allocation'],
                    'network' => $allocation['network'] !== '' ? $allocation['network'] : null,
                    'line_priority' => $allocation['line_priority'],
                    'total_channel_allocated' => $allocation['total_channel_allocated'],
                    'remarks' => $allocation['remarks'] !== '' ? $allocation['remarks'] : null,
                    'sort_order' => $allocIndex + 1,
                ]);
            }
        }
    }
}
