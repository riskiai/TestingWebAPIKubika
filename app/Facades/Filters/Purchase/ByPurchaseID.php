<?php

namespace App\Facades\Filters\Purchase;

use App\Models\Role;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class ByPurchaseID
{
    public function handle(Builder $query, Closure $next)
    {
        if (auth()->user()->role_id == Role::MARKETING) {
            $query->where('user_id', auth()->user()->id);
        }

        if (!request()->has('purchase_id')) {
            return $next($query);
        }

        $query->where('type_purchase_id', request('type_purchase_id'));

        return $next($query);
    }
}
