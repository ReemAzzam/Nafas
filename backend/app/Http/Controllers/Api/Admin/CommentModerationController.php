<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ModerateCommentRequest;
use App\Http\Requests\Api\Admin\ResolveCommentReportRequest;
use App\Http\Resources\Admin\AdminCommentResource;
use App\Models\ArticleComment;
use App\Models\ArticleCommentReport;
use App\Services\CommentModerationService;
use Illuminate\Http\Request;

class CommentModerationController extends Controller
{
    public function __construct(private CommentModerationService $moderation) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $comments = ArticleComment::query()
            ->whereHas('reports', fn ($query) => $status === 'all'
                ? $query
                : $query->where('status', $status))
            ->with([
                'article' => fn ($query) => $query->withTrashed()->select('id', 'title', 'deleted_at'),
                'user' => fn ($query) => $query->withTrashed()->select('id', 'display_name', 'deleted_at'),
                'reports' => fn ($query) => $query
                    ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                    ->with(['user' => fn ($query) => $query->withTrashed()->select('id', 'display_name', 'deleted_at')]),
            ])
            ->withCount([
                'reports',
                'reports as pending_reports_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest('id')
            ->paginate(max(1, min((int) $request->query('per_page', 20), 100)));

        return AdminCommentResource::collection($comments)->additional(['success' => true]);
    }

    public function hide(ModerateCommentRequest $request, ArticleComment $comment)
    {
        $comment = $this->moderation->hide(
            $comment,
            $request->user(),
            $request->validated('admin_note'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إخفاء التعليق ومعالجة بلاغاته المفتوحة.',
            'comment' => AdminCommentResource::make($comment),
        ]);
    }

    public function restore(ArticleComment $comment)
    {
        return response()->json([
            'success' => true,
            'message' => 'تمت إعادة إظهار التعليق.',
            'comment' => AdminCommentResource::make($this->moderation->restore($comment)),
        ]);
    }

    public function resolveReport(
        ResolveCommentReportRequest $request,
        ArticleCommentReport $report,
    ) {
        $report->update([
            'status' => $request->validated('status'),
            'admin_note' => $request->validated('admin_note'),
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة البلاغ.',
        ]);
    }
}
