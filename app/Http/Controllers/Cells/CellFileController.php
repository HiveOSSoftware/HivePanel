<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Enums\BackupMountStatus;
use App\Models\Cell;
use App\Models\SftpCredential;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Node\BackupNodeClient;
use App\Services\Node\CellNodeClient;
use App\Services\Node\FileNodeClient;
use App\Services\Sftp\SftpAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CellFileController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells, SftpAccessService $sftpAccess) {
        $cell = $this->panelCellOrFail($id);

        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $workerCell = $this->getCellOrFail($cell, $cells);
        $user = request()->user();

        if ($this->isLocked($workerCell)) {
            return $this->lockedPage($workerCell);
        }

        $sftpPermissions = $sftpAccess->resolve(
            $cell,
            $user,
        );

        $credential = $cell->sftpCredentials()
            ->where('user_id', $user->id)
            ->first();

        $mountedBackup = $this->mountedBackup($cell);

        return Inertia::render('Cells/Files', [
            'cell' => $workerCell,
            'mode' => 'live',
            'initialPath' => '',

            'mount' => null,
            'backup' => null,

            'mountedBackup' => $mountedBackup
                ? $this->mountedBackupData($mountedBackup)
                : null,

            'sftp' => [
                'enabled' => (bool) (
                    $cell->node?->sftp_enabled &&
                    $sftpPermissions !== null
                ),

                'host' => $cell->node?->sftpHost(),
                'port' => $cell->node?->sftp_port ?? 2022,

                'username' => $credential?->username
                    ?? $this->sftpUsername(
                        $cell,
                        $user,
                    ),

                'has_password' => (bool) (
                    $credential &&
                    $credential->revoked_at === null
                ),

                'last_used_at' => $credential
                    ?->last_used_at
                    ?->toISOString(),

                'permissions' => $sftpPermissions,
            ],
        ]);
    }

    public function json(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, BackupNodeClient $backups) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $page = max(
            1,
            $request->integer('page', 1),
        );

        $perPage = min(
            500,
            max(
                1,
                $request->integer('per_page', 250),
            ),
        );

        $path = trim(
            (string) $request->query('path', ''),
            '/',
        );

        if ($this->isMountedBackupPath($path)) {
            return response()->json(
                $this->mountedBackupFiles(
                    $cell,
                    $path,
                    $page,
                    $perPage,
                    $backups,
                ),
            );
        }

        $result = $files->files(
            $cell,
            $path,
            $page,
            $perPage,
        );

        if ($path === '') {
            $mountedBackup = $this->mountedBackup($cell);

            if ($mountedBackup) {
                $virtualDirectory = $this->mountedBackupDirectory(
                    $mountedBackup,
                );

                if (
                    isset($result['files']) &&
                    is_array($result['files'])
                ) {
                    array_unshift(
                        $result['files'],
                        $virtualDirectory,
                    );

                    if (
                        isset($result['pagination']) &&
                        is_array($result['pagination'])
                    ) {
                        if (isset($result['pagination']['total'])) {
                            $result['pagination']['total']++;
                        }

                        if (isset($result['pagination']['to'])) {
                            $result['pagination']['to']++;
                        }
                    }
                } elseif (array_is_list($result)) {
                    array_unshift(
                        $result,
                        $virtualDirectory,
                    );
                }
            }
        }

        return response()->json($result);
    }

    public function download(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $this->abortIfLocked($cell, $cells);

        $path = trim(
            (string) $request->query('path', ''),
        );

        abort_if(
            $path === '',
            400,
            'Missing file path.',
        );

        $audit->log(
            AuditEvent::FILE_DOWNLOADED,
            $cell,
            "File \"{$path}\" was downloaded.",
            [
                'path' => $path,
            ],
        );

        return $files->downloadFile(
            $cell,
            $path,
        );
    }

    public function edit(string $id, Request $request, CellNodeClient $cells) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $workerCell = $this->getCellOrFail($cell, $cells);

        if ($this->isLocked($workerCell)) {
            return $this->lockedPage($workerCell);
        }

        return Inertia::render('Cells/FileEditor', [
            'cell' => $workerCell,
            'path' => $request->query('path', ''),
        ]);
    }

    public function read(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        return response()->json(
            $files->readFile(
                $cell,
                $request->query('path', ''),
            ),
        );
    }

    public function write(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        $result = $files->writeFile(
            $cell,
            $data['path'],
            $data['content'],
        );

        $audit->log(
            AuditEvent::FILE_EDITED,
            $cell,
            "File \"{$data['path']}\" was edited.",
            [
                'path' => $data['path'],
            ],
        );

        return response()->json($result);
    }

    public function delete(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $path = $request->query('path');

        abort_unless(
            $path,
            400,
            'Missing file path.',
        );

        $result = $files->deleteFile($cell, $path);

        $audit->log(
            AuditEvent::FILE_DELETED,
            $cell,
            "File \"{$path}\" was moved to trash.",
            [
                'path' => $path,
            ],
        );

        return response()->json($result);
    }

    public function restore(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $result = $files->restoreFile(
            $cell,
            $data['path'],
        );

        $audit->log(
            AuditEvent::FILE_RESTORED,
            $cell,
            "File \"{$data['path']}\" was restored.",
            [
                'path' => $data['path'],
            ],
        );

        return response()->json($result);
    }

    public function permanent(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $path = $request->query('path');

        abort_unless(
            $path,
            400,
            'Missing file path.',
        );

        $result = $files->permanentDeleteFile(
            $cell,
            $path,
        );

        $audit->log(
            AuditEvent::FILE_DELETED,
            $cell,
            "File \"{$path}\" was permanently deleted.",
            [
                'path' => $path,
                'permanent' => true,
            ],
        );

        return response()->json($result);
    }

    public function createFile(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $result = $files->createFile(
            $cell,
            $data['path'],
        );

        $audit->log(
            AuditEvent::FILE_CREATED,
            $cell,
            "File \"{$data['path']}\" was created.",
            [
                'path' => $data['path'],
            ],
        );

        return response()->json($result);
    }

    public function createFolder(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $result = $files->createFolder(
            $cell,
            $data['path'],
        );

        $audit->log(
            AuditEvent::FOLDER_CREATED,
            $cell,
            "Folder \"{$data['path']}\" was created.",
            [
                'path' => $data['path'],
            ],
        );

        return response()->json($result);
    }

    public function uploadFromUrl(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
            'url' => ['required', 'url'],
        ]);

        $result = $files->uploadFromUrl(
            $cell,
            $data['path'],
            $data['url'],
        );

        $audit->log(
            AuditEvent::FILE_UPLOADED,
            $cell,
            "File was uploaded from URL to \"{$data['path']}\".",
            [
                'path' => $data['path'],
                'url' => $data['url'],
                'source' => 'url',
            ],
        );

        return response()->json($result);
    }

    public function upload(string $id, Request $request, CellNodeClient $cells, FileNodeClient $files, AuditLogger $audit) {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked($cell, $cells);

        $request->validate([
            'file' => ['required', 'file'],
            'relative_path' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $basePath = trim(
            $request->query('path', ''),
            '/',
        );

        $relativePath = $request->input('relative_path')
            ?: $file->getClientOriginalName();

        $targetPath = trim(
            $basePath
                ? "{$basePath}/{$relativePath}"
                : $relativePath,
            '/',
        );

        $result = $files->uploadFile(
            $cell,
            $targetPath,
            $file,
        );

        $audit->log(
            AuditEvent::FILE_UPLOADED,
            $cell,
            "File \"{$targetPath}\" was uploaded.",
            [
                'path' => $targetPath,
                'size' => $file->getSize(),
                'source' => 'local',
            ],
        );

        return response()->json($result);
    }

    private function isMountedBackupPath(string $path): bool
    {
        return $path === '__backup_mount__'
            || str_starts_with(
                $path,
                '__backup_mount__/',
            );
    }

    private function mountedBackupFiles(Cell $cell, string $path, int $page, int $perPage, BackupNodeClient $backups): array  {
        $segments = explode(
            '/',
            $path,
        );

        $mountID = $segments[1] ?? '';

        if ($mountID === '') {
            return [
                'path' => '',
                'files' => [],
                'pagination' => [
                    'page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
            ];
        }

        $mount = $cell->backupMounts()
            ->with('backup')
            ->whereKey($mountID)
            ->where(
                'status',
                BackupMountStatus::MOUNTED,
            )
            ->firstOrFail();

        abort_if(
            $mount->hasExpired(),
            409,
            'This backup mount has expired.',
        );

        $relativePath = implode(
            '/',
            array_slice(
                $segments,
                2,
            ),
        );

        $result = $backups->mountedBackupFiles(
            cell: $cell,
            mount: $mount,
            path: $relativePath,
            page: $page,
            perPage: $perPage,
        );

        return $this->prefixMountedBackupPaths(
            $result,
            $mount->id,
        );
    }

    private function prefixMountedBackupPaths(array $result, string $mountID): array  {
        $prefix = '__backup_mount__/' . $mountID;

        if (
            isset($result['files']) &&
            is_array($result['files'])
        ) {
            $result['files'] = array_map(
                fn (array $entry): array => $this->prefixMountedBackupEntry(
                    $entry,
                    $prefix,
                    $mountID,
                ),
                $result['files'],
            );

            if (isset($result['path'])) {
                $relativePath = trim(
                    (string) $result['path'],
                    '/',
                );

                $result['path'] = $relativePath === ''
                    ? $prefix
                    : $prefix . '/' . $relativePath;
            }

            return $result;
        }

        if (array_is_list($result)) {
            return array_map(
                fn (array $entry): array => $this->prefixMountedBackupEntry(
                    $entry,
                    $prefix,
                    $mountID,
                ),
                $result,
            );
        }

        return $result;
    }

    private function prefixMountedBackupEntry(array $entry, string $prefix, string $mountID): array  {
        $entryPath = trim(
            (string) ($entry['path'] ?? $entry['name'] ?? ''),
            '/',
        );

        $entry['path'] = $entryPath === ''
            ? $prefix
            : $prefix . '/' . $entryPath;

        $entry['virtual'] = true;
        $entry['virtual_type'] = 'backup_mount_item';
        $entry['mount_id'] = $mountID;
        $entry['read_only'] = true;

        return $entry;
    }

    private function mountedBackup(Cell $cell)
    {
        return $cell->backupMounts()
            ->with([
                'backup:id,name',
            ])
            ->where(
                'status',
                BackupMountStatus::MOUNTED,
            )
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now(),
                    );
            })
            ->latest('mounted_at')
            ->first();
    }

    private function mountedBackupData($mount): array
    {
        return [
            'id' => $mount->id,
            'backup_id' => $mount->backup_id,

            'name' => $mount->backup?->name
                ?: 'Mounted Backup',

            'status' => $mount->status->value,
            'read_only' => true,

            'mounted_at' => $mount
                ->mounted_at
                ?->toIso8601String(),

            'expires_at' => $mount
                ->expires_at
                ?->toIso8601String(),
        ];
    }

    private function mountedBackupDirectory($mount): array
    {
        return [
            'name' => $mount->backup?->name
                ?: 'Mounted Backup',

            'path' => '__backup_mount__/' . $mount->id,

            'type' => 'directory',
            'is_directory' => true,
            'size' => 0,

            'modified_at' => $mount
                ->mounted_at
                ?->toIso8601String(),

            'virtual' => true,
            'virtual_type' => 'backup_mount',

            'mount_id' => $mount->id,
            'backup_id' => $mount->backup_id,

            'backup_name' => $mount->backup?->name
                ?: 'Mounted Backup',

            'expires_at' => $mount
                ->expires_at
                ?->toIso8601String(),

            'read_only' => true,
        ];
    }

    private function sftpUsername(Cell $cell, User $user): string
    {
        $accountUsername = filled($user->username)
            ? $user->username
            : Str::slug($user->name, '');

        $accountUsername = strtolower(
            (string) $accountUsername,
        );

        $accountUsername = preg_replace(
            '/[^a-z0-9_-]/',
            '',
            $accountUsername,
        ) ?? '';

        $accountUsername = substr(
            $accountUsername,
            0,
            32,
        );

        if ($accountUsername === '') {
            $accountUsername = 'user';
        }

        $cellSuffix = substr(
            str_replace('-', '', (string) $cell->id),
            0,
            8,
        );

        return "{$accountUsername}.{$cellSuffix}";
    }
}