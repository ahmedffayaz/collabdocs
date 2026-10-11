<?php

namespace App\Http\Controllers;

use App\Helpers\ApiJsonResponseHelper;
use App\Models\Document;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Support\DocumentContent;
use App\Support\DocumentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $payload = [
            'user' => $user->only('id', 'name', 'email'),
            'users' => $this->users->selectableAccounts(),
            'documents' => $this->documents->visibleTo($user)
                ->map(fn (Document $document) => DocumentPresenter::make($document, $user))
                ->values(),
        ];

        return ApiJsonResponseHelper::successResponse($payload, 'Workspace loaded.');
    }

    public function switchUser(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, ['email' => ['required', 'email', 'exists:users,email']]);
        if ($data instanceof JsonResponse) return $data;

        $user = $this->users->findByEmail($data['email']);
        $request->session()->put('active_user_id', $user->id);

        return ApiJsonResponseHelper::successResponse(['user' => $user->only('id', 'name', 'email')], 'Account switched.');
    }

    public function show(Request $request, string $document): JsonResponse
    {
        $user = $this->actor($request);
        $record = $this->findDocument($document);
        if ($record instanceof JsonResponse) return $record;

        if ($this->documents->permissionFor($record, $user) === null) {
            return $this->failure('You do not have access to this document.', 403);
        }

        return ApiJsonResponseHelper::successResponse(
            DocumentPresenter::make($record, $user, true),
            'Document loaded.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['nullable', 'string', 'max:500000'],
        ]);
        if ($data instanceof JsonResponse) return $data;

        $user = $this->actor($request);
        $document = $this->documents->create($user, [
            'title' => trim($data['title'] ?? '') ?: 'Untitled document',
            'content' => DocumentContent::sanitize($data['content'] ?? ''),
        ]);
        $document = $this->documents->find($document->id);

        return ApiJsonResponseHelper::apiJsonResponse(
            DocumentPresenter::make($document, $user, true),
            201,
            'Document created.',
            'true',
        );
    }

    public function update(Request $request, string $document): JsonResponse
    {
        $user = $this->actor($request);
        $record = $this->findDocument($document);
        if ($record instanceof JsonResponse) return $record;

        $permission = $this->documents->permissionFor($record, $user);
        if ($permission === null) return $this->failure('You do not have access to this document.', 403);
        if ($permission === 'viewer') return $this->failure('This document is view only.', 403);

        $data = $this->validateInput($request, [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'content' => ['sometimes', 'nullable', 'string', 'max:500000'],
        ]);
        if ($data instanceof JsonResponse) return $data;
        if (array_key_exists('content', $data)) {
            $data['content'] = DocumentContent::sanitize($data['content'] ?? '');
        }

        $record = $this->documents->update($record, $data);

        return ApiJsonResponseHelper::successResponse(DocumentPresenter::make($record, $user, true), 'Document saved.');
    }

    public function destroy(Request $request, string $document): JsonResponse
    {
        $user = $this->actor($request);
        $record = $this->findDocument($document);
        if ($record instanceof JsonResponse) return $record;
        if ($record->owner_id !== $user->id) return $this->failure('Only the owner can delete this document.', 403);

        $this->documents->delete($record);

        return ApiJsonResponseHelper::successResponse(['id' => (int) $document], 'Document deleted.');
    }

    public function share(Request $request, string $document): JsonResponse
    {
        $user = $this->actor($request);
        $record = $this->findDocument($document);
        if ($record instanceof JsonResponse) return $record;
        if ($record->owner_id !== $user->id) return $this->failure('Only the owner can manage access.', 403);

        $data = $this->validateInput($request, [
            'email' => ['required', 'email', 'exists:users,email'],
            'permission' => ['required', 'in:editor,viewer'],
        ]);
        if ($data instanceof JsonResponse) return $data;

        $recipient = $this->users->findByEmail($data['email']);
        if ($recipient->id === $user->id) return $this->failure('You already own this document.', 422);

        $record = $this->documents->grantAccess($record, $recipient, $data['permission']);

        return ApiJsonResponseHelper::successResponse(DocumentPresenter::make($record, $user, true), 'Access updated.');
    }

    public function import(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'file' => ['required', 'file', 'max:2048', 'mimes:txt,md'],
        ]);
        if ($data instanceof JsonResponse) return $data;

        $file = $request->file('file');
        $text = mb_convert_encoding(file_get_contents($file->getRealPath()), 'UTF-8', 'UTF-8');
        $html = $file->getClientOriginalExtension() === 'md'
            ? Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : '<p>'.nl2br(e($text)).'</p>';

        $user = $this->actor($request);
        $document = $this->documents->create($user, [
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Imported document',
            'content' => DocumentContent::sanitize($html),
        ]);
        $document = $this->documents->find($document->id);

        return ApiJsonResponseHelper::apiJsonResponse(
            DocumentPresenter::make($document, $user, true),
            201,
            'File imported.',
            'true',
        );
    }

    private function actor(Request $request): User
    {
        return $this->users->find($request->session()->get('active_user_id', 1));
    }

    private function validateInput(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        return $validator->fails()
            ? ApiJsonResponseHelper::apiValidationFailResponse($validator)
            : $validator->validated();
    }

    private function findDocument(string $id): Document|JsonResponse
    {
        return $this->documents->find($id) ?? $this->failure('Document not found.', 404);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        if ($status === 400) return ApiJsonResponseHelper::errorResponse($message);

        return ApiJsonResponseHelper::apiJsonResponse([], $status, $message, 'false');
    }
}
