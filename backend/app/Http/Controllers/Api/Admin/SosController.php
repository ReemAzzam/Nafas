<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpsertSosCounselorRequest;
use App\Http\Requests\Api\Admin\UpsertSosHotlineRequest;
use App\Http\Resources\Admin\AdminSosCounselorResource;
use App\Http\Resources\Admin\AdminSosHotlineResource;
use App\Models\SosCounselor;
use App\Models\SosHotline;
use App\Services\Admin\AdminSosService;

class SosController extends Controller
{
    public function __construct(private AdminSosService $sos) {}

    public function counselors()
    {
        return AdminSosCounselorResource::collection(
            SosCounselor::query()->orderBy('sort_order')->paginate(20)
        )->additional(['success' => true]);
    }

    public function storeCounselor(UpsertSosCounselorRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة جهة الدعم بنجاح.',
            'counselor' => AdminSosCounselorResource::make(
                $this->sos->saveCounselor(null, $request->validated(), $request->file('image'))
            ),
        ], 201);
    }

    public function updateCounselor(UpsertSosCounselorRequest $request, SosCounselor $counselor)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث جهة الدعم بنجاح.',
            'counselor' => AdminSosCounselorResource::make(
                $this->sos->saveCounselor($counselor, $request->validated(), $request->file('image'))
            ),
        ]);
    }

    public function destroyCounselor(SosCounselor $counselor)
    {
        $this->sos->deleteCounselor($counselor);

        return response()->json(['success' => true, 'message' => 'تم حذف جهة الدعم بنجاح.']);
    }

    public function hotlines()
    {
        return AdminSosHotlineResource::collection(
            SosHotline::query()->orderBy('sort_order')->paginate(20)
        )->additional(['success' => true]);
    }

    public function storeHotline(UpsertSosHotlineRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة الخط الساخن بنجاح.',
            'hotline' => AdminSosHotlineResource::make(
                $this->sos->saveHotline(null, $request->validated())
            ),
        ], 201);
    }

    public function updateHotline(UpsertSosHotlineRequest $request, SosHotline $hotline)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الخط الساخن بنجاح.',
            'hotline' => AdminSosHotlineResource::make(
                $this->sos->saveHotline($hotline, $request->validated())
            ),
        ]);
    }

    public function destroyHotline(SosHotline $hotline)
    {
        $hotline->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الخط الساخن بنجاح.']);
    }
}
