<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $map = [
            'dashboard' => ['dashboard', 'Dashboard'],
            'dashboard.snapshot' => ['dashboard', 'Dashboard'],
            'media-gateways.index' => ['media-gateways', 'Media Gateways'],
            'gsm-gateways.index' => ['gsm-gateways', 'GSM Gateway'],
            'users.index' => ['users', 'Users'],
            'users.create' => ['users', 'Add User'],
            'users.edit' => ['users', 'Edit User'],
            'activity-logs' => ['activity-logs', 'Audit Logs'],
            'login-history' => ['login-history', 'Login History'],
            'profile' => ['profile', 'My Profile'],
            'maintenance' => ['maintenance', 'Maintenance'],
            'telco-cost' => ['telco-cost', 'Telco Cost'],
            'channel-prefix' => ['channel-prefix', 'Channel Prefix'],
            'channel-port' => ['channel-port', 'Channel Port'],
            'network-prefix' => ['network-prefix', 'Network Prefix'],
            'pdc-servers' => ['pdc-servers', 'PDC Servers'],
            'sip-channels' => ['sip-channels', 'SIP Channels'],
            'channel-range-list' => ['channel-range-list', 'Channel Range List'],
            'channel-allocation' => ['channel-allocation', 'Channel Allocation'],
            'campaigns' => ['campaigns', 'Campaigns'],
            'archive-recordings' => ['archive-recordings', 'Archive Recordings'],
            'globe-sim' => ['globe-sim', 'Globe SIM'],
            'smart-sim' => ['smart-sim', 'Smart SIM'],
            'program-inbound-numbers' => ['program-inbound-numbers', 'Program Inbound Numbers'],
            'signal-boosters' => ['signal-boosters', 'Signal Boosters'],
            'defective-gsm' => ['defective-gsm', 'Defective GSM'],
            'program-location' => ['program-location', 'Program Location'],
            'program-location.show' => ['program-location', 'Program Location'],
            'channel-utilization' => ['channel-utilization', 'Channel Utilization'],
        ];

        $name = Route::currentRouteName() ?? '';
        [$pageKey, $pageTitle] = $map[$name] ?? ['', 'OmniChannel Inventory'];

        if ($name === 'program-location.show') {
            $slug = (string) Route::current()->parameter('location');
            $label = \App\Support\OperationCatalog::programLocationLabels()[$slug]
                ?? (\App\Support\OperationCatalog::locationMapSites()[$slug]['name'] ?? 'Program Location');
            $pageKey = 'location-'.$slug;
            $pageTitle = $label;
        }

        if (! $view->offsetExists('pageKey')) {
            $view->with('pageKey', $pageKey);
        }
        if (! $view->offsetExists('pageTitle')) {
            $view->with('pageTitle', $pageTitle);
        }
    }
}
