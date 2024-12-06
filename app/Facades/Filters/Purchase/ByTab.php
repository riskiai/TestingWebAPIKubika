<?php

namespace App\Facades\Filters\Purchase;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class ByTab
{
    public function handle(Builder $query, Closure $next)
    {
        if (!request()->has('tab_purchase')) {
            return $next($query);
        }

        $query->where('tab_purchase', request('tab_purchase', 1));

        return $next($query);
    }
}
