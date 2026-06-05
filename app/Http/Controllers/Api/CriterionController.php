<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CriterionResource;
use App\Models\AuditCriterion;
use Illuminate\Http\Request;

class CriterionController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'general');

        $criteria = AuditCriterion::where('is_active', true)
            ->appliesTo($type)
            ->orderBy('order_index')
            ->get();

        return CriterionResource::collection($criteria);
    }
}
