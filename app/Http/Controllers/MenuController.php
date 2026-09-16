<?php

namespace App\Http\Controllers;

use App\Services\Menu\MenuBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __invoke(Request $request, MenuBuilder $menu): JsonResponse
    {
        return response()->json(['data' => $menu->build($request->user())]);
    }
}
