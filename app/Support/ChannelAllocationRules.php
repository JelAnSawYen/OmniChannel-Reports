<?php

namespace App\Support;

/**
 * Single source of the Channel Allocation validation rules so Add, Edit, and
 * Data Transfer stay identical. Channel existence itself is resolved by
 * ChannelAllocationResolver, which all three paths already share.
 */
class ChannelAllocationRules
{
    /**
     * Shown by Add, Edit, and Data Transfer when the campaign is not on the
     * Master Campaign page. Channel Allocation never creates a campaign.
     */
    public const CAMPAIGN_NOT_IN_MASTER = 'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.';

    /**
     * Field labels used when reporting an error against a column.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'campaign' => 'Campaign',
            'fte' => 'FTE',
            'caller_id' => 'Caller ID',
            'prefix' => 'Prefix',
            'remarks' => 'Remarks',
            'channel' => 'Channel',
            'network' => 'Network',
            'line_priority' => 'Line Priority',
            'total_channel_allocated' => 'Channel Count',
        ];
    }

    public static function label(string $field): string
    {
        return self::labels()[$field] ?? $field;
    }

    /**
     * Campaign rules for the Add / Edit forms.
     *
     * @return array<string, mixed>
     */
    public static function campaign(bool $creating = false): array
    {
        $rules = [
            'media_gateway' => 'nullable|string|max:255',
            'caller_id' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
        ];
        if ($creating) {
            $rules['campaign'] = ['required_without:campaign_id', 'nullable', 'string', 'max:255'];
            $rules['campaign_id'] = ['required_without:campaign', 'nullable', 'integer'];
        }

        return $rules;
    }

    /**
     * Allocation rules for the Add / Edit forms.
     *
     * @return array<string, mixed>
     */
    public static function allocation(): array
    {
        return [
            'channel_type' => 'nullable|in:sip,gsm',
            'channel' => 'required|string|max:255',
            'line_priority' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Data Transfer row rules. Same constraints as Add / Edit; Campaign is plainly
     * required because an imported row has no campaign_id to fall back on.
     *
     * @return array<string, mixed>
     */
    public static function importRow(): array
    {
        $campaign = self::campaign(true);
        $allocation = self::allocation();

        return [
            'campaign' => ['required', 'string', 'max:255'],
            'caller_id' => $campaign['caller_id'],
            'prefix' => $campaign['prefix'],
            'remarks' => $campaign['remarks'],
            'line_priority' => $allocation['line_priority'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function importMessages(): array
    {
        return [
            'campaign.required' => 'Campaign is required',
        ];
    }
}
