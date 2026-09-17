<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

trait HandlesBulkDestroy
{
    /**
     * @return list<int>
     */
    protected function validatedBulkIds(Request $request): array
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
