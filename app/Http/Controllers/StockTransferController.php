<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * NOTE: referenced by routes/web.php ('/stock-lots/available') but did not
 * exist anywhere in the base repo — this fatally broke `php artisan
 * route:list` and would have 500'd if the route was ever hit. Adding a
 * minimal stub so routing resolves; the Stock Management module itself
 * (Locations / Stock In-Out, linked from the sidebar) is not part of the
 * travel-agency scope and is left for whoever picks that feature back up.
 */
class StockTransferController extends Controller
{
    public function getAvailableLots(Request $request)
    {
        return response()->json([]);
    }
}
