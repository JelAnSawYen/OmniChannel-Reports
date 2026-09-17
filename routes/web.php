<?php

use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Archive\ArchiveRecordingController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\ChannelAllocation\ChannelAllocationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gsm\LocationController;
use App\Http\Controllers\Gsm\MediaGatewayController;
use App\Http\Controllers\Logs\ActivityLogController;
use App\Http\Controllers\Logs\LoginLogController;
use App\Http\Controllers\Operations\OperationsDataController;
use App\Http\Controllers\Pdc\PdcServerController;
use App\Http\Controllers\Reports\ChannelUtilizationController;
use App\Http\Controllers\Reports\SystemHealthController;
use App\Http\Controllers\Sip\ChannelRangeListController;
use App\Http\Controllers\Sip\SipChannelController;
use App\Support\OperationCatalog;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/setup', [MfaController::class, 'confirmSetup'])->middleware('throttle:mfa')->name('mfa.setup.confirm');
    Route::get('/mfa/recovery', [MfaController::class, 'recovery'])->name('mfa.recovery');
    Route::post('/mfa/recovery', [MfaController::class, 'acknowledgeRecovery'])->middleware('throttle:mfa')->name('mfa.recovery.ack');
    Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaController::class, 'verify'])->middleware('throttle:mfa')->name('mfa.challenge.verify');

    Route::middleware(['verified', 'mfa'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['permission:dashboard.view', 'module:dashboard'])->name('dashboard');
        Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot'])->middleware(['permission:dashboard.view', 'module:dashboard'])->name('dashboard.snapshot');
        Route::get('/system-health', [SystemHealthController::class, 'index'])->middleware(['permission:dashboard.view', 'module:system-health'])->name('system-health');

        $gatewayRoutes = function () {
            Route::get('/', [MediaGatewayController::class, 'index'])->middleware('permission:media.view')->name('index');
            Route::get('/sims', [MediaGatewayController::class, 'sims'])->middleware('permission:media.view')->name('sims');
            Route::get('/export', [MediaGatewayController::class, 'export'])->middleware('permission:media.export')->name('export');
            Route::get('/import/template', [MediaGatewayController::class, 'importTemplate'])->middleware('permission:media.create')->name('import.template');
            Route::get('/import/errors', [MediaGatewayController::class, 'importErrors'])->middleware('permission:media.create')->name('import.errors');
            Route::post('/import/preview', [MediaGatewayController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('import.preview');
            Route::post('/import/confirm', [MediaGatewayController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('import.confirm');
            Route::post('/', [MediaGatewayController::class, 'store'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('store');
            Route::delete('/bulk', [MediaGatewayController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('bulk-destroy');
            Route::put('/{mediaGateway}', [MediaGatewayController::class, 'update'])->middleware(['permission:media.edit', 'throttle:sensitive'])->name('update');
            Route::delete('/{mediaGateway}', [MediaGatewayController::class, 'destroy'])->middleware('permission:media.delete')->name('destroy');
        };

        Route::prefix('media-gateways')->name('media-gateways.')->middleware('module:media-gateways')->group($gatewayRoutes);
        Route::prefix('gsm-gateways')->name('gsm-gateways.')->middleware('module:gsm-gateways')->group(function () use ($gatewayRoutes) {
            $gatewayRoutes();
            Route::post('/{mediaGateway}/assignments', [MediaGatewayController::class, 'storeAssignment'])->middleware(['permission:media.create', 'throttle:sensitive'])->whereNumber('mediaGateway')->name('assignments.store');
            Route::delete('/{mediaGateway}/assignments/bulk', [MediaGatewayController::class, 'bulkDestroyAssignments'])->middleware('permission:media.delete')->whereNumber('mediaGateway')->name('assignments.bulk-destroy');
            Route::put('/{mediaGateway}/assignments/{assignment}', [MediaGatewayController::class, 'updateAssignment'])->middleware(['permission:media.edit', 'throttle:sensitive'])->whereNumber('mediaGateway')->whereNumber('assignment')->name('assignments.update');
            Route::delete('/{mediaGateway}/assignments/{assignment}', [MediaGatewayController::class, 'destroyAssignment'])->middleware('permission:media.delete')->whereNumber('mediaGateway')->whereNumber('assignment')->name('assignments.destroy');
        });

        Route::prefix('users')->name('users.')->middleware('module:users')->group(function () {
            Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('index');
            Route::get('/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('create');
            Route::post('/', [UserController::class, 'store'])->middleware(['permission:users.manage', 'throttle:sensitive'])->name('store');
            Route::delete('/bulk', [UserController::class, 'bulkDestroy'])->middleware(['permission:users.manage', 'throttle:sensitive'])->name('bulk-destroy');
            Route::get('/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->middleware(['permission:users.manage', 'throttle:sensitive'])->name('update');
            Route::post('/{user}/verification', [UserController::class, 'resendVerification'])->middleware('permission:users.manage')->name('verification.resend');
            Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('destroy');
        });

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware(['permission:logs.view', 'module:activity-logs'])->name('activity-logs');
        Route::delete('/activity-logs/older', [ActivityLogController::class, 'clearOlder'])->middleware(['permission:logs.view', 'module:activity-logs'])->name('activity-logs.clear-older');
        Route::get('/login-history', [LoginLogController::class, 'index'])->middleware(['permission:logs.view', 'module:login-history'])->name('login-history');
        Route::delete('/login-history/older', [LoginLogController::class, 'clearOlder'])->middleware(['permission:logs.view', 'module:login-history'])->name('login-history.clear-older');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:sensitive')->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:sensitive')->name('profile.password');

        Route::middleware('module:maintenance')->group(function () {
            Route::get('/maintenance', [MaintenanceController::class, 'index'])->middleware('permission:maintenance.manage')->name('maintenance');
            Route::post('/maintenance/mail', [MaintenanceController::class, 'saveMail'])->middleware(['permission:maintenance.manage', 'throttle:sensitive'])->name('maintenance.mail');
            Route::post('/maintenance/mail/test', [MaintenanceController::class, 'testMail'])->middleware(['permission:maintenance.manage', 'throttle:sensitive'])->name('maintenance.mail.test');
            Route::post('/maintenance/backup', [MaintenanceController::class, 'backup'])->middleware(['permission:maintenance.manage', 'throttle:sensitive'])->name('maintenance.backup');
            Route::post('/maintenance/restore', [MaintenanceController::class, 'restore'])->middleware(['permission:maintenance.manage', 'throttle:sensitive'])->name('maintenance.restore');
            Route::get('/maintenance/backup/{filename}', [MaintenanceController::class, 'download'])->middleware('permission:maintenance.manage')->name('maintenance.backup.download');
            Route::delete('/maintenance/logs', [MaintenanceController::class, 'clearLogs'])->middleware('permission:maintenance.manage')->name('maintenance.logs.clear');
        });

        Route::get('/channel-utilization', [ChannelUtilizationController::class, 'index'])->middleware(['permission:dashboard.view', 'module:dashboard'])->name('channel-utilization');

        Route::middleware('module:program-location')->group(function () {
            Route::get('/program-location', [LocationController::class, 'index'])->middleware('permission:media.view')->name('program-location');
            Route::put('/program-location/{location}/status', [LocationController::class, 'updateStatus'])->middleware('permission:media.edit')->name('program-location.status');
            Route::get('/program-location/{location}/export', [LocationController::class, 'export'])->middleware('permission:media.export')->name('program-location.export');
            Route::get('/program-location/{location}/import/template', [LocationController::class, 'importTemplate'])->middleware('permission:media.create')->name('program-location.import.template');
            Route::get('/program-location/{location}/import/errors', [LocationController::class, 'importErrors'])->middleware('permission:media.create')->name('program-location.import.errors');
            Route::post('/program-location/{location}/import/preview', [LocationController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('program-location.import.preview');
            Route::post('/program-location/{location}/import/confirm', [LocationController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('program-location.import.confirm');
            Route::get('/program-location/{location}', [LocationController::class, 'show'])->middleware('permission:media.view')->name('program-location.show');
            Route::post('/program-location/{location}', [LocationController::class, 'store'])->middleware('permission:media.create')->name('program-location.store');
            Route::put('/program-location/{location}/{gateway}', [LocationController::class, 'update'])->middleware('permission:media.edit')->whereNumber('gateway')->name('program-location.update');
            Route::delete('/program-location/{location}/bulk', [LocationController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('program-location.bulk-destroy');
            Route::delete('/program-location/{location}/{gateway}', [LocationController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('gateway')->name('program-location.destroy');
        });

        Route::prefix('channel-allocation')->middleware('module:channel-allocation')->group(function () {
            Route::get('/', [ChannelAllocationController::class, 'index'])->middleware('permission:media.view')->name('channel-allocation');
            Route::get('/export', [ChannelAllocationController::class, 'export'])->middleware('permission:media.export')->name('channel-allocation.export');
            Route::get('/import/template', [ChannelAllocationController::class, 'importTemplate'])->middleware('permission:media.create')->name('channel-allocation.import.template');
            Route::get('/import/errors', [ChannelAllocationController::class, 'importErrors'])->middleware('permission:media.create')->name('channel-allocation.import.errors');
            Route::post('/import/preview', [ChannelAllocationController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('channel-allocation.import.preview');
            Route::post('/import/confirm', [ChannelAllocationController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('channel-allocation.import.confirm');
            Route::post('/', [ChannelAllocationController::class, 'store'])->middleware('permission:media.create')->name('channel-allocation.store');
            Route::delete('/bulk', [ChannelAllocationController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('channel-allocation.bulk-destroy');
            Route::put('/{campaign}', [ChannelAllocationController::class, 'update'])->middleware('permission:media.edit')->whereNumber('campaign')->name('channel-allocation.update');
            Route::delete('/{campaign}', [ChannelAllocationController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('campaign')->name('channel-allocation.destroy');
            Route::post('/{campaign}/allocations', [ChannelAllocationController::class, 'storeAllocation'])->middleware('permission:media.create')->whereNumber('campaign')->name('channel-allocation.allocations.store');
            Route::delete('/{campaign}/allocations/bulk', [ChannelAllocationController::class, 'bulkDestroyAllocations'])->middleware('permission:media.delete')->whereNumber('campaign')->name('channel-allocation.allocations.bulk-destroy');
            Route::put('/{campaign}/allocations/{allocation}', [ChannelAllocationController::class, 'updateAllocation'])->middleware('permission:media.edit')->whereNumber('campaign')->whereNumber('allocation')->name('channel-allocation.allocations.update');
            Route::delete('/{campaign}/allocations/{allocation}', [ChannelAllocationController::class, 'destroyAllocation'])->middleware('permission:media.delete')->whereNumber('campaign')->whereNumber('allocation')->name('channel-allocation.allocations.destroy');
        });

        Route::prefix('campaigns')->middleware('module:campaigns')->group(function () {
            Route::get('/', [CampaignController::class, 'index'])->middleware('permission:media.view')->name('campaigns');
            Route::get('/export', [CampaignController::class, 'export'])->middleware('permission:media.export')->name('campaigns.export');
            Route::get('/import/template', [CampaignController::class, 'importTemplate'])->middleware('permission:media.create')->name('campaigns.import.template');
            Route::get('/import/errors', [CampaignController::class, 'importErrors'])->middleware('permission:media.create')->name('campaigns.import.errors');
            Route::post('/import/preview', [CampaignController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('campaigns.import.preview');
            Route::post('/import/confirm', [CampaignController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('campaigns.import.confirm');
            Route::post('/', [CampaignController::class, 'store'])->middleware('permission:media.create')->name('campaigns.store');
            Route::delete('/bulk', [CampaignController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('campaigns.bulk-destroy');
            Route::put('/{campaign}', [CampaignController::class, 'update'])->middleware('permission:media.edit')->whereNumber('campaign')->name('campaigns.update');
            Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('campaign')->name('campaigns.destroy');
        });

        Route::prefix('pdc-servers')->middleware('module:pdc-servers')->group(function () {
            Route::get('/', [PdcServerController::class, 'index'])->middleware('permission:media.view')->name('pdc-servers');
            Route::get('/export', [PdcServerController::class, 'export'])->middleware('permission:media.export')->name('pdc-servers.export');
            Route::get('/import/template', [PdcServerController::class, 'importTemplate'])->middleware('permission:media.create')->name('pdc-servers.import.template');
            Route::get('/import/errors', [PdcServerController::class, 'importErrors'])->middleware('permission:media.create')->name('pdc-servers.import.errors');
            Route::post('/import/preview', [PdcServerController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('pdc-servers.import.preview');
            Route::post('/import/confirm', [PdcServerController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('pdc-servers.import.confirm');
            Route::post('/', [PdcServerController::class, 'store'])->middleware('permission:media.create')->name('pdc-servers.store');
            Route::delete('/bulk', [PdcServerController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('pdc-servers.bulk-destroy');
            Route::put('/{group}', [PdcServerController::class, 'update'])->middleware('permission:media.edit')->whereNumber('group')->name('pdc-servers.update');
            Route::delete('/{group}', [PdcServerController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('group')->name('pdc-servers.destroy');
            Route::post('/{group}/servers', [PdcServerController::class, 'storeServer'])->middleware('permission:media.create')->whereNumber('group')->name('pdc-servers.servers.store');
            Route::delete('/{group}/servers/bulk', [PdcServerController::class, 'bulkDestroyServers'])->middleware('permission:media.delete')->whereNumber('group')->name('pdc-servers.servers.bulk-destroy');
            Route::put('/{group}/servers/{server}', [PdcServerController::class, 'updateServer'])->middleware('permission:media.edit')->whereNumber('group')->whereNumber('server')->name('pdc-servers.servers.update');
            Route::delete('/{group}/servers/{server}', [PdcServerController::class, 'destroyServer'])->middleware('permission:media.delete')->whereNumber('group')->whereNumber('server')->name('pdc-servers.servers.destroy');
        });

        Route::prefix('channel-range-list')->middleware('module:sip-channels')->group(function () {
            Route::get('/', [ChannelRangeListController::class, 'index'])->middleware('permission:media.view')->name('channel-range-list');
            Route::get('/export', [ChannelRangeListController::class, 'export'])->middleware('permission:media.export')->name('channel-range-list.export');
            Route::get('/import/template', [ChannelRangeListController::class, 'importTemplate'])->middleware('permission:media.create')->name('channel-range-list.import.template');
            Route::get('/import/errors', [ChannelRangeListController::class, 'importErrors'])->middleware('permission:media.create')->name('channel-range-list.import.errors');
            Route::post('/import/preview', [ChannelRangeListController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('channel-range-list.import.preview');
            Route::post('/import/confirm', [ChannelRangeListController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('channel-range-list.import.confirm');
            Route::post('/', [ChannelRangeListController::class, 'store'])->middleware('permission:media.create')->name('channel-range-list.store');
            Route::delete('/bulk', [ChannelRangeListController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('channel-range-list.bulk-destroy');
            Route::put('/{sipChannelNumber}', [ChannelRangeListController::class, 'update'])->middleware('permission:media.edit')->whereNumber('sipChannelNumber')->name('channel-range-list.update');
            Route::delete('/{sipChannelNumber}', [ChannelRangeListController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('sipChannelNumber')->name('channel-range-list.destroy');
        });

        Route::prefix('sip-channels')->middleware('module:sip-channels')->group(function () {
            Route::get('/', [SipChannelController::class, 'index'])->middleware('permission:media.view')->name('sip-channels');
            Route::get('/export', [SipChannelController::class, 'export'])->middleware('permission:media.export')->name('sip-channels.export');
            Route::get('/import/template', [SipChannelController::class, 'importTemplate'])->middleware('permission:media.create')->name('sip-channels.import.template');
            Route::get('/import/errors', [SipChannelController::class, 'importErrors'])->middleware('permission:media.create')->name('sip-channels.import.errors');
            Route::post('/import/preview', [SipChannelController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('sip-channels.import.preview');
            Route::post('/import/confirm', [SipChannelController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('sip-channels.import.confirm');
            Route::post('/', [SipChannelController::class, 'store'])->middleware('permission:media.create')->name('sip-channels.store');
            Route::delete('/bulk', [SipChannelController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('sip-channels.bulk-destroy');
            Route::put('/{sipChannel}', [SipChannelController::class, 'update'])->middleware('permission:media.edit')->whereNumber('sipChannel')->name('sip-channels.update');
            Route::delete('/{sipChannel}', [SipChannelController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('sipChannel')->name('sip-channels.destroy');
        });

        Route::prefix('archive-recordings')->middleware('module:archive-recordings')->group(function () {
            Route::get('/', [ArchiveRecordingController::class, 'index'])->middleware('permission:media.view')->name('archive-recordings');
            Route::get('/export', [ArchiveRecordingController::class, 'export'])->middleware('permission:media.export')->name('archive-recordings.export');
            Route::get('/import/template', [ArchiveRecordingController::class, 'importTemplate'])->middleware('permission:media.create')->name('archive-recordings.import.template');
            Route::get('/import/errors', [ArchiveRecordingController::class, 'importErrors'])->middleware('permission:media.create')->name('archive-recordings.import.errors');
            Route::post('/import/preview', [ArchiveRecordingController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('archive-recordings.import.preview');
            Route::post('/import/confirm', [ArchiveRecordingController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('archive-recordings.import.confirm');
            Route::post('/import/audio', [ArchiveRecordingController::class, 'importAudio'])->middleware(['permission:media.create', 'throttle:sensitive'])->name('archive-recordings.import.audio');
            Route::get('/{recording}/play', [ArchiveRecordingController::class, 'play'])->middleware('permission:media.view')->whereNumber('recording')->name('archive-recordings.play');
            Route::get('/{recording}/download', [ArchiveRecordingController::class, 'download'])->middleware('permission:media.view')->whereNumber('recording')->name('archive-recordings.download');
            Route::get('/{recording}/certificate', [ArchiveRecordingController::class, 'certificate'])->middleware('permission:media.view')->whereNumber('recording')->name('archive-recordings.certificate');
            Route::delete('/bulk', [ArchiveRecordingController::class, 'bulkDestroy'])->middleware('permission:media.delete')->name('archive-recordings.bulk-destroy');
            Route::delete('/{recording}', [ArchiveRecordingController::class, 'destroy'])->middleware('permission:media.delete')->whereNumber('recording')->name('archive-recordings.destroy');
        });

        foreach (array_keys(OperationCatalog::modules()) as $module) {
            if (in_array($module, ['pdc-servers', 'sip-channels', 'archive-recordings'], true)) {
                continue;
            }
            Route::get('/'.$module, [OperationsDataController::class, 'index'])->middleware(['permission:media.view', 'module:'.$module])->defaults('module', $module)->name($module);
            Route::get('/'.$module.'/export', [OperationsDataController::class, 'export'])->middleware(['permission:media.export', 'module:'.$module])->defaults('module', $module)->name($module.'.export');
            Route::get('/'.$module.'/import/template', [OperationsDataController::class, 'importTemplate'])->middleware(['permission:media.create', 'module:'.$module])->defaults('module', $module)->name($module.'.import.template');
            Route::get('/'.$module.'/import/errors', [OperationsDataController::class, 'importErrors'])->middleware(['permission:media.create', 'module:'.$module])->defaults('module', $module)->name($module.'.import.errors');
            Route::post('/'.$module.'/import/preview', [OperationsDataController::class, 'importPreview'])->middleware(['permission:media.create', 'throttle:sensitive', 'module:'.$module])->defaults('module', $module)->name($module.'.import.preview');
            Route::post('/'.$module.'/import/confirm', [OperationsDataController::class, 'importConfirm'])->middleware(['permission:media.create', 'throttle:sensitive', 'module:'.$module])->defaults('module', $module)->name($module.'.import.confirm');
            Route::post('/'.$module, [OperationsDataController::class, 'store'])->middleware(['permission:media.create', 'module:'.$module])->defaults('module', $module)->name($module.'.store');
            Route::delete('/'.$module.'/bulk', [OperationsDataController::class, 'bulkDestroy'])->middleware(['permission:media.delete', 'module:'.$module])->defaults('module', $module)->name($module.'.bulk-destroy');
            Route::put('/'.$module.'/{id}', [OperationsDataController::class, 'update'])->middleware(['permission:media.edit', 'module:'.$module])->defaults('module', $module)->whereNumber('id')->name($module.'.update');
            Route::delete('/'.$module.'/{id}', [OperationsDataController::class, 'destroy'])->middleware(['permission:media.delete', 'module:'.$module])->defaults('module', $module)->whereNumber('id')->name($module.'.destroy');
        }
    });
});
