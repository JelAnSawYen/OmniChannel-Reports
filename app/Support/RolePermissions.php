<?php

namespace App\Support;

class RolePermissions
{
    public const PERMISSIONS = [
        'dashboard.view' => 'View Dashboard',
        'media.view' => 'View Media Gateways',
        'media.create' => 'Add Media Gateways',
        'media.edit' => 'Edit Media Gateways',
        'media.delete' => 'Delete Media Gateways',
        'media.export' => 'Export Data',
        'users.view' => 'View Users',
        'users.manage' => 'Manage Users',
        'roles.view' => 'View User Types',
        'roles.manage' => 'Manage User Types',
        'logs.view' => 'View Activity Logs',
        'maintenance.manage' => 'Manage Maintenance',
    ];

    public const STANDARD_LOCKED_PERMISSIONS = ['users.manage', 'roles.manage'];

    public const EDIT_PERMISSIONS = [
        'media.create' => ['label' => 'Add / Create Records', 'description' => 'Create new records in the system.', 'icon' => 'plus'],
        'media.edit' => ['label' => 'Edit Records', 'description' => 'Modify existing records in the system.', 'icon' => 'edit'],
        'media.export' => ['label' => 'Export Data', 'description' => 'Export data from the system.', 'icon' => 'export'],
        'media.delete' => ['label' => 'Delete Records', 'description' => 'Delete records from the system.', 'icon' => 'delete'],
        'logs.view' => ['label' => 'View Activity Logs', 'description' => 'View system activity and audit logs.', 'icon' => 'logs'],
        'users.manage' => ['label' => 'Manage Users', 'description' => 'Create, edit, and manage system users.', 'icon' => 'users'],
        'roles.manage' => ['label' => 'Manage User Types', 'description' => 'Create, edit, and manage user types.', 'icon' => 'roles'],
    ];

    public const STANDARD_ALLOWED_MODULES = [
        'dashboard',
        'campaigns',
        'gsm-gateways',
        'media-gateways',
        'channel-allocation',
        'globe-sim',
        'smart-sim',
    ];

    public const ADMINISTRATOR_PERMISSIONS = [
        'dashboard.view',
        'media.view',
        'media.create',
        'media.edit',
        'media.delete',
        'media.export',
        'users.view',
        'users.manage',
        'logs.view',
    ];

    public const STANDARD_USER_PERMISSIONS = [
        'dashboard.view',
        'media.view',
        'media.export',
    ];
}
