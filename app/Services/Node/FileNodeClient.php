<?php

namespace App\Services\Node;

use App\Models\Cell;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class FileNodeClient
{
    public function __construct(
        private NodeClient $nodeClient
    ) {}

    public function files(Cell $cell, string $path = '', int $page = 1, int $perPage = 250): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(250, $perPage));

        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/files", [
                'path' => $path,
                'page' => $page,
                'per_page' => $perPage,
            ])
            ->throw()
            ->json();
    }

    public function downloadFile(Cell $cell, string $path): Response
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/files/download", [
                'path' => $path,
            ])
            ->throw();
    }

    public function readFile(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/files/read", [
                'path' => $path,
            ])
            ->throw()
            ->json();
    }

    public function writeFile(Cell $cell, string $path, string $content): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/files/write", [
                'path' => $path,
                'content' => $content,
            ])
            ->throw()
            ->json();
    }

    public function deleteFile(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->delete("/cells/{$cell->daemon_id}/files/delete?path=" . urlencode($path))
            ->throw()
            ->json();
    }

    public function restoreFile(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/files/restore", [
                'path' => $path,
            ])
            ->throw()
            ->json();
    }

    public function permanentDeleteFile(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->delete("/cells/{$cell->daemon_id}/files/permanent?path=" . urlencode($path))
            ->throw()
            ->json();
    }

    public function createFile(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/files/file", [
                'path' => $path,
            ])
            ->throw()
            ->json();
    }

    public function createFolder(Cell $cell, string $path): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/files/folder", [
                'path' => $path,
            ])
            ->throw()
            ->json();
    }

    public function uploadFromUrl(Cell $cell, string $path, string $url): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/files/upload-url", [
                'path' => $path,
                'url' => $url,
            ])
            ->throw()
            ->json();
    }

    public function uploadFile(Cell $cell, string $path, UploadedFile $file): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->attach(
                'file',
                File::get($file->getRealPath()),
                $file->getClientOriginalName()
            )
            ->post("/cells/{$cell->daemon_id}/files/upload", [
                'path' => $path,
            ])
            ->throw()
            ->json();
    }
}