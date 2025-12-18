<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface TableQueryService
{
    public function getQuery(array $params): Builder;
}
