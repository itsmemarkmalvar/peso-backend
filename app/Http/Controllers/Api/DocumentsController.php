<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Document::query()->orderByDesc('created_at')->with('uploader:id,name');
        if (!$user || !$user->isAdmin()) {
            $query->where('is_active', true);
        }

        $documents = $query->get()->map(function (Document $document) {
            return $this->formatDocument($document);
        });

        return $this->success($documents, 'Documents');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return $this->forbidden('Only admins can upload documents.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $file = $request->file('file');
        if (!$file) {
            return $this->validationError(['file' => ['Document file is required.']]);
        }

        $path = $file->store('documents', 'public');

        $document = Document::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize() ?: 0,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'uploaded_by' => $user->id,
            'is_active' => true,
        ]);

        $document->load('uploader:id,name');

        return $this->success(
            $this->formatDocument($document),
            'Document uploaded',
            201
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return $this->forbidden('Only admins can remove documents.');
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->notFound('Document not found.');
        }

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return $this->success(null, 'Document removed');
    }

    public function download(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('Unauthorized');
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->notFound('Document not found.');
        }
        if (!$document->is_active && !$user->isAdmin()) {
            return $this->forbidden('Document not available.');
        }
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return $this->notFound('Document file missing.');
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    private function formatDocument(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'file_name' => $document->file_name,
            'file_size' => $document->file_size,
            'mime_type' => $document->mime_type,
            'is_active' => $document->is_active,
            'uploaded_by' => $document->uploader
                ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                ]
                : null,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
