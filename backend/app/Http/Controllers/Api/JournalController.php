<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Journal\StoreJournalEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Http\Resources\JournalEntrySummaryResource;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(private JournalService $journals) {}

    public function index(Request $request)
    {
        return JournalEntrySummaryResource::collection(
            $this->journals->paginate($request->user())
        )->additional(['success' => true]);
    }

    public function store(StoreJournalEntryRequest $request)
    {
        $entry = $this->journals->create(
            $request->user(),
            $request->validated('body'),
            $request->validated('prompt')
        );

        return response()->json([
            'success' => true,
            'message' => 'حُفظت كلماتك.',
            'journal_entry' => JournalEntryResource::make($entry),
        ], 201);
    }

    public function show(Request $request, JournalEntry $journalEntry)
    {
        $this->journals->ensureOwner($request->user(), $journalEntry);

        return response()->json([
            'success' => true,
            'journal_entry' => JournalEntryResource::make($journalEntry),
        ]);
    }

    public function destroy(Request $request, JournalEntry $journalEntry)
    {
        $this->journals->delete($request->user(), $journalEntry);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المدخلة من يومياتك.',
        ]);
    }
}
