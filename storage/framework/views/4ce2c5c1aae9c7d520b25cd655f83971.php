<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($pageTitle ?? 'OmniChannel Reports'); ?> - OmniChannel Reports</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css','resources/js/app.js']); ?>
</head>
<body data-user-type="<?php echo e(auth()->user()->userType?->name); ?>" data-page="<?php echo e($pageKey ?? ''); ?>" data-resource-base="<?php echo e($resource['base'] ?? ''); ?>" data-can-edit="<?php echo e((auth()->user()?->hasPermission('media.edit') && auth()->user()?->canMutateGateways()) ? '1' : '0'); ?>" data-can-delete="<?php echo e((auth()->user()?->hasPermission('media.delete') && auth()->user()?->canMutateGateways()) ? '1' : '0'); ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <div class="brand"><div class="brand-title">OmniChannel</div><div class="brand-subtitle">Reports</div></div>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close navigation">×</button>
        <div class="sidebar-nav" id="sidebarNav">
        <nav class="nav-section">
            <div class="nav-list">
                <?php if(auth()->user()->hasPermission('dashboard.view')): ?><a href="<?php echo e(route('dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('dashboard') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.6 11.2 12 4.7l7.4 6.5"/><path d="M6.4 10.2V19h11.2v-8.8"/><path d="M10 19v-5h4v5"/></svg></span><span>Dashboard</span></a><?php endif; ?>
            </div>
        </nav>
        <?php if(auth()->user()->hasPermission('media.view')): ?>
        <?php
            $locationOpen = request()->routeIs('program-location', 'program-location.show');
        ?>
        <div class="nav-section"><div class="section-label">Manage</div>
            <div class="nav-list">
                <a href="<?php echo e(route('campaigns')); ?>" class="nav-item <?php echo e(request()->routeIs('campaigns', 'campaigns.*') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h11v11H4z"/><path d="M15 8h5l-2 4 2 4h-5"/><path d="M7.5 9.5h4M7.5 12.5h4"/></svg></span><span>Campaigns</span></a>
                <a href="<?php echo e(route('pdc-servers')); ?>" class="nav-item <?php echo e(request()->routeIs('pdc-servers') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><circle cx="7" cy="7.5" r=".9" fill="currentColor" stroke="none"/><circle cx="7" cy="16.5" r=".9" fill="currentColor" stroke="none"/></svg></span><span>PDC Servers</span></a>
                <a href="<?php echo e(route('sip-channels')); ?>" class="nav-item <?php echo e(request()->routeIs('sip-channels') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 3a6.5 6.5 0 0 1 5.5 5.5"/><path d="M14.5 6.8a3 3 0 0 1 2.4 2.4"/><path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/></svg></span><span>SIP Channels</span></a>
                <a href="<?php echo e(route('channel-allocation')); ?>" class="nav-item <?php echo e(request()->routeIs('channel-allocation', 'channel-allocation.*') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span><span>Channel Allocation</span></a>
                <a href="<?php echo e(route('archive-recordings')); ?>" class="nav-item <?php echo e(request()->routeIs('archive-recordings') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3H7.5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8l-5-5z"/><path d="M13.5 3v5h5"/><path d="M10 12.8v4l3.4-2-3.4-2z"/></svg></span><span>Archive Recordings</span></a>
                <a href="<?php echo e(route('gsm-gateways.index')); ?>" class="nav-item <?php echo e(request()->routeIs('gsm-gateways.*') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h4.2l3.3 3.3v10.2A2.5 2.5 0 0 1 12 20H7a2.5 2.5 0 0 1-2.5-2.5v-11z"/><rect x="7" y="10.5" width="5" height="5.5" rx="1"/><path d="M17.2 5.4a5.6 5.6 0 0 1 3.3 5"/><path d="M16.8 9.2a2.6 2.6 0 0 1 1.6 2.1"/></svg></span><span>GSM Gateway</span></a>
                <a href="<?php echo e(route('globe-sim')); ?>" class="nav-item <?php echo e(request()->routeIs('globe-sim') ? 'active' : ''); ?>"><span class="nav-icon"><img class="nav-icon-img" src="<?php echo e(asset('icons/globe-sim.png')); ?>" alt=""></span><span>Globe SIM</span></a>
                <a href="<?php echo e(route('smart-sim')); ?>" class="nav-item <?php echo e(request()->routeIs('smart-sim') ? 'active' : ''); ?>"><span class="nav-icon"><img class="nav-icon-img" src="<?php echo e(asset('icons/smart-sim.png')); ?>" alt=""></span><span>Smart SIM</span></a>
                <a href="<?php echo e(route('program-inbound-numbers')); ?>" class="nav-item <?php echo e(request()->routeIs('program-inbound-numbers') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 3.5 15.7 8.3"/><path d="M15.7 4v4.6h4.6"/><path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/></svg></span><span>Program Inbound Numbers</span></a>
                <a href="<?php echo e(route('signal-boosters')); ?>" class="nav-item <?php echo e(request()->routeIs('signal-boosters') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="1.8"/><path d="M8.6 5.6a4.8 4.8 0 0 0 0 6.8"/><path d="M15.4 5.6a4.8 4.8 0 0 1 0 6.8"/><path d="M6.2 3.2a8.2 8.2 0 0 0 0 11.6"/><path d="M17.8 3.2a8.2 8.2 0 0 1 0 11.6"/><path d="M12 10.8V21"/><path d="M9 21l3-5 3 5"/></svg></span><span>Signal Boosters</span></a>
                <a href="<?php echo e(route('defective-gsm')); ?>" class="nav-item <?php echo e(request()->routeIs('defective-gsm') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/></svg></span><span>Defective GSM</span></a>
                <div class="nav-group <?php echo e($locationOpen ? 'open' : ''); ?>" id="programLocationGroup">
                    <div class="nav-parent-row">
                        <a href="<?php echo e(route('program-location')); ?>" class="nav-item <?php echo e(request()->routeIs('program-location') ? 'active' : ''); ?>"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21c4.4-4 7-7.2 7-10.4A7 7 0 0 0 5 10.6C5 13.8 7.6 17 12 21z"/><circle cx="12" cy="10.2" r="2.4"/></svg></span><span>Program Location</span></a>
                        <button type="button" class="nav-caret" id="programLocationToggle" aria-expanded="<?php echo e($locationOpen ? 'true' : 'false'); ?>" aria-controls="programLocationSub" aria-label="Toggle Program Location">⌄</button>
                    </div>
                    <div class="nav-sub" id="programLocationSub">
                        <?php $__currentLoopData = \App\Support\OperationCatalog::locations(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route('program-location.show', $slug)); ?>" class="nav-item nav-subitem <?php echo e(request()->routeIs('program-location.show') && request()->route('location') === $slug ? 'active' : ''); ?>"><span><?php echo e($name); ?></span></a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="main">
        <header class="app-header">
            <div class="header-left">
                <button class="hamburger" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-expanded="true"><span></span><span></span><span></span></button>
            </div>
            <div class="header-right">
                <?php
                    $notifIcons = [
                        'bell' => '<path d="M18 8.6a6 6 0 1 0-12 0c0 4.9-2 6.4-2 6.4h16s-2-1.5-2-6.4"/><path d="M10.3 19a2 2 0 0 0 3.4 0"/>',
                        'gsm' => '<path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h4.2l3.3 3.3v10.2A2.5 2.5 0 0 1 12 20H7a2.5 2.5 0 0 1-2.5-2.5v-11z"/><rect x="7" y="10.5" width="5" height="5.5" rx="1"/><path d="M17.2 5.4a5.6 5.6 0 0 1 3.3 5"/><path d="M16.8 9.2a2.6 2.6 0 0 1 1.6 2.1"/>',
                        'server' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><circle cx="7" cy="7.5" r=".9" fill="currentColor" stroke="none"/><circle cx="7" cy="16.5" r=".9" fill="currentColor" stroke="none"/>',
                        'sim' => '<path d="M6 5.5A2.5 2.5 0 0 1 8.5 3h4.7L18 7.8v10.7A2.5 2.5 0 0 1 15.5 21h-7A2.5 2.5 0 0 1 6 18.5v-13z"/><rect x="9" y="10.5" width="6" height="6" rx="1"/>',
                        'users' => '<circle cx="9.5" cy="8" r="3.2"/><path d="M3.5 20a6 6 0 0 1 12 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M18 14.4A6 6 0 0 1 21 20"/>',
                        'inbound' => '<path d="M20.5 3.5 15.7 8.3"/><path d="M15.7 4v4.6h4.6"/><path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
                        'signal' => '<circle cx="12" cy="9" r="1.8"/><path d="M8.6 5.6a4.8 4.8 0 0 0 0 6.8"/><path d="M15.4 5.6a4.8 4.8 0 0 1 0 6.8"/><path d="M6.2 3.2a8.2 8.2 0 0 0 0 11.6"/><path d="M17.8 3.2a8.2 8.2 0 0 1 0 11.6"/><path d="M12 10.8V21"/><path d="M9 21l3-5 3 5"/>',
                        'alert' => '<path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/>',
                        'archive' => '<rect x="3.5" y="4" width="17" height="4.5" rx="1.5"/><path d="M5 8.5v9A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-9"/><path d="M10 12.5h4"/>',
                        'pin' => '<path d="M12 21c4.4-4 7-7.2 7-10.4A7 7 0 0 0 5 10.6C5 13.8 7.6 17 12 21z"/><circle cx="12" cy="10.2" r="2.4"/>',
                        'shield' => '<path d="M12 3.2 5.2 6v5.4c0 4.3 2.9 8.1 6.8 9.4 3.9-1.3 6.8-5.1 6.8-9.4V6L12 3.2z"/><path d="m9.2 12.2 2 2 3.6-3.6"/>',
                        'upload' => '<path d="M12 15.5V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M4 16.5v2A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5v-2"/>',
                        'download' => '<path d="M12 4v11.5"/><path d="m7.5 11 4.5 4.5 4.5-4.5"/><path d="M4 16.5v2A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5v-2"/>',
                        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.4V12l3.2 1.9"/>',
                        'check' => '<path d="m5 12.6 4.6 4.6L19 7.8"/>',
                        'checks' => '<path d="m3 12.6 4.2 4.2L15 9"/><path d="m11.4 16.2 1.4 1.4 8.2-8.2"/>',
                        'trash' => '<path d="M4 7h16"/><path d="M9.5 7V5.2A1.2 1.2 0 0 1 10.7 4h2.6a1.2 1.2 0 0 1 1.2 1.2V7"/><path d="M6 7l.9 12A2 2 0 0 0 8.9 21h6.2a2 2 0 0 0 2-1.9L18 7"/><path d="M10.2 11v6M13.8 11v6"/>',
                    ];
                    $notifStyle = function ($item) {
                        $key = strtolower($item->notification->type.' '.$item->notification->title.' '.$item->notification->body);
                        [$icon, $tone] = match (true) {
                            str_contains($key, 'location') => ['pin', 'sky'],
                            str_contains($key, 'defective') => ['alert', 'amber'],
                            str_contains($key, 'booster') => ['signal', 'pink'],
                            str_contains($key, 'pdc') => ['server', 'purple'],
                            str_contains($key, 'sim') => ['sim', 'green'],
                            str_contains($key, 'inbound') => ['inbound', 'sky'],
                            str_contains($key, 'archive') => ['archive', 'amber'],
                            str_contains($key, 'gsm') || str_contains($key, 'gateway') => ['gsm', 'blue'],
                            str_contains($key, 'contract') || str_contains($key, 'maintenance') => ['clock', 'amber'],
                            str_contains($key, 'import') => ['upload', 'sky'],
                            str_contains($key, 'export') => ['download', 'green'],
                            str_contains($key, 'system error') => ['shield', 'navy'],
                            default => ['bell', 'sky'],
                        };

                        return [$icon, $item->notification->tone === 'offline' ? 'danger' : $tone];
                    };
                    $notifPriority = fn ($item) => \App\Services\NotificationService::priorityFor($item->notification->type);
                ?>
                <div class="notification-wrap">
                    <button class="notification-button" id="notificationButton" type="button" aria-label="Notifications"><img class="notification-icon-img" src="<?php echo e(asset('icons/notifications.png')); ?>" alt=""><?php if(($headerAlerts['badge']??0)>0): ?><span class="notification-badge" id="notificationBadge"><?php echo e($headerAlerts['badge']); ?></span><?php endif; ?></button>
                    <div class="notification-menu" id="notificationMenu">
                        <div class="notification-head">
                            <span class="notification-head-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $notifIcons['bell']; ?></svg>
                            </span>
                            <strong>Notifications</strong>
                            <span class="notification-count" id="notificationCountPill" <?php if(($headerAlerts['badge']??0)<1): ?> hidden <?php endif; ?>><?php echo e($headerAlerts['badge'] ?? 0); ?> New</span>
                            <?php if(!empty($headerAlerts['items'])): ?>
                                <form class="notification-readall-form" id="markAllNotificationsForm" method="POST" action="<?php echo e(route('notifications.read-all')); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button class="notification-readall" type="submit" id="markAllNotificationsButton" title="Mark all as read">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $notifIcons['checks']; ?></svg>
                                        Mark all as read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div id="notificationList">
                        <?php $__empty_1 = true; $__currentLoopData = ($headerAlerts['items']??[]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php [$itemIcon, $itemTone] = $notifStyle($item); $itemPriority = $notifPriority($item); ?>
                            <div class="notification-item <?php echo e($item->read_at ? '' : 'unread'); ?> <?php echo e($itemTone === 'danger' ? 'danger' : ''); ?>" data-recipient="<?php echo e($item->id); ?>" data-priority="<?php echo e($itemPriority); ?>">
                                <span class="notification-flag" aria-hidden="true"></span>
                                <a class="notification-link" href="<?php echo e($item->notification->link ?: '#'); ?>" data-notification-read="<?php echo e($item->id); ?>">
                                    <span class="notification-ico tone-<?php echo e($itemTone); ?>" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $notifIcons[$itemIcon]; ?></svg>
                                    </span>
                                    <span class="notification-copy">
                                        <span class="notification-text"><?php echo e($item->notification->body); ?></span>
                                        <span class="notification-time">
                                            <span class="notification-priority prio-<?php echo e($itemPriority); ?>"><?php echo e(ucfirst($itemPriority)); ?></span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $notifIcons['clock']; ?></svg>
                                            <?php echo e(($item->created_at ?? $item->notification->created_at)?->diffForHumans()); ?>

                                        </span>
                                    </span>
                                </a>
                                <span class="notification-actions">
                                    <button type="button" class="notification-mark" data-notification-mark="<?php echo e($item->id); ?>" title="Mark as read" aria-label="Mark as read" <?php if($item->read_at): ?> hidden <?php endif; ?>>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $notifIcons['check']; ?></svg>
                                    </button>
                                    <button type="button" class="notification-dismiss" data-notification-dismiss="<?php echo e($item->id); ?>" title="Remove notification" aria-label="Remove notification">×</button>
                                </span>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="notification-empty" id="notificationEmpty">No operational alerts right now.</div>
                        <?php endif; ?>
                        </div>
                        <?php if(!empty($headerAlerts['items'])): ?>
                            <div class="notification-footer">
                                <form id="clearNotificationsForm" method="POST" action="<?php echo e(route('notifications.clear')); ?>" data-confirm="Clear all notifications for your account? Audit logs will be kept." data-confirm-title="Clear Notifications" data-confirm-ok="Clear All">
                                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                    <button class="notification-clear" type="submit" id="clearNotificationsButton">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $notifIcons['trash']; ?></svg>
                                        Clear All
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <div class="account-wrap">
                <button type="button" class="account-button" id="accountButton" aria-expanded="false">
                    <span class="account-avatar"><?php echo e(strtoupper(substr(auth()->user()->name ?? 'U',0,1))); ?></span>
                    <span class="account-label"><?php echo e(auth()->user()->name ?? 'User'); ?></span>
                    <span class="account-caret">⌄</span>
                </button>
                <div class="account-menu" id="accountMenu">
                    <div class="account-menu-user"><div class="account-avatar large"><?php echo e(strtoupper(substr(auth()->user()->name ?? 'U',0,1))); ?></div><div><strong><?php echo e(auth()->user()->name); ?></strong><small><?php echo e(auth()->user()->userType?->name ?? 'User'); ?></small></div></div>
                    <a class="account-item" href="<?php echo e(route('profile')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>My Profile</a>
                    <?php if(auth()->user()->canAccessAdministration() && (auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('roles.view'))): ?>
                    <?php
                        $userManagementOpen = request()->routeIs('users.*', 'user-types.*');
                    ?>
                    <div class="account-group <?php echo e($userManagementOpen ? 'open' : ''); ?>" id="accountUserManagement">
                        <button type="button" class="account-item account-group-toggle" id="accountUserManagementToggle" aria-expanded="<?php echo e($userManagementOpen ? 'true' : 'false'); ?>" aria-controls="accountUserManagementSub"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9.5" cy="8" r="3.2"/><path d="M3.5 20a6 6 0 0 1 12 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M18 14.4A6 6 0 0 1 21 20"/></svg>User Management<svg class="account-group-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                        <div class="account-group-sub" id="accountUserManagementSub">
                            <?php if(auth()->user()->hasPermission('users.view')): ?><a class="account-subitem" href="<?php echo e(route('users.index')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>Users</a><?php endif; ?>
                            <?php if(auth()->user()->hasPermission('roles.view')): ?><a class="account-subitem" href="<?php echo e(route('user-types.index')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 5.6v5.2c0 4.1 2.9 7.6 7 9.2 4.1-1.6 7-5.1 7-9.2V5.6L12 3z"/><circle cx="12" cy="10.6" r="1.7"/><path d="M9.2 16a3.2 3.2 0 0 1 5.6 0"/></svg>User Types</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasPermission('logs.view')): ?><a class="account-item" href="<?php echo e(route('login-history')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 12a8.5 8.5 0 1 0 2.9-6.4"/><path d="M3.2 4.6v4.6h4.6"/><path d="M12 8.2V12l2.9 1.8"/></svg>Login History</a><?php endif; ?>
                    <button type="button" class="account-item account-logout" id="logoutButton"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4H6.5A1.5 1.5 0 0 0 5 5.5v13A1.5 1.5 0 0 0 6.5 20H14"/><path d="m16.5 8.5 3.5 3.5-3.5 3.5"/><path d="M20 12h-9.5"/></svg>Logout</button>
                </div>
            </div>
            </div>
        </header>
        <div class="page-container">
            <?php if(session('success')): ?><div class="flash success"><?php echo e(session('success')); ?></div><?php endif; ?>
            <?php if(session('error')): ?><div class="flash error"><?php echo e(session('error')); ?></div><?php endif; ?>
            <?php if($errors->any()): ?><div class="flash error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        <footer class="app-footer">© <?php echo e(now()->year); ?> OmniChannel Reports. All rights reserved.</footer>
    </main>
</div>
<div class="modal-backdrop" id="logoutModal"><div class="modal small"><div class="modal-header"><h3>Confirm Logout</h3><button type="button" class="close-btn" data-close="logoutModal">×</button></div><div class="modal-body"><p>Are you sure you want to logout?</p></div><div class="modal-footer"><button type="button" class="btn secondary" data-close="logoutModal">No</button><form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button class="btn danger" type="submit">Yes</button></form></div></div></div>
<div class="modal-backdrop" id="confirmModal">
    <div class="modal small">
        <div class="modal-header">
            <h3 id="confirmModalTitle">Confirm</h3>
            <button type="button" class="close-btn" data-close="confirmModal" id="confirmModalDismiss">×</button>
        </div>
        <div class="modal-body"><p id="confirmModalMessage">Are you sure?</p></div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" id="confirmModalCancel">No</button>
            <button type="button" class="btn danger" id="confirmModalOk">Yes</button>
        </div>
    </div>
</div>
<?php echo $__env->yieldPushContent('modals'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/layouts/app.blade.php ENDPATH**/ ?>