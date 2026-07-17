<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Cell;
use App\Models\SftpCredential;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Services\Node\FileNodeClient;
use App\Services\Sftp\SftpAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CellFileController extends CellBaseController
{
    public function index(
        string $id,
        CellNodeClient $cells,
        SftpAccessService $sftpAccess,
    ) {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);
        $user = request()->user();

        if ($this->isLocked($workerCell)) {
            return $this->lockedPage($workerCell);
        }

        $sftpPermissions = $sftpAccess->resolve($cell, $user);

        $credential = $cell->sftpCredentials()
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('Cells/Files', [
            'cell' => $workerCell,

            'sftp' => [
                'enabled' => (bool) (
                    $cell->node?->sftp_enabled &&
                    $sftpPermissions !== null
                ),

                'host' => $cell->node?->sftpHost(),
                'port' => $cell->node?->sftp_port ?? 2022,

                'username' => $credential?->username
                    ?? $this->sftpUsername($cell, $user),

                'has_password' => (bool) (
                    $credential &&
                    $credential->revoked_at === null
                ),

                'last_used_at' => $credential?->last_used_at?->toISOString(),

                'permissions' => $sftpPermissions,
            ],
        ]);
    }

    public function json(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        return response()->json(
            $files->files(
                $cell,
                $request->query('path', ''),
            ),
        );
    }

    public function download(
        string $id,
        Request $request,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);
        $path = $request->query('path', '');

        $audit->log(
            AuditEvent::FILE_DOWNLOADED,
            $cell,
            "File \"{$path}\" was downloaded.",
            [
                'path' => $path,
            ],
        );

        return $files->downloadFile($cell, $path);
    }

    public function edit(
        string $id,
        Request $request,
        CellNodeClient $cells,
    ) {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        if ($this->isLocked($workerCell)) {
            return $this->lockedPage($workerCell);
        }

        return Inertia::render('Cells/FileEditor', [
            'cell' => $workerCell,
            'path' => $request->query('path', ''),
        ]);
    }

    public function read(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        return response()->json(
            $files->readFile(
                $cell,
                $request->query('path', ''),
            ),
        );
    }

    public function write(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function delete(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function restore(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function permanent(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function createFile(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function createFolder(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function uploadFromUrl(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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

    public function upload(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

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