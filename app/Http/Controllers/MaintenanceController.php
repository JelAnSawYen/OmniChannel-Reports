<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use App\Models\MailSetting;
use App\Services\AuditLogger;
use App\Support\EnvWriter;
use App\Support\MailFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
class MaintenanceController extends Controller
{
    public function index()
    {
        $backups = collect(Storage::files('backups'))->filter(fn($p)=>str_ends_with($p,'.sqlite'))->sortDesc()->values();
        return view('maintenance.index', ['backups'=>$backups, 'mailSettings'=>MailSetting::current()]);
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
        } catch (\Throwable $e) {
            return back()->with('error', MailFailure::message($e));
        }

        return back()->with('success', 'A test message was sent to '.$to.'.');
    }
    public function backup(Request $request)
    {
        $source = database_path('database.sqlite');
        if (!is_file($source)) return back()->with('error','SQLite database file was not found.');
        Storage::makeDirectory('backups');
        $name='backups/backup_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        Storage::put($name, file_get_contents($source));
        AuditLogger::log('Created Backup','Maintenance','Created SQLite database backup',null,$request);
        return back()->with('success','Database backup created successfully.');
    }
    public function download(string $filename)
    {
        abort_unless(preg_match('/^backup_[0-9_-]+\.sqlite$/',$filename),404);
        $diskPath = 'backups/'.$filename;
        abort_unless(Storage::exists($diskPath),404);
        return Storage::download($diskPath, $filename);
    }

    public function restore(Request $request)
    {
        $request->validate(['backup_file'=>['required','file','max:51200','extensions:sqlite,db']]);
        $uploaded=$request->file('backup_file');
        $source=$uploaded->getRealPath();
        if (!$source || !is_file($source)) return back()->with('error','The selected backup file could not be read.');
        $header = (string) file_get_contents($source, false, null, 0, 16);
        if ($header !== "SQLite format 3\0") {
            AuditLogger::log('Restore Rejected','Maintenance','Uploaded restore file was not a valid SQLite database',null,$request);
            return back()->with('error','The uploaded file is not a valid SQLite database.');
        }
        $current=database_path('database.sqlite');
        Storage::makeDirectory('backups');
        $safety='backups/pre_restore_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        if (is_file($current)) Storage::put($safety,file_get_contents($current));
        AuditLogger::log('Restore Started','Maintenance','Restoring SQLite database from uploaded backup',null,$request);
        DB::disconnect('sqlite');
        if (!copy($source,$current)) return back()->with('error','Database restore failed.');
        return back()->with('success','Database restored successfully. Restart the Laravel server if the current request cache still shows old data.');
    }

    public function clearLogs(Request $request)
    {
        AuditLog::query()->whereNotIn('module', AuditLog::PROTECTED_MODULES)->delete();
        AuditLogger::log('Cleared Logs','Maintenance','Cleared operational activity logs. Security records were kept.',null,$request);
        return back()->with('success','Operational activity logs were cleared. Security and permission records were kept.');
    }
}
