<?php

namespace App\Http\Controllers;

use App\Services\Admin\Activity;
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AdminDocumentController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            abort_unless(config('dashboard.enabled'), 503);

            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException $error) {
            return back()->withErrors($error->errors());
        } catch (\Throwable $error) {
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;

            return response()->view('admin.unavailable', compact('status'), $status);
        }
    }

    public function index(Request $request, DocumentStore $store)
    {
        $filters = $request->validate(['type' => ['nullable', Rule::in(array_keys(DocumentData::TYPES))], 'status' => ['nullable', Rule::in(['draft', 'issued'])], 'q' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1|max:100000']);
        $documents = array_values(array_filter($store->all(), fn ($record) => (empty($filters['type']) || $record['data']['type'] === $filters['type']) && (empty($filters['status']) || $record['status'] === $filters['status']) && (empty($filters['q']) || mb_stripos(implode(' ', [$record['number'] ?? '', $record['data']['customer']['name'], $record['data']['customer']['company'], $record['data']['order_id'] ?? '']), $filters['q']) !== false)));
        $page = $filters['page'] ?? 1;
        $paginator = new LengthAwarePaginator(array_slice($documents, ($page - 1) * 20, 20), count($documents), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.documents.index', ['documents' => $paginator, 'filters' => $filters]);
    }

    public function create(Request $request, DocumentStore $store)
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(DocumentData::TYPES))], 'order_id' => 'nullable|uuid']);
        $record = $store->create($data['type'], $data['order_id'] ?? null);

        return to_route('admin.documents.edit', ['id' => $record['id']])->with('admin_message', 'Brouillon créé. Aucun numéro ni paiement attribué.');
    }

    public function edit(DocumentStore $store, DocumentData $data, string $id)
    {
        $record = $store->find($id);

        return view('admin.documents.edit', ['record' => $record, 'data' => $record['data'], 'missing' => $record['status'] === 'draft' ? $data->missing($record['data']) : [], 'series' => $record['status'] === 'draft' ? $store->currentSeries($record['data']['type']) : null]);
    }

    public function save(Request $request, DocumentStore $store, string $id)
    {
        $request->validate(['revision' => 'required|integer|min:1']);
        $store->save($id, $request->integer('revision'), $request->all());

        return to_route('admin.documents.edit', compact('id'))->with('admin_message', 'Brouillon enregistré. Le total est recalculé côté serveur.');
    }

    public function issue(Request $request, DocumentStore $store, Activity $activity, string $id)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'confirmed' => 'required|accepted', 'password' => 'required|string|max:200', 'prefix' => 'nullable|string|max:26', 'first_sequence' => 'nullable|integer|min:1|max:99999999', 'series_confirmed' => 'sometimes|accepted']);
        if (! Hash::check($data['password'], config('admin.password_hash'))) {
            throw ValidationException::withMessages(['password' => 'Mot de passe administrateur incorrect.']);
        }
        $record = $store->issue($id, (int) $data['revision'], $data);
        // Issuance is already committed. A notification failure must never invalidate it or allocate again.
        try {
            $activity->record('document_issued', $record['number'], key: $id);
        } catch (\Throwable) {
            // The encrypted document includes the authoritative issue history.
        }

        return to_route('admin.documents.edit', compact('id'))->with('admin_message', 'Document émis et archivé. Téléchargez le PDF pour le remettre au client. Aucun email envoyé.');
    }

    public function duplicate(Request $request, DocumentStore $store, string $id)
    {
        $source = $store->find($id);
        $input = $request->validate(['type' => ['nullable', Rule::in(array_keys(DocumentData::TYPES))]]);
        $record = $store->create($input['type'] ?? $source['data']['type'], copy: $source['data'], source: ['id' => $source['id'], 'revision' => $source['revision'], 'number' => $source['number'], 'type' => $source['data']['type']]);

        return to_route('admin.documents.edit', ['id' => $record['id']])->with('admin_message', 'Nouveau brouillon créé. Le document d’origine est conservé.');
    }

    public function download(DocumentStore $store, string $id)
    {
        $record = $store->find($id);
        $name = ($record['number'] ?? 'BROUILLON-'.$record['id']).'.pdf';

        return response($store->pdf($record), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }
}
