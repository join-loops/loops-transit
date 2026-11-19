<?php

namespace App\Http\Controllers;

use App\Models\LogLeaf;
use App\Transit\Log\MerkleLog;
use App\Transit\TransitException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(protected MerkleLog $log) {}

    public function head(): JsonResponse
    {
        return response()->json($this->log->head()->toPublic(), 200, ['Cache-Control' => 'public, max-age=10']);
    }

    public function entries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'integer', 'min:0'],
            'end' => ['nullable', 'integer', 'gte:start'],
        ]);

        $start = (int) $data['start'];
        $end = (int) ($data['end'] ?? $start + MerkleLog::MAX_ENTRIES - 1);

        return response()->json([
            'size' => $this->log->size(),
            'entries' => $this->log->entries($start, $end)->map(fn (LogLeaf $leaf) => $leaf->toPublic())->values(),
        ]);
    }

    public function inclusion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'index' => ['required_without:hash', 'nullable', 'integer', 'min:0'],
            'hash' => ['required_without:index', 'nullable', 'string', 'max:71'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        $index = isset($data['index']) ? (int) $data['index'] : $this->log->findLeaf((string) $data['hash'])?->leaf_index;

        if ($index === null) {
            throw new TransitException('No leaf has that hash', 'not_found', 404);
        }

        return response()->json($this->log->inclusion($index, isset($data['size']) ? (int) $data['size'] : null));
    }

    public function consistency(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first' => ['required', 'integer', 'min:1'],
            'second' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->log->consistency((int) $data['first'], isset($data['second']) ? (int) $data['second'] : null));
    }
}
