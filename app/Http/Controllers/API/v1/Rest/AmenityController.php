<?php

namespace App\Http\Controllers\API\v1\Rest;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;

class AmenityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Amenity::all(),
        ]);
    }
}
