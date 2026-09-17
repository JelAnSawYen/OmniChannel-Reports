<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MailSetting;
use App\Services\Admin\DatabaseBackupService;
use App\Services\Logs\AuditLogger;
use App\Support\EnvWriter;
use App\Support\MailFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MaintenanceController extends Controller
{
    public function index(DatabaseBackupService $backups)
    {
        return view('maintenance.index', [
            'backups' => $backups->list(),
            'mailSettings' => MailSetting::current(),
            'databaseDriverLabel' => $backups->driverLabel(),
        ]);
    }

    public function saveMail(Request $request)
    {
        $v = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $row = MailSetting::query()->first() ?? new MailSetting;
        $row->host = $v['host'];
        $row->port = $v['port'];
        $row->username = $v['username'];
        $row->from_address = $v['from_address'];
        $row->from_name = $v['from_name'];
        if (filled($v['password'])) {
            $row->password = $v['password'];
        }
        if (! filled($row->password) && ! filled(config('mail.mailers.smtp.password'))) {
            return back()->with('error', 'Enter the App Password for the sending mailbox.')->withInput();
        }
        $row->save();
        $row->applyToConfig();

        $env = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $v['host'],
            'MAIL_PORT' => (string) $v['port'],
            'MAIL_SCHEME' => ((int) $v['port'] === 465) ? 'smtps' : 'smtp',
            'MAIL_USERNAME' => $v['username'],
            'MAIL_FROM_ADDRESS' => $v['from_address'],
            'MAIL_FROM_NAME' => $v['from_name'],
            'QUEUE_CONNECTION' => 'sync',
        ];
        if (filled($v['password'])) {
            $env['MAIL_PASSWORD'] = $v['password'];
        }
        EnvWriter::set($env);

        AuditLogger::log('Updated', 'Maintenance', 'Updated outgoing email delivery settings', null, $request);

        return back()->with('success', 'Email delivery settings saved. Verification and password-reset messages are sent to each user’s own address.');
    }

    public function testMail(Request $request)
    {
        $to = $request->user()->email;
        if (! MailSetting::isConfigured()) {
            return back()->with('error', 'Save the sending mailbox first, then send a test to '.$to.'.');
        }
        try {
            Mail::raw('OmniChannel Reports can deliver mail to '.$to.'.', function ($message) use ($to) {
                $message->to($to)->subject('OmniChannel Reports email test');
            });
        } catch (Throwable $e) {
            return back()->with('error', MailFailure::message($e));
        }

        return back()->with('success', 'A test message was sent to '.$to.'.');
    }

    public function backup(Request $request, DatabaseBackupService $backups)
    {
        try {
            $backups->create();
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Database backup created successfully.');
    }

    public function download(string $filename, DatabaseBackupService $backups)
    {
        abort_unless($backups->isDownloadable($filename), 404);
        $diskPath = 'backups/'.$filename;
        abort_unless(Storage::exists($diskPath), 404);

        return Storage::download($diskPath, $filename);
    }

    public function restore(Request $request, DatabaseBackupService $backups)
    {
        $request->validate(['backup_file' => ['required', 'file', 'max:51200', 'extensions:sqlite,db,sql']]);
        $uploaded = $request->file('backup_file');
        $source = $uploaded->getRealPath();
        if (! $source || ! is_file($source)) {
            return back()->with('error', 'The selected backup file could not be read.');
        }
        try {
            $backups->restoreUploaded($source, (string) $uploaded->getClientOriginalName());
        } catch (Throwable $exception) {
            AuditLogger::log('Restore Rejected', 'Maintenance', $exception->getMessage(), null, $request);

            return back()->with('error', $exception->getMessage());
        }
        AuditLogger::log('Restore Started', 'Maintenance', 'Restoring '.$backups->driverLabel().' database from uploaded backup', null, $request);

        return back()->with('success', 'Database restored successfully. Restart the Laravel server if the current request cache still shows old data.');
    }

    public function clearLogs(Request $request)
    {
        AuditLog::query()->whereNotIn('module', AuditLog::PROTECTED_MODULES)->delete();
        AuditLogger::log('Cleared Logs', 'Maintenance', 'Cleared operational activity logs. Security records were kept.', null, $request);

        return back()->with('success', 'Operational activity logs were cleared. Security and permission records were kept.');
    }
}
